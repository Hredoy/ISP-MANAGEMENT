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
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private array $tenantDatabasePaths = [];

    protected function tearDown(): void
    {
        parent::tearDown();

        DB::purge('tenant');

        foreach ($this->tenantDatabasePaths as $path) {
            @unlink($path);
        }
    }

    /**
     * Provision a real tenant database (file-based sqlite, standing in for a
     * tenant MySQL database) the same way TenantProvisioningService does:
     * point the 'tenant' connection at it, migrate, then seed.
     */
    private function provisionTenantDatabase(string $slug, string $contactName, string $email): TenantApplication
    {
        $path = tempnam(sys_get_temp_dir(), 'tenant_');
        $this->tenantDatabasePaths[] = $path;

        Config::set('database.connections.tenant', [
            'driver' => 'sqlite',
            'database' => $path,
            'prefix' => '',
        ]);
        DB::purge('tenant');

        Artisan::call('migrate', [
            '--database' => 'tenant',
            '--force' => true,
        ]);

        $application = TenantApplication::create([
            'organization_name' => $slug,
            'slug' => $slug,
            'contact_name' => $contactName,
            'email' => $email,
            'status' => 'approved',
            'database_name' => $path,
            'subdomain' => $slug.'.localhost',
            'approved_at' => now(),
        ]);

        (new TenantDatabaseSeeder)->run($application);

        return $application;
    }

    public function test_tenant_databases_are_isolated_and_subdomain_routing_is_enforced(): void
    {
        $landlordDefault = config('database.default');

        $this->provisionTenantDatabase('tenant1', 'Alice', 'alice@tenant1.test');
        $this->provisionTenantDatabase('tenant2', 'Bob', 'bob@tenant2.test');

        $middleware = new SetTenantDatabase;

        // Each call below simulates a separate web request. In production
        // every request boots a fresh container (so 'database.default'
        // always starts from config/database.php); replicate that here by
        // resetting it before each simulated request.
        Config::set('database.default', $landlordDefault);
        $middleware->handle(
            Request::create('http://tenant1.localhost/dashboard'),
            fn () => response('ok')
        );
        $this->assertSame(
            ['alice@tenant1.test'],
            DB::connection('tenant')->table('users')->pluck('email')->all(),
            'tenant1 request must only see tenant1 data'
        );

        Config::set('database.default', $landlordDefault);
        $middleware->handle(
            Request::create('http://tenant2.localhost/dashboard'),
            fn () => response('ok')
        );
        $this->assertSame(
            ['bob@tenant2.test'],
            DB::connection('tenant')->table('users')->pluck('email')->all(),
            'tenant2 request must only see tenant2 data, never tenant1 data'
        );

        // Landlord/main domain requests must bypass tenant switching entirely.
        Config::set('database.default', $landlordDefault);
        $middleware->handle(
            Request::create('http://localhost/dashboard'),
            fn () => response('ok')
        );
        $this->assertSame($landlordDefault, config('database.default'));
        $this->assertDatabaseMissing('users', ['email' => 'alice@tenant1.test']);
        $this->assertDatabaseMissing('users', ['email' => 'bob@tenant2.test']);

        // A subdomain for a pending (not yet approved) tenant must 404, not
        // silently fall through to some other tenant's database.
        TenantApplication::create([
            'organization_name' => 'tenant3',
            'slug' => 'tenant3',
            'contact_name' => 'Carl',
            'email' => 'carl@tenant3.test',
            'status' => 'pending',
        ]);

        Config::set('database.default', $landlordDefault);
        $this->expectException(NotFoundHttpException::class);

        $middleware->handle(
            Request::create('http://tenant3.localhost/dashboard'),
            fn () => response('ok')
        );
    }
}
