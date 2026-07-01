<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class DomainProvisioningService
{
    public function provisionTenantDomains(Tenant $tenant, array $domains): array
    {
        $domains = array_values(array_unique(array_filter($domains)));

        return [
            'local' => $this->provisionLocalHosts($domains),
            'cpanel' => $this->provisionCpanel($tenant, $domains),
        ];
    }

    private function provisionLocalHosts(array $domains): array
    {
        if (! config('domain-provisioning.local.enabled')) {
            return ['status' => 'skipped', 'message' => 'Local hosts auto-add is disabled.'];
        }

        $hostsPath = config('domain-provisioning.local.hosts_path');
        $ip = config('domain-provisioning.local.ip');

        try {
            $existing = File::exists($hostsPath) ? File::get($hostsPath) : '';
            $lines = [];

            foreach ($domains as $domain) {
                if (preg_match('/(^|\s)'.preg_quote($domain, '/').'(\s|$)/i', $existing)) {
                    continue;
                }

                $lines[] = $ip.' '.$domain;
            }

            if ($lines === []) {
                return ['status' => 'completed', 'message' => 'All domains already exist in hosts file.'];
            }

            File::append($hostsPath, PHP_EOL.'# ISP Management tenant domains'.PHP_EOL.implode(PHP_EOL, $lines).PHP_EOL);

            return ['status' => 'completed', 'message' => 'Domains added to local hosts file.', 'domains' => $lines];
        } catch (\Throwable $exception) {
            return [
                'status' => 'failed',
                'message' => 'Could not update local hosts file. Run as Administrator/root or add domains manually.',
                'error' => $exception->getMessage(),
            ];
        }
    }

    private function provisionCpanel(Tenant $tenant, array $domains): array
    {
        if (! config('domain-provisioning.cpanel.enabled')) {
            return ['status' => 'skipped', 'message' => 'cPanel domain auto-add is disabled.'];
        }

        if (! $this->hasCpanelCredentials()) {
            return ['status' => 'failed', 'message' => 'cPanel credentials are not configured.'];
        }

        $results = [];

        foreach ($domains as $domain) {
            $results[$domain] = $this->addDomainToCpanel($tenant, $domain);
        }

        $failed = collect($results)->contains(fn ($result) => $result['status'] === 'failed');

        return [
            'status' => $failed ? 'partial' : 'completed',
            'message' => $failed ? 'Some cPanel domains could not be added.' : 'cPanel domains synced.',
            'domains' => $results,
        ];
    }

    private function addDomainToCpanel(Tenant $tenant, string $domain): array
    {
        $landlordDomain = env('LANDLORD_DOMAIN', 'localhost');
        $documentRoot = trim(config('domain-provisioning.cpanel.document_root'), '/');

        if (Str::endsWith($domain, '.'.$landlordDomain)) {
            $subdomain = Str::beforeLast($domain, '.'.$landlordDomain);

            return $this->cpanelRequest('SubDomain', 'addsubdomain', [
                'domain' => $subdomain,
                'rootdomain' => $landlordDomain,
                'dir' => $documentRoot,
            ]);
        }

        return $this->cpanelRequest('AddonDomain', 'addaddondomain', [
            'newdomain' => $domain,
            'subdomain' => $this->cpanelSafeSubdomain($tenant, $domain),
            'dir' => $documentRoot,
        ]);
    }

    private function cpanelRequest(string $module, string $function, array $query): array
    {
        $url = sprintf(
            'https://%s:%s/execute/%s/%s',
            config('domain-provisioning.cpanel.host'),
            config('domain-provisioning.cpanel.port'),
            $module,
            $function
        );

        try {
            $response = Http::withHeaders([
                'Authorization' => 'cpanel '.config('domain-provisioning.cpanel.username').':'.config('domain-provisioning.cpanel.token'),
            ])
                ->withOptions(['verify' => (bool) config('domain-provisioning.cpanel.ssl_verify')])
                ->timeout(20)
                ->get($url, $query);

            $payload = $response->json();
            $ok = $response->successful() && (bool) data_get($payload, 'status');

            return [
                'status' => $ok ? 'completed' : 'failed',
                'message' => data_get($payload, 'messages.0') ?: data_get($payload, 'errors.0') ?: $response->body(),
            ];
        } catch (\Throwable $exception) {
            return ['status' => 'failed', 'message' => $exception->getMessage()];
        }
    }

    private function hasCpanelCredentials(): bool
    {
        return filled(config('domain-provisioning.cpanel.host'))
            && filled(config('domain-provisioning.cpanel.username'))
            && filled(config('domain-provisioning.cpanel.token'));
    }

    private function cpanelSafeSubdomain(Tenant $tenant, string $domain): string
    {
        return Str::limit(Str::slug($tenant->id.'-'.$domain), 60, '');
    }
}
