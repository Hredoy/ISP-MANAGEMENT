<?php

namespace Tests\Feature;

use App\Models\Mikrotik;
use App\Models\MikrotikActivityLog;
use App\Models\TenantApplication;
use App\Services\MikroTik\MockMikroTikService;
use App\Services\TenantProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * MikrotikActivityLog rows are written either by LogMikrotikActivity (subscribed to the 7 domain
 * events in AppServiceProvider::boot()) or directly by the services for operations with no
 * dedicated event (reboot, queue CRUD, enable/disable). This test exercises both paths via
 * MockMikroTikService, since it needs no real/fake router connection.
 */
class MikrotikActivityLoggingTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDatabase;

    protected function tearDown(): void
    {
        $this->dropTestTenantDatabases();

        parent::tearDown();
    }

    public function test_pppoe_lifecycle_writes_activity_log_rows_via_events(): void
    {
        $router = $this->setupTenant('activity-events');
        $service = new MockMikroTikService($router);

        $service->addPPPoEUser(['name' => 'logtest', 'password' => 'secret123']);
        $service->updatePPPoEUser('logtest', ['comment' => 'Renamed']);
        $service->removePPPoEUser('logtest');

        $eventTypes = MikrotikActivityLog::on('tenant')->where('mikrotik_id', $router->id)->pluck('event_type');

        $this->assertContains('ppp_user.created', $eventTypes->all());
        $this->assertContains('ppp_user.updated', $eventTypes->all());
        $this->assertContains('ppp_user.deleted', $eventTypes->all());
    }

    public function test_operations_without_dedicated_events_are_logged_directly(): void
    {
        $router = $this->setupTenant('activity-direct');
        $service = new MockMikroTikService($router);

        $service->addPPPoEUser(['name' => 'directlog', 'password' => 'secret123']);
        $service->disableUser('directlog');
        $service->enableUser('directlog');
        $service->addSimpleQueue('directlog-queue', '10.10.10.9', '5M/5M');
        $service->updateQueue('directlog-queue', ['max_limit' => '10M/10M']);
        $service->deleteQueue('directlog-queue');
        $service->reboot();

        $eventTypes = MikrotikActivityLog::on('tenant')->where('mikrotik_id', $router->id)->pluck('event_type');

        $this->assertContains('ppp_user.disabled', $eventTypes->all());
        $this->assertContains('ppp_user.enabled', $eventTypes->all());
        $this->assertContains('queue.upserted', $eventTypes->all());
        $this->assertContains('queue.updated', $eventTypes->all());
        $this->assertContains('queue.deleted', $eventTypes->all());
        $this->assertContains('router.rebooted', $eventTypes->all());
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
            'name' => 'Activity Router', 'host' => '10.0.0.1', 'port' => 8728,
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
