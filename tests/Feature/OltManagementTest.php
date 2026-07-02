<?php

namespace Tests\Feature;

use App\Models\Olt;
use App\Models\TenantApplication;
use App\Models\User;
use App\Services\OltService;
use App\Services\TenantProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OltManagementTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDatabase;

    protected function tearDown(): void
    {
        $this->dropTestTenantDatabases();

        parent::tearDown();
    }

    public function test_authenticated_user_can_create_an_olt_device(): void
    {
        [$host, $user] = $this->setupTenant('olt-create');

        $this->mock(OltService::class)
            ->shouldReceive('testConnection')
            ->once()
            ->andReturn(['ok' => true, 'message' => 'CONNECTION_OK']);

        $response = $this->actingAs($user)->post("http://{$host}/dashboard/olts", [
            'name' => 'Main OLT',
            'vendor' => 'huawei',
            'host' => '192.168.1.10',
            'port' => 23,
            'username' => 'root',
            'password' => 'secret',
        ]);

        $response->assertSessionHasNoErrors()->assertRedirect('/dashboard/olts');

        $this->reconnectTenant();
        $this->assertDatabaseHas('olts', [
            'name' => 'Main OLT',
            'vendor' => 'huawei',
            'host' => '192.168.1.10',
        ], 'tenant');

        $stored = Olt::on('tenant')->where('name', 'Main OLT')->firstOrFail();
        $this->assertNotSame('secret', $stored->getAttributes()['password']);
        $this->assertSame('secret', $stored->password);
    }

    public function test_update_can_change_olt_credentials(): void
    {
        [$host, $user] = $this->setupTenant('olt-update');

        $olt = Olt::on('tenant')->create([
            'name' => 'Edge OLT',
            'vendor' => 'zte',
            'host' => '10.0.0.5',
            'port' => 23,
            'username' => 'admin',
            'password' => 'old-pass',
            'is_active' => true,
        ]);

        $this->actingAs($user)->put("http://{$host}/dashboard/olts/{$olt->id}", [
            'name' => 'Edge OLT',
            'vendor' => 'zte',
            'host' => '10.0.0.5',
            'port' => 23,
            'username' => 'admin',
            'password' => 'new-pass',
            'is_active' => true,
        ])->assertRedirect('/dashboard/olts');

        $this->reconnectTenant();
        $this->assertSame('new-pass', Olt::on('tenant')->findOrFail($olt->id)->password);
    }

    /**
     * @return array{0: string, 1: User}
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

        return ["{$slug}.localhost", $user];
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
