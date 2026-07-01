<?php

namespace Tests\Feature;

use App\Http\Middleware\SetTenantDatabase;
use App\Models\Package;
use App\Models\TenantApplication;
use App\Services\TenantProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        $this->dropTestTenantDatabases();

        parent::tearDown();
    }

    public function test_two_tenants_have_isolated_databases(): void
    {
        $this->dropTestTenantDatabases();
        $provisioning = app(TenantProvisioningService::class);

        $alpha = $provisioning->approve(TenantApplication::create([
            'organization_name' => 'Alpha ISP',
            'slug' => 'alpha-isp',
            'contact_name' => 'Alpha Admin',
            'email' => 'admin@alpha.test',
            'phone' => '01700000001',
            'status' => 'pending',
        ]), 'secret-alpha');

        $beta = $provisioning->approve(TenantApplication::create([
            'organization_name' => 'Beta ISP',
            'slug' => 'beta-isp',
            'contact_name' => 'Beta Admin',
            'email' => 'admin@beta.test',
            'phone' => '01700000002',
            'status' => 'pending',
        ]), 'secret-beta');

        $this->usingTenantDatabase($alpha->database_name);
        Package::on('tenant')->create([
            'mikrotik_id' => 1,
            'name' => 'Alpha Only',
            'rate_limit' => '100M/100M',
            'price' => 2500,
        ]);

        $this->usingTenantDatabase($beta->database_name);
        $this->assertDatabaseHas('users', ['email' => 'admin@beta.test'], 'tenant');
        $this->assertDatabaseMissing('users', ['email' => 'admin@alpha.test'], 'tenant');
        $this->assertDatabaseMissing('packages', ['name' => 'Alpha Only'], 'tenant');
    }

    public function test_subdomain_host_switches_to_tenant_database(): void
    {
        $this->dropTestTenantDatabases();
        $application = app(TenantProvisioningService::class)->approve(TenantApplication::create([
            'organization_name' => 'Gamma ISP',
            'slug' => 'gamma-isp',
            'contact_name' => 'Gamma Admin',
            'email' => 'admin@gamma.test',
            'phone' => '01700000003',
            'status' => 'pending',
        ]), 'secret-gamma');

        $request = Request::create('http://gamma-isp.localhost/dashboard');
        $middleware = new SetTenantDatabase;

        $middleware->handle($request, function () use ($application) {
            $this->assertTrue(tenancy()->initialized);
            $this->assertSame($application->tenant_id, tenancy()->tenant->getTenantKey());

            return response('ok');
        });
    }

    private function usingTenantDatabase(string $databaseName): void
    {
        Config::set('database.connections.tenant.database', $databaseName);
        DB::purge('tenant');
        DB::reconnect('tenant');
    }

}
