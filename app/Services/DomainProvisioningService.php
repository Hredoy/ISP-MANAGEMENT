<?php

namespace App\Services;

use App\Models\Tenant;
use App\Notifications\CustomDomainLiveNotification;
use App\Services\Dns\DnsResolver;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

class DomainProvisioningService
{
    private readonly DnsResolver $dnsResolver;

    public function __construct(?DnsResolver $dnsResolver = null)
    {
        $this->dnsResolver = $dnsResolver ?? app(DnsResolver::class);
    }

    public function provisionTenantDomains(Tenant $tenant, array $domains): array
    {
        $domains = array_values(array_unique(array_filter($domains)));

        return [
            'local' => $this->provisionLocalHosts($domains),
            'cpanel' => $this->provisionCpanel($tenant, $domains),
        ];
    }

    /**
     * Plain-VPS flow for a tenant's custom domain (the free {slug}.LANDLORD_DOMAIN subdomain
     * never needs this - it's served by an existing wildcard vhost/cert): poll DNS for the
     * CNAME target, then Certbot + an Nginx server block once it resolves. Safe to call
     * repeatedly (from the scheduled command) - each step short-circuits once already done.
     *
     * ⚠️ Honesty flag: the Certbot/Nginx calls below run real shell commands via Process.
     * They've only ever been exercised with Process::fake() in tests - never against a real
     * certbot binary or a real Nginx install. Verify on an actual VPS before relying on this.
     */
    public function provisionCustomDomainSsl(Tenant $tenant, string $domain): array
    {
        if (! config('domain-provisioning.nginx.enabled')) {
            return ['status' => 'skipped', 'message' => 'Nginx auto-SSL is disabled.'];
        }

        $tenant->ssl_last_checked_at = now();
        $wasAlreadyActive = $tenant->ssl_status === Tenant::SSL_ACTIVE;

        try {
            $target = config('domain-provisioning.nginx.cname_target');

            if (! $this->dnsResolver->pointsTo($domain, $target)) {
                $tenant->ssl_status = Tenant::SSL_PENDING_DNS;
                $tenant->save();

                return ['status' => 'pending', 'message' => "Waiting for {$domain} to point its CNAME at {$target}."];
            }

            $tenant->ssl_status = Tenant::SSL_ISSUING;
            $tenant->save();

            $certbotResult = $this->runCertbot($domain, $tenant->admin_email);

            if (! $certbotResult['status']) {
                $tenant->ssl_status = Tenant::SSL_FAILED;
                $tenant->save();

                return ['status' => 'failed', 'message' => 'Certbot could not issue a certificate.', 'error' => $certbotResult['output']];
            }

            $nginxResult = $this->writeNginxServerBlock($domain);

            if (! $nginxResult['status']) {
                $tenant->ssl_status = Tenant::SSL_FAILED;
                $tenant->save();

                return ['status' => 'failed', 'message' => 'Nginx server block could not be applied.', 'error' => $nginxResult['output']];
            }

            $tenant->ssl_status = Tenant::SSL_ACTIVE;
            $tenant->ssl_issued_at = now();
            $tenant->save();

            if (! $wasAlreadyActive && $tenant->admin_email) {
                Notification::route('mail', $tenant->admin_email)->notify(new CustomDomainLiveNotification($domain));
            }

            return ['status' => 'completed', 'message' => "{$domain} is live with a free SSL certificate."];
        } catch (\Throwable $exception) {
            $tenant->ssl_status = Tenant::SSL_FAILED;
            $tenant->save();

            return ['status' => 'failed', 'message' => 'Domain SSL provisioning threw an exception.', 'error' => $exception->getMessage()];
        }
    }

    private function runCertbot(string $domain, ?string $contactEmail): array
    {
        $result = Process::timeout(120)->run([
            config('domain-provisioning.nginx.certbot_binary'),
            'certonly',
            '--webroot',
            '-w', config('domain-provisioning.nginx.webroot'),
            '-d', $domain,
            '--non-interactive',
            '--agree-tos',
            '-m', $contactEmail ?: 'admin@'.$domain,
            '--quiet',
        ]);

        return ['status' => $result->successful(), 'output' => $result->errorOutput() ?: $result->output()];
    }

    private function writeNginxServerBlock(string $domain): array
    {
        $availablePath = rtrim(config('domain-provisioning.nginx.sites_available_path'), '/').'/'.$domain.'.conf';
        $enabledPath = rtrim(config('domain-provisioning.nginx.sites_enabled_path'), '/').'/'.$domain.'.conf';

        try {
            File::put($availablePath, $this->nginxServerBlock($domain));
        } catch (\Throwable $exception) {
            return ['status' => false, 'output' => $exception->getMessage()];
        }

        // Symlinking goes through Process (not PHP's File::link()) so it's fakeable in tests
        // and doesn't depend on Windows-only-with-elevation symlink support in dev sandboxes.
        $symlink = Process::run(['ln', '-sf', $availablePath, $enabledPath]);

        if (! $symlink->successful()) {
            return ['status' => false, 'output' => $symlink->errorOutput()];
        }

        $test = Process::run(['nginx', '-t']);

        if (! $test->successful()) {
            return ['status' => false, 'output' => $test->errorOutput()];
        }

        $reload = Process::run(explode(' ', config('domain-provisioning.nginx.reload_command')));

        return ['status' => $reload->successful(), 'output' => $reload->errorOutput()];
    }

    private function nginxServerBlock(string $domain): string
    {
        $root = config('domain-provisioning.nginx.app_root');

        return <<<NGINX
        server {
            listen 443 ssl;
            server_name {$domain};
            root {$root};

            ssl_certificate /etc/letsencrypt/live/{$domain}/fullchain.pem;
            ssl_certificate_key /etc/letsencrypt/live/{$domain}/privkey.pem;

            index index.php;

            location / {
                try_files \$uri \$uri/ /index.php?\$query_string;
            }

            location ~ \.php\$ {
                fastcgi_pass unix:/var/run/php/php-fpm.sock;
                fastcgi_index index.php;
                include fastcgi_params;
                fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
            }
        }
        NGINX;
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
