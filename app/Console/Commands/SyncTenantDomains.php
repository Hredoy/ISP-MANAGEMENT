<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\TenantApplication;
use App\Services\DomainProvisioningService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class SyncTenantDomains extends Command
{
    protected $signature = 'tenants:sync-domains {tenant? : Optional tenant id}';

    protected $description = 'Add tenant domains to local hosts and cPanel when configured.';

    public function handle(DomainProvisioningService $domainProvisioningService): int
    {
        $query = Tenant::with('domains');

        if ($tenantId = $this->argument('tenant')) {
            $query->whereKey($tenantId);
        }

        $tenants = $query->get();

        if ($tenants->isEmpty()) {
            $this->warn('No tenants found.');

            return self::SUCCESS;
        }

        foreach ($tenants as $tenant) {
            $domains = $this->ensureLandlordSubdomain($tenant);
            $result = $domainProvisioningService->provisionTenantDomains($tenant, $domains);

            $this->info($tenant->id.': local='.$result['local']['status'].' cpanel='.$result['cpanel']['status']);

            if (($result['local']['message'] ?? null)) {
                $this->line('  local: '.$result['local']['message']);
            }

            if (($result['cpanel']['message'] ?? null)) {
                $this->line('  cPanel: '.$result['cpanel']['message']);
            }
        }

        return self::SUCCESS;
    }

    private function ensureLandlordSubdomain(Tenant $tenant): array
    {
        $application = TenantApplication::where('tenant_id', $tenant->id)->first();
        $label = $application ? $this->landlordSubdomainLabel($application) : $tenant->id;
        $landlordDomain = env('LANDLORD_DOMAIN', 'localhost');
        $landlordSubdomain = strtolower($label).'.'.$landlordDomain;

        $tenant->domains()->firstOrCreate(['domain' => $landlordSubdomain]);
        $this->removeMalformedLandlordDomain($tenant, $label, $landlordDomain, $landlordSubdomain);

        if ($application && $application->subdomain !== $landlordSubdomain) {
            $application->forceFill(['subdomain' => $landlordSubdomain])->save();
        }

        return $tenant->domains()->pluck('domain')->all();
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

    private function removeMalformedLandlordDomain(Tenant $tenant, string $label, string $landlordDomain, string $landlordSubdomain): void
    {
        $rootWithoutTld = Str::beforeLast($landlordDomain, '.');
        $malformed = strtolower($label).'.'.$rootWithoutTld;

        if ($malformed !== $landlordSubdomain) {
            $tenant->domains()->where('domain', $malformed)->delete();
        }
    }
}
