<?php

namespace Tests\Feature;

use App\Models\Mikrotik;
use App\Models\MikrotikActivityLog;
use App\Models\TenantApplication;
use App\Services\MikroTik\Contracts\MikroTikServiceInterface;
use App\Services\MikroTik\MockMikroTikService;
use App\Services\MikroTik\RealMikroTikService;
use App\Services\TenantProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\Support\FakeRouterOsClient;
use Tests\TestCase;

/**
 * Runs the identical PPPoE + queue lifecycle assertions against both MikroTikServiceInterface
 * implementations - MockMikroTikService (DB-backed) and RealMikroTikService wired to a
 * FakeRouterOsClient (in-process RouterOS wire-format simulator, no socket, no real hardware) -
 * proving callers genuinely can't tell the two apart, per the interface's own contract.
 */
class MikroTikServiceContractTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDatabase;

    protected function tearDown(): void
    {
        $this->dropTestTenantDatabases();

        parent::tearDown();
    }

    public function test_pppoe_and_queue_lifecycle_against_mock_service(): void
    {
        $router = $this->setupTenant('contract-mock');

        $this->assertPppoeAndQueueLifecycle(new MockMikroTikService($router), $router);
    }

    public function test_pppoe_and_queue_lifecycle_against_real_service_with_fake_client(): void
    {
        $router = $this->setupTenant('contract-real');

        $this->assertPppoeAndQueueLifecycle(new RealMikroTikService($router, new FakeRouterOsClient), $router);
    }

    private function assertPppoeAndQueueLifecycle(MikroTikServiceInterface $service, Mikrotik $mikrotik): void
    {
        $service->addPPPoEUser(['name' => 'contractuser', 'password' => 'secret123', 'profile' => 'default']);
        $this->assertTrue(collect($service->getPPPoEUsers())->contains('name', 'contractuser'));

        $service->updatePPPoEUser('contractuser', ['comment' => 'Updated Name']);
        $updated = collect($service->getPPPoEUsers())->firstWhere('name', 'contractuser');
        $this->assertSame('Updated Name', $updated['comment']);

        $service->disableUser('contractuser');
        $disabled = collect($service->getPPPoEUsers())->firstWhere('name', 'contractuser');
        $this->assertContains($disabled['disabled'], ['true', 'yes']);

        $service->enableUser('contractuser');
        $enabled = collect($service->getPPPoEUsers())->firstWhere('name', 'contractuser');
        $this->assertContains($enabled['disabled'], ['false', 'no']);

        $service->addSimpleQueue('contractuser-queue', '10.10.10.5', '10M/10M');
        $this->assertTrue(collect($service->getQueues())->contains('name', 'contractuser-queue'));

        $service->updateQueue('contractuser-queue', ['max_limit' => '20M/20M']);
        $updatedQueue = collect($service->getQueues())->firstWhere('name', 'contractuser-queue');
        $this->assertSame('20M/20M', $updatedQueue['max-limit']);

        $service->deleteQueue('contractuser-queue');
        $this->assertFalse(collect($service->getQueues())->contains('name', 'contractuser-queue'));

        $service->removePPPoEUser('contractuser');
        $this->assertFalse(collect($service->getPPPoEUsers())->contains('name', 'contractuser'));

        // Both implementations dispatch the same domain events / write the same direct log calls
        // (see LogMikrotikActivity + RealMikroTikService::logActivity()/MockMikroTikService::logActivity()).
        $this->assertGreaterThan(
            0,
            MikrotikActivityLog::on($mikrotik->getConnectionName())->where('mikrotik_id', $mikrotik->id)->count(),
        );
    }

    private function setupTenant(string $slug): Mikrotik
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

        return Mikrotik::on('tenant')->create([
            'name' => 'Contract Router', 'host' => '10.0.0.1', 'port' => 8728,
            'username' => 'admin', 'password' => 'secret',
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

    private function dropTestTenantDatabases(): void
    {
        foreach (DB::select("SHOW DATABASES LIKE 'test\\_tenant\\_%'") as $row) {
            $database = array_values((array) $row)[0];

            DB::statement('DROP DATABASE IF EXISTS `'.str_replace('`', '``', $database).'`');
        }
    }
}
