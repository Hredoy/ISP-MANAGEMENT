<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Mikrotik;
use App\Models\Olt;
use App\Models\Package;
use App\Models\TenantApplication;
use App\Models\User;
use App\Services\MikroTik\Contracts\MikroTikServiceInterface;
use App\Services\MikroTik\MikroTikServiceFactory;
use App\Services\OltService;
use App\Services\TenantProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Mockery\MockInterface;
use Tests\TestCase;

class ClientProvisioningWizardTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDatabase;

    protected function tearDown(): void
    {
        $this->dropTestTenantDatabases();

        parent::tearDown();
    }

    public function test_step_one_creates_client_with_auto_computed_expiry(): void
    {
        [$host, $user, $router] = $this->setupTenant('wizard-create');

        $response = $this->actingAs($user)->postJson("http://{$host}/dashboard/clients/provision/client", [
            'mikrotik_id' => $router->id,
            'pppoe_username' => 'wizarduser',
            'pppoe_password' => 'secret123',
            'package_name' => 'Nano',
            'full_name' => 'Wizard User',
            'phone_number' => '01711111111',
            'monthly_bill' => 500,
            'full_address' => 'Somewhere',
            'expiry_date' => now()->addDay()->toDateString(),
        ]);

        $response->assertOk()->assertJson(['success' => true]);
        $clientId = $response->json('client_id');

        $this->reconnectTenant();
        $client = Client::on('tenant')->findOrFail($clientId);
        $this->assertSame(now()->addDays(30)->toDateString(), $client->expiry_date);
    }

    public function test_pppoe_step_is_idempotent_on_retry(): void
    {
        [$host, $user, $router] = $this->setupTenant('wizard-pppoe');
        $client = $this->makeClient($router);

        $service = $this->fakeMikroTikService();
        $service->shouldReceive('getPPPoEUsers')->twice()->andReturn([]);
        $service->shouldReceive('addPPPoEUser')->twice()->andReturn([]);

        // Call twice, simulating a manual retry after a UI-level hiccup.
        $this->actingAs($user)->postJson("http://{$host}/dashboard/clients/provision/{$client->id}/pppoe")
            ->assertOk()->assertJson(['success' => true]);
        $this->actingAs($user)->postJson("http://{$host}/dashboard/clients/provision/{$client->id}/pppoe")
            ->assertOk()->assertJson(['success' => true]);
    }

    public function test_pppoe_step_returns_422_when_router_unreachable(): void
    {
        [$host, $user, $router] = $this->setupTenant('wizard-pppoe-fail');
        $client = $this->makeClient($router);

        $this->fakeMikroTikService()
            ->shouldReceive('getPPPoEUsers')
            ->once()
            ->andThrow(new \RuntimeException('MikroTik router is unreachable.'));

        $this->actingAs($user)->postJson("http://{$host}/dashboard/clients/provision/{$client->id}/pppoe")
            ->assertStatus(422)
            ->assertJson(['success' => false]);
    }

    public function test_onu_step_skips_when_no_olt_selected(): void
    {
        [$host, $user, $router] = $this->setupTenant('wizard-onu-skip');
        $client = $this->makeClient($router);

        $this->actingAs($user)->postJson("http://{$host}/dashboard/clients/provision/{$client->id}/onu", [])
            ->assertOk()
            ->assertJson(['success' => true, 'skipped' => true]);
    }

    public function test_onu_step_binds_when_olt_selected(): void
    {
        [$host, $user, $router] = $this->setupTenant('wizard-onu-bind');
        $client = $this->makeClient($router);

        $olt = Olt::on('tenant')->create([
            'name' => 'Field OLT', 'vendor' => 'huawei', 'host' => '10.0.0.9', 'port' => 23,
        ]);

        $this->mock(OltService::class)
            ->shouldReceive('bindOnu')
            ->once()
            ->andReturn(['ok' => true, 'message' => 'ONU_BOUND']);

        $this->actingAs($user)->postJson("http://{$host}/dashboard/clients/provision/{$client->id}/onu", [
            'olt_id' => $olt->id,
            'onu_serial' => 'HWTC12345678',
            'pon_port' => '0/1',
        ])->assertOk()->assertJson(['success' => true]);

        $this->reconnectTenant();
        $this->assertDatabaseHas('clients', ['id' => $client->id, 'olt_id' => $olt->id, 'onu_serial' => 'HWTC12345678'], 'tenant');
    }

    public function test_queue_step_skips_for_dynamic_pool_package(): void
    {
        [$host, $user, $router] = $this->setupTenant('wizard-queue-skip');
        $client = $this->makeClient($router);

        Package::on('tenant')->create([
            'mikrotik_id' => $router->id, 'name' => 'Nano', 'rate_limit' => '5M/5M', 'price' => 500,
            'remote_address' => 'client-pool',
        ]);

        $this->actingAs($user)->postJson("http://{$host}/dashboard/clients/provision/{$client->id}/queue")
            ->assertOk()
            ->assertJson(['success' => true, 'skipped' => true]);
    }

    public function test_queue_step_applies_when_package_has_static_ip(): void
    {
        [$host, $user, $router] = $this->setupTenant('wizard-queue-static');
        $client = $this->makeClient($router);

        Package::on('tenant')->create([
            'mikrotik_id' => $router->id, 'name' => 'Nano', 'rate_limit' => '5M/5M', 'price' => 500,
            'remote_address' => '10.10.10.5',
        ]);

        $this->fakeMikroTikService()
            ->shouldReceive('addSimpleQueue')
            ->once()
            ->andReturn([]);

        $this->actingAs($user)->postJson("http://{$host}/dashboard/clients/provision/{$client->id}/queue")
            ->assertOk()
            ->assertJson(['success' => true]);
    }

    public function test_sms_step_fails_clearly_when_no_active_gateway(): void
    {
        [$host, $user, $router] = $this->setupTenant('wizard-sms-none');
        $client = $this->makeClient($router);

        $this->actingAs($user)->postJson("http://{$host}/dashboard/clients/provision/{$client->id}/sms")
            ->assertStatus(422)
            ->assertJson(['success' => false]);
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

    private function makeClient(Mikrotik $router): Client
    {
        return Client::on('tenant')->create([
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

    private function dropTestTenantDatabases(): void
    {
        foreach (DB::select("SHOW DATABASES LIKE 'test\\_tenant\\_%'") as $row) {
            $database = array_values((array) $row)[0];

            DB::statement('DROP DATABASE IF EXISTS `'.str_replace('`', '``', $database).'`');
        }
    }
}
