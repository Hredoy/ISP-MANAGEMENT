<?php

namespace Tests\Feature;

use App\Models\Mikrotik;
use App\Models\Setting;
use App\Models\Tenant;
use App\Models\TenantApplication;
use App\Services\MikroTik\DTO\ModeResolution;
use App\Services\MikroTik\MikroTikServiceFactory;
use App\Services\MikroTik\MockMikroTikService;
use App\Services\MikroTik\ModeResolver;
use App\Services\MikroTik\RealMikroTikService;
use App\Services\TenantProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Covers the spec's own worked example: Global=Demo, RouterA=Real, RouterB=Use_Global should
 * resolve RouterA -> Real, RouterB -> Demo.
 *
 * ModeResolver::globalMode() reads `Setting` via the bare (default) connection, which is only the
 * tenant DB once tenancy is genuinely initialized (stancl/tenancy's DatabaseTenancyBootstrapper
 * swaps `config('database.default')`, not just a side connection) - so these tests call
 * tenancy()->initialize() for real rather than the raw Config::set() swap trick some sibling
 * tests use only for post-request assertions.
 */
class MikrotikModeResolutionTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDatabase;

    private ?Tenant $tenant = null;

    protected function tearDown(): void
    {
        $this->dropTestTenantDatabases();

        parent::tearDown();
    }

    public function test_per_router_override_wins_over_global_setting(): void
    {
        $this->setupTenant('mode-resolution');

        Setting::on('tenant')->updateOrCreate(['key' => 'mikrotik_mode'], ['value' => ['mode' => 'demo']]);

        $routerA = Mikrotik::on('tenant')->create([
            'name' => 'Router A', 'host' => '10.0.0.1', 'port' => 8728,
            'username' => 'admin', 'password' => 'secret', 'mode' => 'real',
        ]);

        $routerB = Mikrotik::on('tenant')->create([
            'name' => 'Router B', 'host' => '10.0.0.2', 'port' => 8728,
            'username' => 'admin', 'password' => 'secret', 'mode' => 'use_global',
        ]);

        tenancy()->initialize($this->tenant);

        $resolver = app(ModeResolver::class);
        $factory = app(MikroTikServiceFactory::class);

        $this->assertSame(ModeResolver::MODE_REAL, $resolver->resolve($routerA));
        $this->assertSame(ModeResolver::MODE_DEMO, $resolver->resolve($routerB));

        $this->assertInstanceOf(RealMikroTikService::class, $factory->make($routerA));
        $this->assertInstanceOf(MockMikroTikService::class, $factory->make($routerB));
    }

    public function test_resolve_with_source_reports_where_the_mode_came_from(): void
    {
        $this->setupTenant('mode-source');

        Setting::on('tenant')->updateOrCreate(['key' => 'mikrotik_mode'], ['value' => ['mode' => 'real']]);

        $overridden = Mikrotik::on('tenant')->create([
            'name' => 'Overridden', 'host' => '10.0.0.1', 'port' => 8728,
            'username' => 'admin', 'password' => 'secret', 'mode' => 'demo',
        ]);

        $inherited = Mikrotik::on('tenant')->create([
            'name' => 'Inherited', 'host' => '10.0.0.2', 'port' => 8728,
            'username' => 'admin', 'password' => 'secret', 'mode' => 'use_global',
        ]);

        tenancy()->initialize($this->tenant);

        $resolver = app(ModeResolver::class);

        $overriddenResolution = $resolver->resolveWithSource($overridden);
        $this->assertEquals(new ModeResolution('demo', ModeResolution::SOURCE_ROUTER_OVERRIDE), $overriddenResolution);

        $inheritedResolution = $resolver->resolveWithSource($inherited);
        $this->assertEquals(new ModeResolution('real', ModeResolution::SOURCE_GLOBAL), $inheritedResolution);
    }

    public function test_global_mode_defaults_to_real_when_no_setting_exists(): void
    {
        $this->setupTenant('mode-default');

        $router = Mikrotik::on('tenant')->create([
            'name' => 'Default Mode Router', 'host' => '10.0.0.1', 'port' => 8728,
            'username' => 'admin', 'password' => 'secret', 'mode' => 'use_global',
        ]);

        tenancy()->initialize($this->tenant);

        $this->assertSame(ModeResolver::MODE_REAL, app(ModeResolver::class)->resolve($router));
    }

    private function setupTenant(string $slug): void
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
