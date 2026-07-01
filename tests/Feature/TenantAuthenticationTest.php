<?php

namespace Tests\Feature;

use App\Models\TenantApplication;
use App\Models\User;
use App\Services\TenantProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TenantAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        $this->dropTestTenantDatabases();

        parent::tearDown();
    }

    public function test_tenant_admin_can_login_and_stay_authenticated_on_next_request(): void
    {
        $this->dropTestTenantDatabases();

        $application = app(TenantProvisioningService::class)->approve(TenantApplication::create([
            'organization_name' => 'Login ISP',
            'slug' => 'login-isp',
            'contact_name' => 'Login Admin',
            'email' => 'admin@login.test',
            'phone' => '01700000005',
            'status' => 'pending',
        ]), 'secret-login');

        Config::set('database.connections.tenant', array_replace(Config::get('database.connections.mysql'), [
            'database' => $application->database_name,
        ]));
        DB::purge('tenant');
        DB::reconnect('tenant');

        $user = new User([
            'name' => 'Login Admin',
            'email' => 'login-admin@example.test',
            'password' => Hash::make('password'),
        ]);
        $user->setConnection('tenant');
        $user->save();

        $login = $this->post('http://login-isp.localhost/login', [
            'email' => 'login-admin@example.test',
            'password' => 'password',
        ]);

        $login->assertRedirect('http://login-isp.localhost/dashboard');

        // Fresh, separate request simulating the browser's next page load.
        $dashboard = $this->get('http://login-isp.localhost/dashboard');

        $dashboard->assertOk();
    }

    private function dropTestTenantDatabases(): void
    {
        foreach (DB::select("SHOW DATABASES LIKE 'test\\_tenant\\_%'") as $row) {
            $database = array_values((array) $row)[0];

            DB::statement('DROP DATABASE IF EXISTS `'.str_replace('`', '``', $database).'`');
        }
    }
}
