<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Mikrotik;
use App\Models\Package;
use App\Models\TenantApplication;
use App\Models\User;
use App\Models\Zone;
use App\Services\MikroTik\Contracts\MikroTikServiceInterface;
use App\Services\MikroTik\MikroTikServiceFactory;
use App\Services\TenantProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Mockery\MockInterface;
use Tests\TestCase;

class ClientManagementTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDatabase;

    protected function tearDown(): void
    {
        $this->dropTestTenantDatabases();

        parent::tearDown();
    }

    public function test_index_supports_search_filter_sort_and_pagination(): void
    {
        [$host, $user, $router] = $this->setupTenant('client-list');

        $zone = Zone::on('tenant')->create(['name' => 'North']);

        Client::on('tenant')->create([
            'mikrotik_id' => $router->id, 'zone_id' => $zone->id,
            'pppoe_username' => 'alice', 'pppoe_password' => 'secret1',
            'package_name' => 'Nano', 'full_name' => 'Alice Anderson',
            'phone_number' => '01711111111', 'monthly_bill' => 500,
            'full_address' => 'Addr', 'expiry_date' => now()->addMonth()->toDateString(),
            'status' => 'Active',
        ]);

        Client::on('tenant')->create([
            'mikrotik_id' => $router->id,
            'pppoe_username' => 'bob', 'pppoe_password' => 'secret2',
            'package_name' => 'Pro', 'full_name' => 'Bob Baker',
            'phone_number' => '01722222222', 'monthly_bill' => 1200,
            'full_address' => 'Addr', 'expiry_date' => now()->subDay()->toDateString(),
            'status' => 'Active',
        ]);

        // Search narrows to a single client.
        $this->actingAs($user)
            ->get("http://{$host}/dashboard/clients?search=alice")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('clients.total', 1)
                ->where('clients.data.0.pppoe_username', 'alice')
            );

        // Filter by zone.
        $this->actingAs($user)
            ->get("http://{$host}/dashboard/clients?zone_id={$zone->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('clients.total', 1));

        // Filter by computed Expired status (bob's expiry_date is in the past).
        $this->actingAs($user)
            ->get("http://{$host}/dashboard/clients?status=Expired")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('clients.total', 1)
                ->where('clients.data.0.pppoe_username', 'bob')
            );

        // Sort by monthly_bill ascending.
        $this->actingAs($user)
            ->get("http://{$host}/dashboard/clients?sort=monthly_bill&direction=asc")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('clients.data.0.pppoe_username', 'alice')
                ->where('clients.data.1.pppoe_username', 'bob')
            );
    }

    public function test_store_creates_client_and_provisions_pppoe_on_mikrotik(): void
    {
        [$host, $user, $router] = $this->setupTenant('client-create');

        $this->fakeMikroTikService()
            ->shouldReceive('addPPPoEUser')
            ->once()
            ->andReturn([]);

        $response = $this->actingAs($user)->post("http://{$host}/dashboard/clients", [
            'mikrotik_id' => $router->id,
            'pppoe_username' => 'newclient',
            'pppoe_password' => 'secret123',
            'package_name' => 'Nano',
            'full_name' => 'New Client',
            'phone_number' => '01700000001',
            'monthly_bill' => 500,
            'full_address' => 'Somewhere',
            'expiry_date' => now()->addMonth()->toDateString(),
        ]);

        $response->assertRedirect('/dashboard/clients');

        $this->reconnectTenant();
        $this->assertDatabaseHas('clients', ['pppoe_username' => 'newclient', 'status' => 'Active'], 'tenant');
    }

    public function test_store_fails_gracefully_when_router_unreachable(): void
    {
        [$host, $user, $router] = $this->setupTenant('client-offline');

        $this->fakeMikroTikService()
            ->shouldReceive('addPPPoEUser')
            ->once()
            ->andThrow(new \RuntimeException('MikroTik router is unreachable.'));

        $response = $this->actingAs($user)->post("http://{$host}/dashboard/clients", [
            'mikrotik_id' => $router->id,
            'pppoe_username' => 'offlineclient',
            'pppoe_password' => 'secret123',
            'package_name' => 'Nano',
            'full_name' => 'Offline Client',
            'phone_number' => '01700000002',
            'monthly_bill' => 500,
            'full_address' => 'Somewhere',
            'expiry_date' => now()->addMonth()->toDateString(),
        ]);

        $response->assertSessionHasErrors('mikrotik_id');

        $this->reconnectTenant();
        $this->assertDatabaseMissing('clients', ['pppoe_username' => 'offlineclient'], 'tenant');
    }

    public function test_destroy_soft_deletes_and_removes_pppoe_from_mikrotik(): void
    {
        [$host, $user, $router] = $this->setupTenant('client-delete');

        $client = Client::on('tenant')->create([
            'mikrotik_id' => $router->id,
            'pppoe_username' => 'todelete', 'pppoe_password' => 'secret',
            'package_name' => 'Nano', 'full_name' => 'To Delete',
            'phone_number' => '01700000003', 'monthly_bill' => 500,
            'full_address' => 'Addr', 'expiry_date' => now()->addMonth()->toDateString(),
            'status' => 'Active',
        ]);

        $this->fakeMikroTikService()
            ->shouldReceive('removePPPoEUser')
            ->once()
            ->andReturn(true);

        $this->actingAs($user)->delete("http://{$host}/dashboard/clients/{$client->id}")
            ->assertRedirect();

        $this->reconnectTenant();
        $this->assertSoftDeleted('clients', ['id' => $client->id], connection: 'tenant');
    }

    public function test_suspend_disables_pppoe_secret_and_updates_status(): void
    {
        [$host, $user, $router] = $this->setupTenant('client-suspend');

        $client = Client::on('tenant')->create([
            'mikrotik_id' => $router->id,
            'pppoe_username' => 'suspendme', 'pppoe_password' => 'secret',
            'package_name' => 'Nano', 'full_name' => 'Suspend Me',
            'phone_number' => '01700000004', 'monthly_bill' => 500,
            'full_address' => 'Addr', 'expiry_date' => now()->addMonth()->toDateString(),
            'status' => 'Active',
        ]);

        $this->fakeMikroTikService()
            ->shouldReceive('disableUser')
            ->once()
            ->andReturn(true);

        $this->actingAs($user)->post("http://{$host}/dashboard/clients/{$client->id}/suspend")
            ->assertRedirect();

        $this->reconnectTenant();
        $this->assertDatabaseHas('clients', ['id' => $client->id, 'status' => 'Suspended'], 'tenant');
    }

    public function test_unsuspend_re_enables_pppoe_secret_and_updates_status(): void
    {
        [$host, $user, $router] = $this->setupTenant('client-unsuspend');

        $client = Client::on('tenant')->create([
            'mikrotik_id' => $router->id,
            'pppoe_username' => 'unsuspendme', 'pppoe_password' => 'secret',
            'package_name' => 'Nano', 'full_name' => 'Unsuspend Me',
            'phone_number' => '01700000005', 'monthly_bill' => 500,
            'full_address' => 'Addr', 'expiry_date' => now()->addMonth()->toDateString(),
            'status' => 'Suspended',
        ]);

        $this->fakeMikroTikService()
            ->shouldReceive('enableUser')
            ->once()
            ->andReturn(true);

        $this->actingAs($user)->post("http://{$host}/dashboard/clients/{$client->id}/unsuspend")
            ->assertRedirect();

        $this->reconnectTenant();
        $this->assertDatabaseHas('clients', ['id' => $client->id, 'status' => 'Active'], 'tenant');
    }

    public function test_package_can_be_updated(): void
    {
        [$host, $user, $router] = $this->setupTenant('package-update');

        $package = Package::on('tenant')->create([
            'mikrotik_id' => $router->id,
            'name' => 'Nano',
            'rate_limit' => '5M/5M',
            'price' => 500,
        ]);

        $this->actingAs($user)->put("http://{$host}/dashboard/packages/{$package->id}", [
            'mikrotik_id' => $router->id,
            'name' => 'Nano',
            'rate_limit' => '10M/10M',
            'price' => 800,
        ])->assertRedirect('/dashboard/packages');

        $this->reconnectTenant();
        $this->assertDatabaseHas('packages', ['id' => $package->id, 'rate_limit' => '10M/10M', 'price' => 800], 'tenant');
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
            'name' => 'Core Router',
            'host' => '10.0.0.1',
            'port' => 8728,
            'username' => 'admin',
            'password' => 'secret',
        ]);

        return ["{$slug}.localhost", $user, $router];
    }

    /**
     * MikroTikServiceFactory::make() constructs Real/MockMikroTikService directly (never
     * container-resolved), so faking the router connection means swapping the factory binding
     * itself rather than mocking a service class - keeps controllers genuinely mode-agnostic
     * instead of special-casing test runtime in production code.
     */
    private function fakeMikroTikService(): MockInterface
    {
        $service = \Mockery::mock(MikroTikServiceInterface::class);

        $factory = \Mockery::mock(MikroTikServiceFactory::class);
        $factory->shouldReceive('make')->andReturn($service);

        $this->app->instance(MikroTikServiceFactory::class, $factory);

        return $service;
    }

    /**
     * The tenant middleware ends tenancy (and reverts the dynamic 'tenant'
     * connection) once the HTTP request finishes, so it must be reconnected
     * before making further Eloquent/assertion calls against it directly.
     */
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
