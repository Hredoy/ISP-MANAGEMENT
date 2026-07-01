<?php

namespace App\Services;

use App\Models\Mikrotik;
use App\Models\Module;
use App\Models\Package;
use App\Models\Setting;
use App\Models\Tenant;
use App\Models\TenantApplication;
use App\Models\TenantModule;
use App\Models\TenantPackage;
use App\Models\TenantProvisioningLog;
use App\Models\TenantSetting;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TenantProvisioningService
{
    private readonly DomainProvisioningService $domainProvisioningService;

    public function __construct(?DomainProvisioningService $domainProvisioningService = null)
    {
        $this->domainProvisioningService = $domainProvisioningService ?? app(DomainProvisioningService::class);
    }

    public function approve(TenantApplication $application, ?string $adminPassword = null): TenantApplication
    {
        if ($application->status === 'converted') {
            return $application;
        }

        if ($application->status === 'approved' && $adminPassword !== null) {
            return $this->convert($application, $adminPassword);
        }

        if ($application->status === 'approved') {
            return $application;
        }

        $application->update([
            'status' => 'approved',
            'approved_at' => now(),
            'rejection_reason' => null,
        ]);

        $this->logAudit('application_approved', 'Organization application approved.', $application);

        $application = $application->fresh();

        if ($adminPassword !== null) {
            return $this->convert($application, $adminPassword);
        }

        return $application;
    }

    public function reject(TenantApplication $application, string $reason): TenantApplication
    {
        if ($application->status === 'converted') {
            throw new \RuntimeException('Converted applications cannot be rejected.');
        }

        $application->update([
            'status' => 'rejected',
            'rejection_reason' => $reason,
        ]);

        $this->logAudit('application_rejected', $reason, $application);

        return $application->fresh();
    }

    public function convert(TenantApplication $application, ?string $adminPassword = null): TenantApplication
    {
        if ($application->status === 'converted' && $application->tenant_id) {
            return $application;
        }

        if ($application->status !== 'approved') {
            throw new \RuntimeException('Only approved applications can be converted to tenants.');
        }

        $slug = $application->slug ?: $this->uniqueSlug($application->organization_name);
        $tenantId = $application->tenant_id ?: $slug;
        $subdomain = $this->landlordSubdomain($this->landlordSubdomainLabel($application));
        $customDomain = $this->customDomainFromApplication($application);

        if (config('database.default') !== 'mysql') {
            throw new \RuntimeException('Tenant provisioning currently supports mysql connection only.');
        }

        if (Tenant::where('id', '!=', $tenantId)->where('admin_email', $application->email)->exists()) {
            throw new \RuntimeException('A tenant already exists for this admin email.');
        }

        $tenant = Tenant::find($tenantId) ?? Tenant::create([
            'id' => $tenantId,
            'organization_name' => $application->organization_name,
            'owner_name' => $application->owner_name ?: $application->contact_name,
            'admin_email' => $application->email,
            'status' => Tenant::STATUS_PENDING_SETUP,
            'database_status' => 'pending',
            'domain_status' => 'pending',
        ]);
        $databaseName = $application->database_name ?: $tenant->database()->getName();

        $this->logStep($application, $tenant, 'tenant_record', 'completed', 'Tenant record created or reused.');

        try {
            $tenant->update([
                'organization_name' => $application->organization_name,
                'owner_name' => $application->owner_name ?: $application->contact_name,
                'admin_email' => $application->email,
                'status' => Tenant::STATUS_PENDING_SETUP,
                'database_name' => $databaseName,
                'database_status' => 'creating',
                'domain_status' => 'pending',
            ]);

            DB::statement(sprintf('CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci', str_replace('`', '``', $databaseName)));
            $tenant->update(['database_status' => 'created']);
            $this->logStep($application, $tenant, 'database_created', 'completed', 'Tenant database is ready.');

            $tenant->domains()->firstOrCreate(['domain' => $subdomain]);

            if ($customDomain && $customDomain !== $subdomain) {
                $tenant->domains()->firstOrCreate(['domain' => $customDomain]);
            }

            $domainProvisioningResult = $this->domainProvisioningService->provisionTenantDomains(
                $tenant,
                array_values(array_filter([$subdomain, $customDomain]))
            );
            $domainProvisioningFailed = collect($domainProvisioningResult)->contains(fn ($result) => in_array($result['status'], ['failed', 'partial'], true));

            $tenant->update(['domain_status' => $domainProvisioningFailed ? 'dns_pending' : 'active']);
            $this->logStep(
                $application,
                $tenant,
                'domains_created',
                $domainProvisioningFailed ? 'warning' : 'completed',
                $domainProvisioningFailed ? 'Tenant domains were saved, but DNS/hosts automation needs attention.' : 'Tenant domain records are ready.',
                $domainProvisioningResult
            );

            $this->runTenantMigrations($databaseName);
            $tenant->update(['database_status' => 'migrated']);
            $this->logStep($application, $tenant, 'migrations', 'completed', 'Tenant migrations completed.');

            $this->seedTenant($databaseName, $application, $adminPassword ?? Str::password(12));
            $this->seedCentralDefaults($tenant, $application);
            $this->logStep($application, $tenant, 'defaults_seeded', 'completed', 'Default tenant settings, admin, roles, packages, and billing settings seeded.');

            $this->assignModules($tenant, $application->module_request ?: $this->defaultModuleSlugs());
            $this->logStep($application, $tenant, 'modules_assigned', 'completed', 'Requested tenant modules assigned.');

            $package = TenantPackage::updateOrCreate(
                ['tenant_id' => $tenant->id, 'name' => $application->package_request ?: $application->plan ?: 'starter'],
                ['status' => 'active', 'limits' => ['source' => 'application']]
            );
            $package->modules()->sync(Module::whereIn('slug', $application->module_request ?: $this->defaultModuleSlugs())->pluck('id'));

            $tenant->update([
                'status' => Tenant::STATUS_ACTIVE,
                'database_status' => 'ready',
                'domain_status' => 'active',
            ]);

            $application->update([
                'slug' => $slug,
                'status' => 'converted',
                'tenant_id' => $tenant->id,
                'database_name' => $databaseName,
                'subdomain' => $subdomain,
                'admin_email' => $application->email,
                'converted_at' => now(),
            ]);

            $this->logAudit('tenant_created', 'Organization converted into tenant.', $application, $tenant);

            return $application->fresh();
        } catch (\Throwable $exception) {
            $tenant->update([
                'status' => Tenant::STATUS_PENDING_SETUP,
                'database_status' => 'failed',
            ]);

            $this->logStep($application, $tenant, 'provisioning_failed', 'failed', $exception->getMessage());

            throw $exception;
        }
    }

    public function setTenantModules(Tenant $tenant, array $enabledModuleSlugs): void
    {
        $this->assignModules($tenant, $enabledModuleSlugs);
        $this->logAudit('tenant_modules_updated', 'Tenant module access updated.', null, $tenant, [
            'modules' => array_values($enabledModuleSlugs),
        ]);
    }

    public function setTenantStatus(Tenant $tenant, string $status, ?string $message = null): void
    {
        $tenant->update([
            'status' => $status,
            'suspended_message' => $status === Tenant::STATUS_SUSPENDED ? $message : null,
        ]);

        $this->logAudit($status === Tenant::STATUS_SUSPENDED ? 'tenant_suspended' : 'tenant_status_updated', 'Tenant status updated.', null, $tenant, [
            'status' => $status,
            'message' => $message,
        ]);
    }

    public function ensureModulesSeeded(): void
    {
        foreach ($this->defaultModules() as $index => $module) {
            Module::updateOrCreate(
                ['slug' => $module['slug']],
                [...$module, 'sort_order' => $index + 1, 'is_active' => true]
            );
        }
    }

    public function defaultModuleSlugs(): array
    {
        return array_column($this->defaultModules(), 'slug');
    }

    private function runTenantMigrations(string $databaseName): void
    {
        $this->setTenantConnection($databaseName);

        Artisan::call('migrate', [
            '--database' => 'tenant',
            '--path' => 'database/migrations/tenant',
            '--force' => true,
        ]);
    }

    private function seedTenant(string $databaseName, TenantApplication $application, string $adminPassword): void
    {
        $this->setTenantConnection($databaseName);

        DB::connection('tenant')->transaction(function () use ($application, $adminPassword): void {
            $router = Mikrotik::on('tenant')->firstOrCreate(
                ['name' => 'Default MikroTik'],
                [
                    'host' => $application->mikrotik_ip ?: '127.0.0.1',
                    'port' => 8728,
                    'username' => 'admin',
                    'password' => '',
                    'sitename' => $application->district,
                    'is_active' => (bool) $application->mikrotik_ip,
                ]
            );

            foreach ($this->defaultPackages() as $package) {
                Package::on('tenant')->updateOrCreate(
                    ['name' => $package['name']],
                    [
                        ...$package,
                        'mikrotik_id' => $router->id,
                        'description' => 'Default onboarding package',
                    ]
                );
            }

            User::on('tenant')->updateOrCreate(
                ['email' => $application->email],
                [
                    'name' => $application->owner_name ?: $application->contact_name,
                    'password' => Hash::make($adminPassword),
                    'email_verified_at' => now(),
                ]
            );

            Setting::on('tenant')->updateOrCreate(['key' => 'organization'], ['value' => [
                'name' => $application->organization_name,
                'owner_name' => $application->owner_name ?: $application->contact_name,
                'email' => $application->email,
                'phone' => $application->phone,
                'address' => $application->address,
                'business_type' => $application->business_type,
                'district' => $application->district,
                'logo_path' => $application->logo_path,
                'plan' => $application->plan,
            ]]);

            Setting::on('tenant')->updateOrCreate(['key' => 'network'], ['value' => [
                'mikrotik_ip' => $application->mikrotik_ip,
                'olt_ip' => $application->olt_ip,
                'olt_brand' => $application->olt_brand,
            ]]);

            Setting::on('tenant')->updateOrCreate(['key' => 'roles'], ['value' => [
                'default_roles' => ['Admin', 'Manager', 'Support', 'Accounts'],
                'default_permissions' => ['view', 'create', 'update', 'delete', 'report'],
            ]]);

            Setting::on('tenant')->updateOrCreate(['key' => 'modules'], ['value' => [
                'enabled' => $application->module_request ?: $this->defaultModuleSlugs(),
            ]]);

            Setting::on('tenant')->updateOrCreate(['key' => 'billing'], ['value' => [
                'currency' => 'BDT',
                'billing_day' => 1,
                'payment_methods' => ['cash', 'bkash', 'nagad', 'bank'],
            ]]);

            Setting::on('tenant')->updateOrCreate(['key' => 'notifications'], ['value' => [
                'sms_enabled' => in_array('sms', $application->module_request ?: [], true),
                'email_enabled' => true,
            ]]);
        });
    }

    private function defaultPackages(): array
    {
        return [
            ['name' => 'Nano', 'rate_limit' => '5M/5M', 'price' => 500],
            ['name' => 'Starter', 'rate_limit' => '10M/10M', 'price' => 800],
            ['name' => 'Pro', 'rate_limit' => '20M/20M', 'price' => 1200],
            ['name' => 'Enterprise', 'rate_limit' => '50M/50M', 'price' => 1500],
        ];
    }

    private function setTenantConnection(string $databaseName): void
    {
        $mysqlConnection = Config::get('database.connections.mysql', []);
        $tenantConnection = Config::get('database.connections.tenant', []);
        $tenantConnection = is_array($tenantConnection) ? $tenantConnection : [];

        Config::set('database.connections.tenant', array_replace($mysqlConnection, $tenantConnection, [
            'database' => $databaseName,
        ]));

        DB::purge('tenant');
        DB::reconnect('tenant');
    }

    private function uniqueSlug(string $organizationName): string
    {
        $base = Str::slug($organizationName);
        $slug = $base;
        $counter = 2;

        while (TenantApplication::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$counter;
            $counter++;
        }

        return $slug;
    }

    private function assignModules(Tenant $tenant, array $enabledModuleSlugs): void
    {
        $this->ensureModulesSeeded();
        $enabledModuleSlugs = array_values(array_unique($enabledModuleSlugs));

        foreach (Module::where('is_active', true)->get() as $module) {
            $enabled = in_array($module->slug, $enabledModuleSlugs, true);

            TenantModule::updateOrCreate(
                ['tenant_id' => $tenant->id, 'module_id' => $module->id],
                [
                    'enabled' => $enabled,
                    'enabled_at' => $enabled ? now() : null,
                    'disabled_at' => $enabled ? null : now(),
                ]
            );
        }
    }

    private function seedCentralDefaults(Tenant $tenant, TenantApplication $application): void
    {
        foreach ([
            'general' => ['timezone' => 'Asia/Dhaka', 'currency' => 'BDT'],
            'company_profile' => ['name' => $application->organization_name, 'owner' => $application->owner_name ?: $application->contact_name],
            'isp' => ['default_package' => $application->package_request ?: $application->plan ?: 'starter'],
            'billing' => ['billing_day' => 1, 'payment_methods' => ['cash', 'bkash', 'nagad', 'bank']],
            'notifications' => ['email' => true, 'sms' => false],
        ] as $key => $value) {
            TenantSetting::updateOrCreate(['tenant_id' => $tenant->id, 'key' => $key], ['value' => $value]);
        }
    }

    private function normalizeDomain(string $domainRequest): string
    {
        $domain = Str::lower(trim(preg_replace('#^https?://#', '', $domainRequest), " \t\n\r\0\x0B/"));

        if (! str_contains($domain, '.')) {
            $domain .= '.'.env('LANDLORD_DOMAIN', 'yourplatform.com');
        }

        return $domain;
    }

    private function landlordSubdomain(string $slug): string
    {
        return Str::lower($slug).'.'.env('LANDLORD_DOMAIN', 'yourplatform.com');
    }

    private function landlordSubdomainLabel(TenantApplication $application): string
    {
        $requestedDomain = $application->custom_domain ?: $application->domain_request;

        if ($requestedDomain) {
            $domain = Str::lower(trim(preg_replace('#^https?://#', '', $requestedDomain), " \t\n\r\0\x0B/"));
            $label = Str::contains($domain, '.') ? Str::before($domain, '.') : $domain;

            if ($label = Str::slug($label)) {
                return $label;
            }
        }

        return $application->slug ?: Str::slug($application->organization_name);
    }

    private function customDomainFromApplication(TenantApplication $application): ?string
    {
        $domain = $application->custom_domain ?: $application->domain_request;

        if (! $domain || ! str_contains($domain, '.')) {
            return null;
        }

        return $this->normalizeDomain($domain);
    }

    private function logStep(TenantApplication $application, Tenant $tenant, string $step, string $status, string $message, array $context = []): void
    {
        TenantProvisioningLog::create([
            'tenant_id' => $tenant->id,
            'tenant_application_id' => $application->id,
            'step' => $step,
            'status' => $status,
            'message' => $message,
            'context' => $context,
        ]);
    }

    private function logAudit(string $action, string $message, ?TenantApplication $application = null, ?Tenant $tenant = null, array $properties = []): void
    {
        \App\Models\LandlordAuditLog::create([
            'actor_type' => auth()->check() ? get_class(auth()->user()) : null,
            'actor_id' => auth()->id(),
            'tenant_id' => $tenant?->id ?: $application?->tenant_id,
            'tenant_application_id' => $application?->id,
            'action' => $action,
            'message' => $message,
            'properties' => $properties,
        ]);
    }

    private function defaultModules(): array
    {
        return [
            ['name' => 'Customers', 'slug' => 'customers'],
            ['name' => 'Packages', 'slug' => 'packages'],
            ['name' => 'Billing', 'slug' => 'billing'],
            ['name' => 'Payments', 'slug' => 'payments'],
            ['name' => 'MikroTik', 'slug' => 'mikrotik'],
            ['name' => 'OLT', 'slug' => 'olt'],
            ['name' => 'Support Tickets', 'slug' => 'support-tickets'],
            ['name' => 'SMS', 'slug' => 'sms'],
            ['name' => 'Reports', 'slug' => 'reports'],
            ['name' => 'Employees', 'slug' => 'employees'],
            ['name' => 'Accounting', 'slug' => 'accounting'],
            ['name' => 'Inventory', 'slug' => 'inventory'],
            ['name' => 'Settings', 'slug' => 'settings'],
        ];
    }
}
