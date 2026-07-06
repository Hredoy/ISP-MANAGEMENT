<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Mikrotik;
use App\Models\Package;
use App\Models\Tenant;
use App\Models\TenantApplication;
use App\Services\MikroTik\Contracts\MikroTikServiceInterface;
use App\Services\MikroTik\MikroTikServiceFactory;
use App\Services\SmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Mockery\MockInterface;
use Tests\TestCase;

class BillingAutomationTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDatabase;

    private ?Tenant $tenant = null;

    protected function tearDown(): void
    {
        $this->dropTestTenantDatabases();

        parent::tearDown();
    }

    public function test_sends_d7_and_d3_reminders(): void
    {
        $router = $this->setupTenant('billing-remind');
        $clientD7 = $this->makeClient($router, ['expiry_date' => now()->addDays(7)->toDateString()]);
        $clientD3 = $this->makeClient($router, ['pppoe_username' => 'client-d3-'.uniqid(), 'expiry_date' => now()->addDays(3)->toDateString()]);
        $this->makeClient($router, ['pppoe_username' => 'client-safe-'.uniqid(), 'expiry_date' => now()->addDays(20)->toDateString()]);

        $sms = $this->fakeSmsService();
        $sms->shouldReceive('sendExpiryReminder')->once()->with(\Mockery::on(fn (Client $c) => $c->id === $clientD7->id), 7)->andReturn(['ok' => true, 'message' => 'SENT']);
        $sms->shouldReceive('sendExpiryReminder')->once()->with(\Mockery::on(fn (Client $c) => $c->id === $clientD3->id), 3)->andReturn(['ok' => true, 'message' => 'SENT']);

        $this->runBillingCommand();
    }

    public function test_throttles_client_expiring_today_with_static_ip(): void
    {
        $router = $this->setupTenant('billing-throttle');
        Package::on('tenant')->create([
            'mikrotik_id' => $router->id, 'name' => 'Nano', 'rate_limit' => '5M/5M', 'price' => 500,
            'remote_address' => '10.10.10.5',
        ]);
        $client = $this->makeClient($router, ['expiry_date' => now()->toDateString()]);

        $this->fakeMikroTikService()->shouldReceive('addSimpleQueue')->once()
            ->with($client->pppoe_username, '10.10.10.5', '512k/512k')->andReturn([]);

        $this->runBillingCommand();

        $this->reconnectTenant();
        $updated = Client::on('tenant')->findOrFail($client->id);
        $this->assertNotNull($updated->throttled_at);
    }

    public function test_skips_throttle_for_dynamic_ip_package(): void
    {
        $router = $this->setupTenant('billing-throttle-skip');
        Package::on('tenant')->create([
            'mikrotik_id' => $router->id, 'name' => 'Nano', 'rate_limit' => '5M/5M', 'price' => 500,
            'remote_address' => 'client-pool',
        ]);
        $client = $this->makeClient($router, ['expiry_date' => now()->toDateString()]);

        $this->fakeMikroTikService()->shouldNotReceive('addSimpleQueue');

        $this->runBillingCommand();

        $this->reconnectTenant();
        $updated = Client::on('tenant')->findOrFail($client->id);
        $this->assertNull($updated->throttled_at);
    }

    public function test_restores_bandwidth_once_client_has_paid(): void
    {
        $router = $this->setupTenant('billing-restore');
        Package::on('tenant')->create([
            'mikrotik_id' => $router->id, 'name' => 'Nano', 'rate_limit' => '5M/5M', 'price' => 500,
            'remote_address' => '10.10.10.9',
        ]);
        $client = $this->makeClient($router, [
            'expiry_date' => now()->addDays(30)->toDateString(),
            'throttled_at' => now()->subDay(),
        ]);

        $this->fakeMikroTikService()->shouldReceive('addSimpleQueue')->once()
            ->with($client->pppoe_username, '10.10.10.9', '5M/5M')->andReturn([]);

        $this->runBillingCommand();

        $this->reconnectTenant();
        $updated = Client::on('tenant')->findOrFail($client->id);
        $this->assertNull($updated->throttled_at);
    }

    public function test_auto_suspends_client_expired_yesterday(): void
    {
        $router = $this->setupTenant('billing-suspend');
        $client = $this->makeClient($router, ['expiry_date' => now()->subDay()->toDateString()]);

        $this->fakeMikroTikService()->shouldReceive('disableUser')->once()->with($client->pppoe_username)->andReturn(true);

        $this->runBillingCommand();

        $this->reconnectTenant();
        $updated = Client::on('tenant')->findOrFail($client->id);
        $this->assertSame('Suspended', $updated->status);
    }

    public function test_auto_suspend_does_not_mark_suspended_when_router_unreachable(): void
    {
        $router = $this->setupTenant('billing-suspend-fail');
        $client = $this->makeClient($router, ['expiry_date' => now()->subDay()->toDateString()]);

        $this->fakeMikroTikService()->shouldReceive('disableUser')->once()->andThrow(new \RuntimeException('unreachable'));

        $this->runBillingCommand();

        $this->reconnectTenant();
        $updated = Client::on('tenant')->findOrFail($client->id);
        $this->assertSame('Active', $updated->status);
    }

    public function test_escalation_runs_without_error_for_week_overdue_suspended_client(): void
    {
        $router = $this->setupTenant('billing-escalate');
        $this->makeClient($router, ['expiry_date' => now()->subDays(7)->toDateString(), 'status' => 'Suspended']);

        $this->runBillingCommand();

        $this->assertTrue(true);
    }

    private function setupTenant(string $slug): Mikrotik
    {
        $this->dropTestTenantDatabases();

        $application = app(\App\Services\TenantProvisioningService::class)->approve(TenantApplication::create([
            'organization_name' => ucfirst($slug),
            'slug' => $slug,
            'contact_name' => 'Admin',
            'email' => "admin@{$slug}.test",
            'phone' => '017'.substr(str_pad((string) crc32($slug), 8, '0'), 0, 8),
            'status' => 'pending',
        ]), 'secret-'.$slug);

        $this->tenantDatabase = $application->database_name;
        $this->tenant = $application->tenant;
        $this->reconnectTenant();

        return Mikrotik::on('tenant')->create([
            'name' => 'Core Router', 'host' => '10.0.0.1', 'port' => 8728, 'username' => 'admin', 'password' => 'secret',
        ]);
    }

    private function makeClient(Mikrotik $router, array $overrides = []): Client
    {
        return Client::on('tenant')->create(array_merge([
            'mikrotik_id' => $router->id,
            'pppoe_username' => 'client-'.uniqid(),
            'pppoe_password' => 'secret123',
            'package_name' => 'Nano',
            'full_name' => 'Test Client',
            'phone_number' => '01799999999',
            'monthly_bill' => 500,
            'full_address' => 'Somewhere',
            'expiry_date' => now()->addDays(30)->toDateString(),
            'status' => 'Active',
        ], $overrides));
    }

    private function runBillingCommand(): void
    {
        tenancy()->initialize($this->tenant);
        Artisan::call('billing:process-expirations');
        tenancy()->end();
        $this->reconnectTenant();
    }

    private function reconnectTenant(): void
    {
        Config::set('database.connections.tenant', array_replace(Config::get('database.connections.mysql'), [
            'database' => $this->tenantDatabase,
        ]));
        DB::purge('tenant');
        DB::reconnect('tenant');
    }

    private function fakeMikroTikService(): MockInterface
    {
        $service = \Mockery::mock(MikroTikServiceInterface::class);

        $factory = \Mockery::mock(MikroTikServiceFactory::class);
        $factory->shouldReceive('make')->andReturn($service);

        $this->app->instance(MikroTikServiceFactory::class, $factory);

        return $service;
    }

    private function fakeSmsService(): MockInterface
    {
        $mock = \Mockery::mock(SmsService::class);
        $this->app->instance(SmsService::class, $mock);

        return $mock;
    }
}
