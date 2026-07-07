<?php

namespace Tests\Feature;

use App\Models\Mikrotik;
use App\Models\Tenant;
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
 * Same identical assertions run against both MikroTikServiceInterface implementations, mirroring
 * MikroTikServiceContractTest's pattern - proves the new Queue Tree / Firewall / bulk-session-kill
 * methods behave the same way whether backed by MockMikroTikService or RealMikroTikService (wired
 * to a FakeRouterOsClient, never a real socket).
 */
class MikrotikQueueTreeFirewallContractTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDatabase;

    private ?Tenant $tenant = null;

    protected function tearDown(): void
    {
        $this->dropTestTenantDatabases();

        parent::tearDown();
    }

    public function test_queue_tree_and_firewall_lifecycle_against_mock_service(): void
    {
        $router = $this->setupTenant('advanced-mock');

        tenancy()->initialize($this->tenant);

        $this->assertQueueTreeAndFirewallLifecycle(new MockMikroTikService($router));
    }

    public function test_queue_tree_and_firewall_lifecycle_against_real_service_with_fake_client(): void
    {
        $router = $this->setupTenant('advanced-real');

        tenancy()->initialize($this->tenant);

        $this->assertQueueTreeAndFirewallLifecycle(new RealMikroTikService($router, new FakeRouterOsClient));
    }

    private function assertQueueTreeAndFirewallLifecycle(MikroTikServiceInterface $service): void
    {
        // --- Queue Tree ---
        $service->addQueueTree(['name' => 'parent-node', 'parent' => 'global', 'max_limit' => '100M/100M']);
        $service->addQueueTree(['name' => 'child-node', 'parent' => 'parent-node', 'max_limit' => '10M/10M', 'priority' => 4]);

        $tree = collect($service->getQueueTree());
        $this->assertTrue($tree->contains('name', 'parent-node'));
        $child = $tree->firstWhere('name', 'child-node');
        $this->assertSame('parent-node', $child['parent']);
        $this->assertSame('4', (string) $child['priority']);

        $service->updateQueueTree('child-node', ['max_limit' => '20M/20M']);
        $updated = collect($service->getQueueTree())->firstWhere('name', 'child-node');
        $this->assertSame('20M/20M', $updated['max-limit']);

        $service->deleteQueueTree('child-node');
        $this->assertFalse(collect($service->getQueueTree())->contains('name', 'child-node'));
        $this->assertFalse($service->deleteQueueTree('child-node'), 'deleting an already-gone node is a no-op, not an error');

        // --- Firewall rules ---
        // Deliberately re-fetch each rule's `.id` via getFirewallRules() rather than trusting
        // add*()'s return value: on a real router, RouterOS's `/add` reply only ever contains the
        // new `{ret=*N}`, not the full row - so relying on richer add() output would pass against
        // the fakes here but silently break against real hardware.
        $service->addFirewallRule(['chain' => 'forward', 'action' => 'accept', 'comment' => 'rule-one']);
        $service->addFirewallRule(['chain' => 'forward', 'action' => 'drop', 'comment' => 'rule-two']);

        // `.id` is a literal key containing a dot - Collection::firstWhere()/contains() use
        // dot-notation traversal via data_get() internally, which mis-parses it. Plain array
        // search sidesteps that entirely.
        $findByComment = fn (string $comment) => collect($service->getFirewallRules())->first(fn (array $r) => $r['comment'] === $comment);

        $rules = $service->getFirewallRules();
        $this->assertCount(2, $rules);
        $this->assertSame('rule-one', $rules[0]['comment']);
        $this->assertSame('rule-two', $rules[1]['comment']);

        $firstId = $findByComment('rule-one')['.id'];
        $secondId = $findByComment('rule-two')['.id'];

        $this->assertTrue($service->updateFirewallRule($secondId, ['action' => 'reject', 'disabled' => true]));
        $updatedRule = $findByComment('rule-two');
        $this->assertSame('reject', $updatedRule['action']);
        $this->assertContains($updatedRule['disabled'], ['true', 'yes']);

        // Swap order: rule-two should now come first.
        $this->assertTrue($service->moveFirewallRule($secondId, 'up'));
        $reordered = $service->getFirewallRules();
        $this->assertSame('rule-two', $reordered[0]['comment']);
        $this->assertSame('rule-one', $reordered[1]['comment']);

        // Already at the top - nothing to swap with.
        $this->assertFalse($service->moveFirewallRule($secondId, 'up'));

        $this->assertTrue($service->deleteFirewallRule($firstId));
        $this->assertCount(1, $service->getFirewallRules());
        $this->assertFalse($service->updateFirewallRule('*9999', ['action' => 'accept']));

        // --- Bulk PPPoE session kill ---
        $service->addPPPoEUser(['name' => 'bulkuser1', 'password' => 'secret123']);
        $service->addPPPoEUser(['name' => 'bulkuser2', 'password' => 'secret123']);

        // Neither user has an active session yet in either implementation's fresh state, so a
        // bulk kill against them (plus a nonexistent user) should report 0 killed without error.
        $killed = $service->killActiveSessions(['bulkuser1', 'bulkuser2', 'no-such-user']);
        $this->assertSame(0, $killed);
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
        $this->tenant = $application->tenant;
        $this->reconnectTenant();

        return Mikrotik::on('tenant')->create([
            'name' => 'Advanced Router', 'host' => '10.0.0.2', 'port' => 8728,
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
}
