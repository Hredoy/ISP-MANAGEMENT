<?php

namespace Tests\Feature;

use App\Models\TenantApplication;
use App\Models\User;
use Database\Seeders\TenantDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TenantDatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dropTenantDatabase();
        $this->createIsolatedDatabase('test_tenant_seeder');

        Config::set('database.connections.tenant', array_replace(Config::get('database.connections.mysql'), [
            'database' => 'test_tenant_seeder',
        ]));
        DB::purge('tenant');

        Artisan::call('migrate', [
            '--database' => 'tenant',
            '--path' => 'database/migrations/tenant',
            '--force' => true,
        ]);
    }

    protected function tearDown(): void
    {
        $this->dropTenantDatabase();

        parent::tearDown();
    }

    public function test_it_creates_a_default_admin_user_on_the_tenant_connection(): void
    {
        $application = TenantApplication::create([
            'organization_name' => 'Acme ISP',
            'slug' => 'acme-isp',
            'contact_name' => 'Jane Doe',
            'email' => 'jane@acme-isp.test',
            'status' => 'pending',
        ]);

        (new TenantDatabaseSeeder)->run($application);

        $this->assertDatabaseHas('users', [
            'email' => 'jane@acme-isp.test',
            'name' => 'Jane Doe',
        ], 'tenant');

        // The default (landlord) connection must remain untouched.
        $this->assertDatabaseMissing('users', [
            'email' => 'jane@acme-isp.test',
        ]);
    }

    public function test_it_is_idempotent_for_the_same_tenant_email(): void
    {
        $application = TenantApplication::create([
            'organization_name' => 'Acme ISP',
            'slug' => 'acme-isp',
            'contact_name' => 'Jane Doe',
            'email' => 'jane@acme-isp.test',
            'status' => 'pending',
        ]);

        (new TenantDatabaseSeeder)->run($application);
        (new TenantDatabaseSeeder)->run($application);

        $this->assertSame(1, User::on('tenant')->where('email', 'jane@acme-isp.test')->count());
    }

    private function dropTenantDatabase(): void
    {
        $this->dropIsolatedDatabase('test_tenant_seeder');
    }
}
