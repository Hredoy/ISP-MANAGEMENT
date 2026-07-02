<?php

namespace Tests\Feature;

use App\Models\Mikrotik;
use App\Models\MockMikrotikInterface;
use App\Models\MockMikrotikLog;
use App\Models\MockMikrotikSystem;
use App\Models\MockMikrotikUser;
use App\Models\Tenant;
use App\Models\TenantApplication;
use App\Services\TenantProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MikrotikDemoTickTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDatabase;

    private ?Tenant $tenant = null;

    protected function tearDown(): void
    {
        $this->dropTestTenantDatabases();

        parent::tearDown();
    }

    public function test_tick_advances_uptime_traffic_and_writes_a_log_entry(): void
    {
        $router = $this->setupDemoRouter('demo-tick');

        MockMikrotikSystem::on('tenant')->create([
            'mikrotik_id' => $router->id,
            'uptime_seconds' => 100,
            'cpu_load' => 10,
            'free_memory' => 50_000_000,
            'total_memory' => 128_000_000,
        ]);

        $interface = MockMikrotikInterface::on('tenant')->create([
            'mikrotik_id' => $router->id,
            'name' => 'ether1',
            'running' => true,
            'rx_bytes' => 1000,
            'tx_bytes' => 500,
        ]);

        MockMikrotikUser::on('tenant')->create([
            'mikrotik_id' => $router->id,
            'username' => 'tickuser',
            'password' => 'secret',
        ]);

        tenancy()->initialize($this->tenant);

        Artisan::call('mikrotik:demo:tick');

        tenancy()->end();
        $this->reconnectTenant();

        $system = MockMikrotikSystem::on('tenant')->where('mikrotik_id', $router->id)->firstOrFail();
        $this->assertSame(160, $system->uptime_seconds);

        $interface->refresh();
        $this->assertGreaterThanOrEqual(1000, $interface->rx_bytes);
        $this->assertGreaterThanOrEqual(500, $interface->tx_bytes);

        $this->assertSame(1, MockMikrotikLog::on('tenant')->where('mikrotik_id', $router->id)->count());
    }

    public function test_tick_skips_routers_not_in_demo_mode(): void
    {
        $this->dropTestTenantDatabases();

        $application = app(TenantProvisioningService::class)->approve(TenantApplication::create([
            'organization_name' => 'Demo Tick Real',
            'slug' => 'demo-tick-real',
            'contact_name' => 'Admin',
            'email' => 'admin@demo-tick-real.test',
            'phone' => '01700000030',
            'status' => 'pending',
        ]), 'secret-demo-tick-real');

        $this->tenantDatabase = $application->database_name;
        $this->tenant = $application->tenant;
        $this->reconnectTenant();

        $router = Mikrotik::on('tenant')->create([
            'name' => 'Real Router', 'host' => '10.0.0.1', 'port' => 8728,
            'username' => 'admin', 'password' => 'secret', 'mode' => 'real',
        ]);

        tenancy()->initialize($this->tenant);

        Artisan::call('mikrotik:demo:tick');

        tenancy()->end();
        $this->reconnectTenant();

        $this->assertSame(0, MockMikrotikLog::on('tenant')->where('mikrotik_id', $router->id)->count());
    }

    private function setupDemoRouter(string $slug): Mikrotik
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
        $this->tenant = $application->tenant;
        $this->reconnectTenant();

        return Mikrotik::on('tenant')->create([
            'name' => 'Demo Router', 'host' => '10.0.0.1', 'port' => 8728,
            'username' => 'admin', 'password' => 'secret', 'mode' => 'demo',
        ]);
    }

    private function reconnectTenant(): void
    {
        Config::set('database.connections.tenant', array_replace(Config::get('database.connections.mysql'), [
            'database' => $this->tenantDatabase,
        ]));
        DB::purge('tenant');
        DB::reconnect('tenant');
    }

    // dropTestTenantDatabases() now lives on the base Tests\TestCase (see tests/TestCase.php).
}
