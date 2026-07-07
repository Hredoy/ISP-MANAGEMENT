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

/**
 * HTTP-level coverage for the new Queue Tree / Firewall / PPPoE-session routes and controllers -
 * the router is created with mode=demo, so MikroTikServiceFactory resolves MockMikroTikService
 * and no real socket is ever touched (same pattern as MikrotikRouterTest).
 */
class MikrotikAdvancedRoutesTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDatabase;

    protected function tearDown(): void
    {
        $this->dropTestTenantDatabases();

        parent::tearDown();
    }

    public function test_queue_tree_can_be_managed_over_http(): void
    {
        [$user, $mikrotik] = $this->setupTenantWithRouter('queue-http');

        $this->actingAs($user)
            ->post("http://queue-http.localhost/dashboard/mikrotik/{$mikrotik->id}/queue-tree", [
                'name' => 'http-node',
                'parent' => 'global',
                'max_limit' => '5M/5M',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->usingTenantDatabase($this->tenantDatabase);

        $response = $this->actingAs($user)->get("http://queue-http.localhost/dashboard/mikrotik/{$mikrotik->id}/queue-tree");
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Mikrotik/QueueTree')
            ->where('nodes.0.name', 'http-node'));

        $this->usingTenantDatabase($this->tenantDatabase);

        $this->actingAs($user)
            ->delete("http://queue-http.localhost/dashboard/mikrotik/{$mikrotik->id}/queue-tree/http-node")
            ->assertRedirect();
    }

    public function test_firewall_rule_can_be_managed_over_http(): void
    {
        [$user, $mikrotik] = $this->setupTenantWithRouter('firewall-http');

        $this->actingAs($user)
            ->post("http://firewall-http.localhost/dashboard/mikrotik/{$mikrotik->id}/firewall", [
                'chain' => 'forward',
                'action' => 'drop',
                'comment' => 'block-test',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->usingTenantDatabase($this->tenantDatabase);

        $response = $this->actingAs($user)->get("http://firewall-http.localhost/dashboard/mikrotik/{$mikrotik->id}/firewall");
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Mikrotik/Firewall')
            ->where('rules.0.comment', 'block-test'));
    }

    public function test_pppoe_sessions_index_renders_and_bulk_kill_endpoint_accepts_usernames(): void
    {
        [$user, $mikrotik] = $this->setupTenantWithRouter('pppoe-http');

        $response = $this->actingAs($user)->get("http://pppoe-http.localhost/dashboard/mikrotik/{$mikrotik->id}/pppoe-sessions");
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Mikrotik/PppoeSessions'));

        $this->usingTenantDatabase($this->tenantDatabase);

        // No active sessions exist for these usernames yet - the endpoint should still succeed
        // (0 killed) rather than error, matching killActiveSessions()'s no-op-on-miss contract.
        $this->actingAs($user)
            ->post("http://pppoe-http.localhost/dashboard/mikrotik/{$mikrotik->id}/pppoe-sessions/bulk-kill", [
                'usernames' => ['ghost-user'],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();
    }

    private function setupTenantWithRouter(string $slug): array
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
        $this->usingTenantDatabase($this->tenantDatabase);

        $user = new User([
            'name' => 'Admin',
            'email' => "admin-{$slug}@example.test",
            'password' => Hash::make('password'),
        ]);
        $user->setConnection('tenant');
        $user->save();

        $mikrotik = Mikrotik::on('tenant')->create([
            'name' => 'Advanced Router', 'host' => '192.168.88.1', 'port' => 8728,
            'username' => 'admin', 'password' => 'secret', 'mode' => 'demo',
        ]);

        return [$user, $mikrotik];
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
