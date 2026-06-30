<?php

namespace Tests\Feature;

use App\Http\Middleware\SetTenantDatabase;
use App\Models\TenantApplication;
use Database\Seeders\TenantDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use App\Models\Package;
use App\Models\Tenant;
use App\Models\TenantApplication;
use App\Services\TenantProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dropTestTenantDatabases();
    }

    protected function tearDown(): void
    {
        $this->dropTestTenantDatabases();

        parent::tearDown();
    }

    public function test_two_tenants_have_isolated_databases_and_domains(): void
    {
        $this->app['config']->set('tenancy.central_domains', ['localhost']);
        $this->app['config']->set('tenancy.database.prefix', 'test_tenant_');

        $provisioning = app(TenantProvisioningService::class);

        $alphaApplication = TenantApplication::create([
            'organization_name' => 'Alpha ISP',
            'slug' => 'alpha-isp',
            'contact_name' => 'Alpha Admin',
            'email' => 'admin@alpha.test',
            'custom_domain' => 'alpha.example.test',
            'status' => 'pending',
        ]);

        $betaApplication = TenantApplication::create([
            'organization_name' => 'Beta ISP',
            'slug' => 'beta-isp',
            'contact_name' => 'Beta Admin',
            'email' => 'admin@beta.test',
            'status' => 'pending',
        ]);

        $alphaApplication = $provisioning->approve($alphaApplication);
        $betaApplication = $provisioning->approve($betaApplication);

        $this->assertDatabaseHas('domains', [
            'domain' => 'alpha-isp.localhost',
            'tenant_id' => $alphaApplication->tenant_id,
        ]);
        $this->assertDatabaseHas('domains', [
            'domain' => 'alpha.example.test',
            'tenant_id' => $alphaApplication->tenant_id,
        ]);
        $this->assertDatabaseHas('domains', [
            'domain' => 'beta-isp.localhost',
            'tenant_id' => $betaApplication->tenant_id,
        ]);

        tenancy()->initialize(Tenant::findOrFail($alphaApplication->tenant_id));
        Package::create([
            'mikrotik_id' => 1,
            'name' => 'Alpha Only',
            'rate_limit' => '100M/100M',
            'price' => 2500,
        ]);
        $this->assertDatabaseHas('users', ['email' => 'admin@alpha.test']);
        $this->assertDatabaseHas('packages', ['name' => 'Alpha Only']);
        tenancy()->end();

        tenancy()->initialize(Tenant::findOrFail($betaApplication->tenant_id));
        $this->assertDatabaseHas('users', ['email' => 'admin@beta.test']);
        $this->assertDatabaseMissing('users', ['email' => 'admin@alpha.test']);
        $this->assertDatabaseMissing('packages', ['name' => 'Alpha Only']);
        tenancy()->end();
    }

    public function test_duplicate_organization_names_still_get_separate_tenants(): void
    {
        $provisioning = app(TenantProvisioningService::class);

        $firstApplication = TenantApplication::create([
            'organization_name' => 'Same ISP',
            'slug' => 'same-isp-a1b2c3',
            'contact_name' => 'First Admin',
            'email' => 'first@example.test',
            'status' => 'pending',
        ]);

        $secondApplication = TenantApplication::create([
            'organization_name' => 'Same ISP',
            'slug' => 'same-isp-d4e5f6',
            'contact_name' => 'Second Admin',
            'email' => 'second@example.test',
            'status' => 'pending',
        ]);

        $firstApplication = $provisioning->approve($firstApplication);
        $secondApplication = $provisioning->approve($secondApplication);

        $this->assertNotSame($firstApplication->tenant_id, $secondApplication->tenant_id);
        $this->assertNotSame($firstApplication->database_name, $secondApplication->database_name);
    }

    private function dropTestTenantDatabases(): void
    {
        foreach (DB::select("SHOW DATABASES LIKE 'test\\_tenant\\_%'") as $row) {
            $database = array_values((array) $row)[0];

            DB::statement('DROP DATABASE IF EXISTS `'.str_replace('`', '``', $database).'`');
        }
    }
}
