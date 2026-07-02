<?php

namespace Tests\Feature;

use App\Models\Mikrotik;
use App\Models\MockMikrotikInterface;
use App\Models\MockMikrotikProfile;
use App\Models\MockMikrotikQueue;
use App\Models\MockMikrotikSession;
use App\Models\MockMikrotikUser;
use App\Models\Tenant;
use App\Models\TenantApplication;
use App\Services\TenantProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MikrotikDemoDataGeneratorTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDatabase;

    private ?Tenant $tenant = null;

    protected function tearDown(): void
    {
        tenancy()->end();
        $this->dropTestTenantDatabases();

        parent::tearDown();
    }

    public function test_generates_demo_data_for_a_demo_mode_router(): void
    {
        $router = $this->setupDemoRouter('demo-gen');

        tenancy()->initialize($this->tenant);

        Artisan::call('mikrotik:demo:generate', [
            'mikrotik' => $router->id,
            '--users' => 10,
            '--sessions' => 4,
            '--profiles' => 3,
            '--interfaces' => 2,
            '--queues' => 5,
        ]);

        tenancy()->end();
        $this->reconnectTenant();

        $this->assertSame(10, MockMikrotikUser::on('tenant')->where('mikrotik_id', $router->id)->count());
        $this->assertSame(4, MockMikrotikSession::on('tenant')->where('mikrotik_id', $router->id)->count());
        // 2 random profiles + the always-present 'default' one.
        $this->assertSame(3, MockMikrotikProfile::on('tenant')->where('mikrotik_id', $router->id)->count());
        $this->assertSame(2, MockMikrotikInterface::on('tenant')->where('mikrotik_id', $router->id)->count());
        $this->assertSame(5, MockMikrotikQueue::on('tenant')->where('mikrotik_id', $router->id)->count());

        $sessionedUsernames = MockMikrotikSession::on('tenant')->where('mikrotik_id', $router->id)->pluck('username');
        $this->assertCount(4, $sessionedUsernames->unique());
    }

    public function test_only_seeds_routers_effectively_in_demo_mode_when_none_specified(): void
    {
        $demoRouter = $this->setupDemoRouter('demo-auto');

        $realRouter = Mikrotik::on('tenant')->create([
            'name' => 'Real Router', 'host' => '10.0.0.2', 'port' => 8728,
            'username' => 'admin', 'password' => 'secret', 'mode' => 'real',
        ]);

        tenancy()->initialize($this->tenant);

        Artisan::call('mikrotik:demo:generate', ['--users' => 5, '--sessions' => 0, '--profiles' => 1, '--interfaces' => 1, '--queues' => 1]);

        tenancy()->end();
        $this->reconnectTenant();

        $this->assertSame(5, MockMikrotikUser::on('tenant')->where('mikrotik_id', $demoRouter->id)->count());
        $this->assertSame(0, MockMikrotikUser::on('tenant')->where('mikrotik_id', $realRouter->id)->count());
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

    private function dropTestTenantDatabases(): void
    {
        foreach (DB::select("SHOW DATABASES LIKE 'test\\_tenant\\_%'") as $row) {
            $database = array_values((array) $row)[0];

            DB::statement('DROP DATABASE IF EXISTS `'.str_replace('`', '``', $database).'`');
        }
    }
}
