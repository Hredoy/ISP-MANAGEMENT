<?php

namespace App\Services;

use App\Models\Mikrotik;
use App\Models\Package;
use App\Models\Setting;
use App\Models\Tenant;
use App\Models\TenantApplication;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TenantProvisioningService
{
    public function approve(TenantApplication $application, ?string $adminPassword = null): TenantApplication
    {
        if ($application->status === 'approved') {
            return $application;
        }

        $slug = $application->slug ?: $this->uniqueSlug($application->organization_name);
        $tenantId = $application->tenant_id ?: $slug;
        $subdomain = $application->subdomain ?: $slug.'.'.env('LANDLORD_DOMAIN', 'yourplatform.com');
        $tenant = Tenant::find($tenantId) ?? Tenant::create([
            'id' => $tenantId,
            'organization_name' => $application->organization_name,
            'admin_name' => $application->contact_name,
            'admin_email' => $application->email,
        ]);
        $databaseName = $application->database_name ?: $tenant->database()->getName();

        if (config('database.default') !== 'mysql') {
            throw new \RuntimeException('Tenant provisioning currently supports mysql connection only.');
        }

        $tenant->domains()->firstOrCreate(['domain' => $subdomain]);

        if ($application->custom_domain) {
            $tenant->domains()->firstOrCreate(['domain' => $application->custom_domain]);
        }

        DB::statement(sprintf('CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci', $databaseName));

        $this->runTenantMigrations($databaseName);
        $this->seedTenant($databaseName, $application, $adminPassword ?? Str::password(12));

        $application->update([
            'slug' => $slug,
            'status' => 'approved',
            'tenant_id' => $tenant->id,
            'database_name' => $databaseName,
            'subdomain' => $subdomain,
            'admin_email' => $application->email,
            'approved_at' => now(),
        ]);

        return $application->fresh();
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
                    'name' => $application->contact_name,
                    'password' => Hash::make($adminPassword),
                    'email_verified_at' => now(),
                ]
            );

            Setting::on('tenant')->updateOrCreate(['key' => 'organization'], ['value' => [
                'name' => $application->organization_name,
                'district' => $application->district,
                'logo_path' => $application->logo_path,
                'plan' => $application->plan,
            ]]);

            Setting::on('tenant')->updateOrCreate(['key' => 'network'], ['value' => [
                'mikrotik_ip' => $application->mikrotik_ip,
                'olt_ip' => $application->olt_ip,
                'olt_brand' => $application->olt_brand,
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
}
