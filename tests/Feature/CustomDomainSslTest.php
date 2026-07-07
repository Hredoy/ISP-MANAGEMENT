<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Notifications\CustomDomainLiveNotification;
use App\Services\Dns\DnsResolver;
use App\Services\DomainProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class CustomDomainSslTest extends TestCase
{
    use RefreshDatabase;

    private string $sitesPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sitesPath = sys_get_temp_dir().'/nginx-sites-'.uniqid();
        mkdir($this->sitesPath, recursive: true);

        Config::set('domain-provisioning.nginx.enabled', true);
        Config::set('domain-provisioning.nginx.cname_target', 'sites.yourplatform.com');
        Config::set('domain-provisioning.nginx.sites_available_path', $this->sitesPath);
        Config::set('domain-provisioning.nginx.sites_enabled_path', $this->sitesPath);
    }

    protected function tearDown(): void
    {
        \Illuminate\Support\Facades\File::deleteDirectory($this->sitesPath);
        $this->dropTestTenantDatabases();

        parent::tearDown();
    }

    public function test_skips_when_nginx_auto_ssl_is_disabled(): void
    {
        Config::set('domain-provisioning.nginx.enabled', false);
        $tenant = $this->makeTenant('example.com');

        $result = app(DomainProvisioningService::class)->provisionCustomDomainSsl($tenant, 'example.com');

        $this->assertSame('skipped', $result['status']);
        $this->assertSame(Tenant::SSL_NOT_APPLICABLE, $tenant->fresh()->ssl_status);
    }

    public function test_marks_pending_dns_until_the_cname_resolves(): void
    {
        $tenant = $this->makeTenant('example.com');
        $this->mockDnsResolver(false);

        $result = app(DomainProvisioningService::class)->provisionCustomDomainSsl($tenant, 'example.com');

        $this->assertSame('pending', $result['status']);
        $this->assertSame(Tenant::SSL_PENDING_DNS, $tenant->fresh()->ssl_status);
    }

    public function test_issues_certificate_and_writes_nginx_block_once_dns_resolves(): void
    {
        Notification::fake();
        Process::fake();
        $tenant = $this->makeTenant('example.com');
        $this->mockDnsResolver(true);

        $result = app(DomainProvisioningService::class)->provisionCustomDomainSsl($tenant, 'example.com');

        $this->assertSame('completed', $result['status']);
        $fresh = $tenant->fresh();
        $this->assertSame(Tenant::SSL_ACTIVE, $fresh->ssl_status);
        $this->assertNotNull($fresh->ssl_issued_at);
        Notification::assertSentOnDemand(CustomDomainLiveNotification::class);
    }

    public function test_marks_failed_when_certbot_fails(): void
    {
        Process::fake(['*certbot*' => Process::result(output: '', errorOutput: 'DNS problem', exitCode: 1)]);
        $tenant = $this->makeTenant('example.com');
        $this->mockDnsResolver(true);

        $result = app(DomainProvisioningService::class)->provisionCustomDomainSsl($tenant, 'example.com');

        $this->assertSame('failed', $result['status']);
        $this->assertSame(Tenant::SSL_FAILED, $tenant->fresh()->ssl_status);
    }

    public function test_does_not_renotify_once_already_active(): void
    {
        Notification::fake();
        Process::fake();
        $tenant = $this->makeTenant('example.com');
        $tenant->update(['ssl_status' => Tenant::SSL_ACTIVE]);
        $this->mockDnsResolver(true);

        app(DomainProvisioningService::class)->provisionCustomDomainSsl($tenant, 'example.com');

        Notification::assertNothingSent();
    }

    public function test_poll_command_retries_pending_tenants_and_leaves_others_alone(): void
    {
        Notification::fake();
        Process::fake();
        $tenant = $this->makeTenant('example.com');
        $tenant->update(['ssl_status' => Tenant::SSL_PENDING_DNS]);
        $this->mockDnsResolver(true);

        $this->artisan('domains:poll-custom-ssl')->assertExitCode(0);

        $this->assertSame(Tenant::SSL_ACTIVE, $tenant->fresh()->ssl_status);
    }

    private function makeTenant(string $customDomain): Tenant
    {
        $tenant = Tenant::create([
            'id' => 'acme',
            'organization_name' => 'Acme ISP',
            'admin_email' => 'admin@acme.test',
            'status' => Tenant::STATUS_ACTIVE,
            'database_name' => 'tenant_acme',
            'database_status' => 'ready',
            'domain_status' => 'active',
        ]);

        $tenant->domains()->create(['domain' => 'acme.yourplatform.com']);
        $tenant->domains()->create(['domain' => $customDomain]);

        return $tenant->fresh();
    }

    private function mockDnsResolver(bool $resolves): void
    {
        $resolver = \Mockery::mock(DnsResolver::class);
        $resolver->shouldReceive('pointsTo')->once()->andReturn($resolves);

        $this->app->instance(DnsResolver::class, $resolver);
    }
}
