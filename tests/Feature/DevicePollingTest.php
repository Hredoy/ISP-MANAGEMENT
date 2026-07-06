<?php

namespace Tests\Feature;

use App\Events\DeviceStatusChanged;
use App\Models\Fault;
use App\Models\Mikrotik;
use App\Models\Tenant;
use App\Models\TenantApplication;
use App\Services\DevicePollingService;
use App\Services\MikroTik\Contracts\MikroTikServiceInterface;
use App\Services\MikroTik\MikroTikServiceFactory;
use App\Services\TenantProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class DevicePollingTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDatabase;

    protected function tearDown(): void
    {
        $this->dropTestTenantDatabases();

        parent::tearDown();
    }

    public function test_first_poll_writes_status_and_broadcasts(): void
    {
        [, , $tenant] = $this->setupTenant('poll-first');
        Event::fake([DeviceStatusChanged::class]);

        tenancy()->initialize($tenant);
        $router = Mikrotik::on('tenant')->create(['name' => 'R', 'host' => '10.0.0.1', 'port' => 8728, 'username' => 'admin', 'password' => 'secret']);
        $service = $this->fakeMikroTikService();
        $service->shouldReceive('getSystemResources')->once()->andReturn(['cpu-load' => '20', 'uptime' => '1d']);

        $result = app(DevicePollingService::class)->pollAll();
        $status = $router->fresh()->status;
        tenancy()->end();

        $this->assertSame(1, $result['polled']);
        $this->assertSame(1, $result['changed']);
        $this->assertSame('online', $status);
        Event::assertDispatchedTimes(DeviceStatusChanged::class, 1);
    }

    public function test_second_poll_within_ttl_with_same_status_does_not_rewrite_or_broadcast(): void
    {
        [, , $tenant] = $this->setupTenant('poll-cached');
        Event::fake([DeviceStatusChanged::class]);

        tenancy()->initialize($tenant);
        $router = Mikrotik::on('tenant')->create(['name' => 'R', 'host' => '10.0.0.1', 'port' => 8728, 'username' => 'admin', 'password' => 'secret']);
        $service = $this->fakeMikroTikService();
        $service->shouldReceive('getSystemResources')->twice()->andReturn(['cpu-load' => '20', 'uptime' => '1d']);

        $poller = app(DevicePollingService::class);
        $poller->pollAll();
        $result = $poller->pollAll();
        tenancy()->end();

        $this->assertSame(0, $result['changed']);
        Event::assertDispatchedTimes(DeviceStatusChanged::class, 1);
    }

    public function test_router_becoming_unreachable_is_treated_as_offline_and_broadcasts_the_transition(): void
    {
        [, , $tenant] = $this->setupTenant('poll-offline');
        Event::fake([DeviceStatusChanged::class]);

        tenancy()->initialize($tenant);
        Mikrotik::on('tenant')->create(['name' => 'R', 'host' => '10.0.0.1', 'port' => 8728, 'username' => 'admin', 'password' => 'secret', 'status' => 'online']);
        $service = $this->fakeMikroTikService();
        $service->shouldReceive('getSystemResources')->once()->andThrow(new \RuntimeException('unreachable'));

        $result = app(DevicePollingService::class)->pollAll();
        tenancy()->end();

        $this->assertSame(1, $result['changed']);
        Event::assertDispatched(DeviceStatusChanged::class, fn ($event) => $event->status === 'offline');
    }

    public function test_cpu_high_for_5_minutes_opens_a_fault_and_recovering_resolves_it(): void
    {
        [, , $tenant] = $this->setupTenant('poll-cpu-fault');

        tenancy()->initialize($tenant);
        $router = Mikrotik::on('tenant')->create(['name' => 'R', 'host' => '10.0.0.1', 'port' => 8728, 'username' => 'admin', 'password' => 'secret']);
        $service = $this->fakeMikroTikService();
        $service->shouldReceive('getSystemResources')->twice()->andReturn(['cpu-load' => '95', 'uptime' => '1d']);

        $poller = app(DevicePollingService::class);
        $poller->pollAll();
        // Backdate the "high since" marker so the next poll sees it as 5+ minutes sustained,
        // instead of sleeping the test for real.
        Cache::put("device_cpu_high_since:mikrotik:{$router->id}", now()->subMinutes(6)->toIso8601String(), now()->addHour());
        Cache::forget("device_status:mikrotik:{$router->id}");
        $result = $poller->pollAll();

        $this->assertSame(1, $result['faults_opened']);
        $this->assertDatabaseHas('faults', ['mikrotik_id' => $router->id, 'title' => 'High CPU load', 'status' => 'open'], 'tenant');

        // CPU recovers -> the fault should be auto-resolved.
        $service->shouldReceive('getSystemResources')->once()->andReturn(['cpu-load' => '10', 'uptime' => '1d']);
        Cache::forget("device_status:mikrotik:{$router->id}");
        $recovered = $poller->pollAll();
        tenancy()->end();
        $this->reconnectTenant();

        $this->assertSame(1, $recovered['faults_resolved']);
        $fault = Fault::on('tenant')->where('mikrotik_id', $router->id)->firstOrFail();
        $this->assertSame('resolved', $fault->status);
    }

    public function test_cpu_high_but_not_yet_5_minutes_does_not_open_a_fault(): void
    {
        [, , $tenant] = $this->setupTenant('poll-cpu-not-yet');

        tenancy()->initialize($tenant);
        Mikrotik::on('tenant')->create(['name' => 'R', 'host' => '10.0.0.1', 'port' => 8728, 'username' => 'admin', 'password' => 'secret']);
        $this->fakeMikroTikService()->shouldReceive('getSystemResources')->once()->andReturn(['cpu-load' => '95', 'uptime' => '1d']);

        $result = app(DevicePollingService::class)->pollAll();
        tenancy()->end();
        $this->reconnectTenant();

        $this->assertSame(0, $result['faults_opened']);
        $this->assertDatabaseMissing('faults', ['title' => 'High CPU load'], 'tenant');
    }

    /**
     * @return array{0: string, 1: null, 2: Tenant}
     */
    private function setupTenant(string $slug): array
    {
        $this->dropTestTenantDatabases();

        $application = app(TenantProvisioningService::class)->approve(TenantApplication::create([
            'organization_name' => ucfirst($slug),
            'slug' => $slug,
            'contact_name' => 'Admin',
            'email' => "admin@{$slug}.test",
            'phone' => '017'.substr(str_pad((string) crc32($slug), 8, '0'), 0, 8),
            'status' => 'pending',
        ]), 'secret-'.$slug);

        $this->tenantDatabase = $application->database_name;
        $this->reconnectTenant();

        // Provisioning auto-seeds default router(s) (e.g. "Default MikroTik") - removed so each
        // test's router count is exact and deterministic rather than +N from seeding.
        Mikrotik::on('tenant')->delete();

        return ["{$slug}.localhost", null, $application->tenant];
    }

    private function reconnectTenant(): void
    {
        Config::set('database.connections.tenant', array_replace(Config::get('database.connections.mysql'), [
            'database' => $this->tenantDatabase,
        ]));
        DB::purge('tenant');
        DB::reconnect('tenant');
    }

    private function fakeMikroTikService(): \Mockery\MockInterface
    {
        $service = \Mockery::mock(MikroTikServiceInterface::class);

        $factory = \Mockery::mock(MikroTikServiceFactory::class);
        $factory->shouldReceive('make')->andReturn($service);

        $this->app->instance(MikroTikServiceFactory::class, $factory);

        return $service;
    }
}
