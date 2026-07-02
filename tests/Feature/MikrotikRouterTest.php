<?php

namespace Tests\Feature;

use App\Models\Mikrotik;
use App\Models\TenantApplication;
use App\Models\User;
use App\Services\TenantProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MikrotikRouterTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        $this->dropTestTenantDatabases();

        parent::tearDown();
    }

    public function test_authenticated_user_can_create_a_mikrotik_router(): void
    {
        $this->dropTestTenantDatabases();

        $application = app(TenantProvisioningService::class)->approve(TenantApplication::create([
            'organization_name' => 'Router ISP',
            'slug' => 'router-isp',
            'contact_name' => 'Router Admin',
            'email' => 'admin@router.test',
            'phone' => '01700000004',
            'status' => 'pending',
        ]), 'secret-router');

        $this->usingTenantDatabase($application->database_name);

        $user = new User([
            'name' => 'Router Admin',
            'email' => 'router-admin@example.test',
            'password' => Hash::make('password'),
        ]);
        $user->setConnection('tenant');
        $user->save();

        // mode=demo makes store() skip the live RouterOS connection test entirely (see
        // MikrotikController::store()) - the exact production path a router deliberately
        // marked Demo Mode takes, so no router or service double is needed here.
        $response = $this
            ->actingAs($user)
            ->post('http://router-isp.localhost/dashboard/mikrotik', [
                'name' => 'Core Router',
                'host' => '192.168.88.1',
                'port' => 8728,
                'username' => 'admin',
                'password' => 'secret',
                'description' => 'Main gateway',
                'mode' => 'demo',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/dashboard/mikrotik');

        $this->usingTenantDatabase($application->database_name);

        $this->assertDatabaseHas('mikrotiks', [
            'name' => 'Core Router',
            'host' => '192.168.88.1',
            'port' => 8728,
            'description' => 'Main gateway',
        ], 'tenant');

        $stored = Mikrotik::on('tenant')->where('name', 'Core Router')->firstOrFail();

        $this->assertNotSame('secret', $stored->getAttributes()['password']);
        $this->assertSame('secret', $stored->password);
    }

    private function usingTenantDatabase(string $databaseName): void
    {
        Config::set('database.connections.tenant', array_replace(Config::get('database.connections.mysql'), [
            'database' => $databaseName,
        ]));
        DB::purge('tenant');
        DB::reconnect('tenant');
    }

}
