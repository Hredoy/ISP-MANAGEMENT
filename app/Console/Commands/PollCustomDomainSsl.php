<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\DomainProvisioningService;
use Illuminate\Console\Command;

class PollCustomDomainSsl extends Command
{
    protected $signature = 'domains:poll-custom-ssl';

    protected $description = 'Retries DNS/Certbot/Nginx provisioning for tenants whose custom domain is not live yet.';

    public function handle(DomainProvisioningService $domainProvisioningService): int
    {
        $tenants = Tenant::with('domains')
            ->whereIn('ssl_status', [Tenant::SSL_PENDING_DNS, Tenant::SSL_ISSUING])
            ->get();

        foreach ($tenants as $tenant) {
            $domain = $tenant->customDomainName();

            if (! $domain) {
                continue;
            }

            $result = $domainProvisioningService->provisionCustomDomainSsl($tenant, $domain);
            $this->line("{$tenant->id} ({$domain}): {$result['status']} - {$result['message']}");
        }

        return self::SUCCESS;
    }
}
