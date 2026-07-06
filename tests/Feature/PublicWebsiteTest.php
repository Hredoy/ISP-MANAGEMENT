<?php

namespace Tests\Feature;

use App\Models\Mikrotik;
use App\Models\Package;
use App\Models\TenantApplication;
use App\Models\TenantFrontendSetting;
use App\Models\User;
use App\Services\MikroTik\Contracts\MikroTikServiceInterface;
use App\Services\MikroTik\MikroTikServiceFactory;
use App\Services\TenantProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PublicWebsiteTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDatabase;

    protected function tearDown(): void
    {
        $this->dropTestTenantDatabases();

        parent::tearDown();
    }

    public function test_public_website_renders_by_default(): void
    {
        [$host] = $this->setupTenant('site-default');

        $this->get("http://{$host}/")->assertOk();
    }

    public function test_disabling_the_site_returns_404_to_visitors(): void
    {
        [$host] = $this->setupTenant('site-disabled');

        TenantFrontendSetting::on('tenant')->updateOrCreate(['key' => 'site'], ['value' => ['is_enabled' => false]]);

        $this->get("http://{$host}/")->assertNotFound();
    }

    public function test_only_public_packages_are_shown_on_the_website(): void
    {
        [$host, , $router] = $this->setupTenant('site-packages');

        // Provisioning auto-seeds default packages (Nano/Starter/Pro/Enterprise) - removed so
        // this test's package count is exact and deterministic.
        Package::on('tenant')->delete();

        Package::on('tenant')->create([
            'mikrotik_id' => $router->id, 'name' => 'Visible', 'rate_limit' => '10M/10M', 'price' => 800, 'is_public' => true,
        ]);
        Package::on('tenant')->create([
            'mikrotik_id' => $router->id, 'name' => 'Hidden', 'rate_limit' => '20M/20M', 'price' => 1200, 'is_public' => false,
        ]);

        $response = $this->get("http://{$host}/");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('packages', 1)
            ->where('packages.0.name', 'Visible')
        );
    }

    public function test_package_store_defaults_to_public_when_not_specified(): void
    {
        [$host, $user, $router] = $this->setupTenant('site-store-default');

        $this->fakeMikroTikService()->shouldReceive('createPPPProfile')->once()->andReturn([]);

        $this->actingAs($user)->post("http://{$host}/dashboard/packages", [
            'name' => 'Nano', 'mikrotik_id' => $router->id, 'rate_limit' => '5M/5M', 'price' => 500,
        ])->assertSessionHasNoErrors();

        $this->reconnectTenant();
        $this->assertDatabaseHas('packages', ['name' => 'Nano', 'is_public' => 1], 'tenant');
    }

    public function test_package_can_be_marked_hidden_from_the_public_website(): void
    {
        [$host, $user, $router] = $this->setupTenant('site-store-hidden');

        $this->fakeMikroTikService()->shouldReceive('createPPPProfile')->once()->andReturn([]);

        $this->actingAs($user)->post("http://{$host}/dashboard/packages", [
            'name' => 'Nano', 'mikrotik_id' => $router->id, 'rate_limit' => '5M/5M', 'price' => 500, 'is_public' => false,
        ])->assertSessionHasNoErrors();

        $this->reconnectTenant();
        $this->assertDatabaseHas('packages', ['name' => 'Nano', 'is_public' => 0], 'tenant');
    }

    public function test_coverage_zones_appear_on_the_website(): void
    {
        [$host] = $this->setupTenant('site-zones');

        \App\Models\Zone::on('tenant')->create(['name' => 'Uttara']);
        \App\Models\Zone::on('tenant')->create(['name' => 'Mirpur']);

        $response = $this->get("http://{$host}/");

        $response->assertInertia(fn ($page) => $page->has('zones', 2));
    }

    public function test_admin_can_toggle_the_site_off_from_frontend_settings(): void
    {
        [$host, $user] = $this->setupTenant('site-toggle');

        $this->actingAs($user)->patch("http://{$host}/dashboard/frontend", [
            'is_enabled' => false,
        ])->assertSessionHasNoErrors();

        $this->get("http://{$host}/")->assertNotFound();
    }

    /**
     * @return array{0: string, 1: User, 2: Mikrotik}
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

        $user = new User([
            'name' => 'Admin',
            'email' => "user-{$slug}@example.test",
            'password' => Hash::make('password'),
        ]);
        $user->setConnection('tenant');
        $user->save();

        $router = Mikrotik::on('tenant')->create([
            'name' => 'Core Router', 'host' => '10.0.0.1', 'port' => 8728, 'username' => 'admin', 'password' => 'secret',
        ]);

        return ["{$slug}.localhost", $user, $router];
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
