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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Mockery\MockInterface;
use Tests\TestCase;

class ClientImportTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDatabase;

    protected function tearDown(): void
    {
        $this->dropTestTenantDatabases();

        parent::tearDown();
    }

    public function test_template_download_succeeds(): void
    {
        [$host, $user] = $this->setupTenant('import-template');

        $response = $this->actingAs($user)
            ->get("http://{$host}/dashboard/clients-import/template")
            ->assertOk();

        $this->assertStringContainsString('clients_import_template.xlsx', $response->headers->get('content-disposition'));
    }

    public function test_preview_reports_valid_and_invalid_rows_without_persisting(): void
    {
        [$host, $user, $router] = $this->setupTenant('import-preview');

        Package::on('tenant')->create(['mikrotik_id' => $router->id, 'name' => 'Nano', 'rate_limit' => '5M/5M', 'price' => 500]);

        $csv = "Name,Phone,Address,Package,PPPoE Username,PPPoE Password,ONU MAC,Zone\n"
            ."Alice Anderson,01711111111,Addr One,Nano,alice,secret123,,North\n"
            .",01722222222,Addr Two,Nano,bob,secret123,,North\n"; // missing name -> invalid

        $file = UploadedFile::fake()->createWithContent('clients.csv', $csv);

        $response = $this->actingAs($user)
            ->post("http://{$host}/dashboard/clients-import/preview", ['file' => $file])
            ->assertOk();

        $response->assertJson([
            'summary' => ['total' => 2, 'valid' => 1, 'invalid' => 1],
        ]);

        $this->reconnectTenant();
        $this->assertDatabaseMissing('clients', ['pppoe_username' => 'alice'], 'tenant');
    }

    public function test_import_creates_clients_provisions_pppoe_and_auto_creates_zone(): void
    {
        [$host, $user, $router] = $this->setupTenant('import-commit');

        Package::on('tenant')->create(['mikrotik_id' => $router->id, 'name' => 'Nano', 'rate_limit' => '5M/5M', 'price' => 500]);

        $this->fakeMikroTikService()->shouldReceive('addPPPoEUser')->once()->andReturn([]);

        $csv = "Name,Phone,Address,Package,PPPoE Username,PPPoE Password,ONU MAC,Zone\n"
            ."Alice Anderson,01711111111,Addr One,Nano,alice,secret123,,North\n";

        $file = UploadedFile::fake()->createWithContent('clients.csv', $csv);

        $response = $this->actingAs($user)
            ->post("http://{$host}/dashboard/clients-import", ['file' => $file])
            ->assertOk();

        $response->assertJson(['summary' => ['total' => 1, 'created' => 1]]);

        $this->reconnectTenant();
        $this->assertDatabaseHas('clients', ['pppoe_username' => 'alice', 'full_name' => 'Alice Anderson', 'status' => 'Active'], 'tenant');
        $this->assertDatabaseHas('zones', ['name' => 'North'], 'tenant');
    }

    public function test_import_skips_duplicate_pppoe_username_but_keeps_going(): void
    {
        [$host, $user, $router] = $this->setupTenant('import-duplicate');

        Package::on('tenant')->create(['mikrotik_id' => $router->id, 'name' => 'Nano', 'rate_limit' => '5M/5M', 'price' => 500]);

        Client::on('tenant')->create([
            'mikrotik_id' => $router->id, 'pppoe_username' => 'alice', 'pppoe_password' => 'secret',
            'package_name' => 'Nano', 'full_name' => 'Existing Alice', 'phone_number' => '01700000000',
            'monthly_bill' => 500, 'full_address' => 'Addr', 'expiry_date' => now()->addMonth()->toDateString(),
            'status' => 'Active',
        ]);

        $this->fakeMikroTikService()->shouldReceive('addPPPoEUser')->once()->andReturn([]);

        $csv = "Name,Phone,Address,Package,PPPoE Username,PPPoE Password,ONU MAC,Zone\n"
            ."Alice Anderson,01711111111,Addr One,Nano,alice,secret123,,\n" // duplicate username
            ."Bob Baker,01722222222,Addr Two,Nano,bob,secret123,,\n";

        $file = UploadedFile::fake()->createWithContent('clients.csv', $csv);

        $response = $this->actingAs($user)
            ->post("http://{$host}/dashboard/clients-import", ['file' => $file])
            ->assertOk();

        $response->assertJson(['summary' => ['total' => 2, 'created' => 1, 'invalid' => 1]]);

        $this->reconnectTenant();
        $this->assertDatabaseHas('clients', ['pppoe_username' => 'bob'], 'tenant');
        $this->assertDatabaseCount('clients', 2, 'tenant'); // the pre-existing alice + newly created bob
    }

    public function test_import_saves_client_even_when_router_unreachable(): void
    {
        [$host, $user, $router] = $this->setupTenant('import-offline');

        Package::on('tenant')->create(['mikrotik_id' => $router->id, 'name' => 'Nano', 'rate_limit' => '5M/5M', 'price' => 500]);

        $this->fakeMikroTikService()->shouldReceive('addPPPoEUser')->once()->andThrow(new \RuntimeException('unreachable'));

        $csv = "Name,Phone,Address,Package,PPPoE Username,PPPoE Password,ONU MAC,Zone\n"
            ."Alice Anderson,01711111111,Addr One,Nano,alice,secret123,,\n";

        $file = UploadedFile::fake()->createWithContent('clients.csv', $csv);

        $response = $this->actingAs($user)
            ->post("http://{$host}/dashboard/clients-import", ['file' => $file])
            ->assertOk();

        $response->assertJson(['summary' => ['total' => 1, 'created' => 1]]);

        $this->reconnectTenant();
        $this->assertDatabaseHas('clients', ['pppoe_username' => 'alice'], 'tenant');
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

        // A fresh tenant already ships with 4 seeded packages - clear them so
        // Package::whereRaw('LOWER(name) = ?', ['nano']) resolves to the one this test creates.
        Package::on('tenant')->delete();
        Zone::on('tenant')->delete();

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

    private function fakeMikroTikService(): MockInterface
    {
        $service = \Mockery::mock(MikroTikServiceInterface::class);

        $factory = \Mockery::mock(MikroTikServiceFactory::class);
        $factory->shouldReceive('make')->andReturn($service);

        $this->app->instance(MikroTikServiceFactory::class, $factory);

        return $service;
    }

    private function reconnectTenant(): void
    {
        Config::set('database.connections.tenant', array_replace(Config::get('database.connections.mysql'), [
            'database' => $this->tenantDatabase,
        ]));
        DB::purge('tenant');
        DB::reconnect('tenant');
    }
}
