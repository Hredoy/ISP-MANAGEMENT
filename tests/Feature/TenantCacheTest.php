<?php

namespace Tests\Feature;

use App\Support\TenantCache;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Verifies TenantCache's remember/forget wiring in isolation. Doesn't exercise this through a
 * full HTTP round trip: Laravel's testing harness re-runs SetTenantDatabase's
 * tenancy()->initialize()/end() cycle on every request, and stancl/tenancy's
 * CacheTenancyBootstrapper rebuilds the cache repository on each initialize() - so two separate
 * `$this->get()` calls in a test don't share the same array-store cache the way two requests
 * against a real, persistent Redis would. That's a testing-harness limitation, not a bug in this
 * class; see CLAUDE.md's existing "Known gap" note on the array driver vs. real Redis for the
 * session/cache timing issue this project already documents.
 */
class TenantCacheTest extends TestCase
{
    public function test_remember_dashboard_caches_the_closure_result_until_forgotten(): void
    {
        Cache::flush();

        $first = TenantCache::rememberDashboard(fn () => ['count' => 1]);
        $second = TenantCache::rememberDashboard(fn () => ['count' => 2]);

        $this->assertSame(['count' => 1], $first);
        $this->assertSame(['count' => 1], $second, 'Second call should be served from cache, not the new closure.');

        TenantCache::forgetDashboardAndClients();

        $third = TenantCache::rememberDashboard(fn () => ['count' => 2]);
        $this->assertSame(['count' => 2], $third, 'After forgetting, the cache should recompute.');
    }

    public function test_remember_clients_shares_invalidation_with_dashboard(): void
    {
        Cache::flush();

        TenantCache::rememberDashboard(fn () => ['count' => 1]);
        TenantCache::rememberClients(fn () => ['count' => 1]);

        TenantCache::forgetDashboardAndClients();

        $dashboard = TenantCache::rememberDashboard(fn () => ['count' => 99]);
        $clients = TenantCache::rememberClients(fn () => ['count' => 99]);

        $this->assertSame(['count' => 99], $dashboard);
        $this->assertSame(['count' => 99], $clients);
    }

    public function test_remember_devices_is_invalidated_independently_of_dashboard_and_clients(): void
    {
        Cache::flush();

        TenantCache::rememberDashboard(fn () => ['count' => 1]);
        TenantCache::rememberDevices(fn () => ['count' => 1]);

        TenantCache::forgetDevices();

        // Devices was forgotten, so it recomputes...
        $devices = TenantCache::rememberDevices(fn () => ['count' => 2]);
        $this->assertSame(['count' => 2], $devices);

        // ...but dashboard was untouched by forgetDevices(), so it's still cached.
        $dashboard = TenantCache::rememberDashboard(fn () => ['count' => 99]);
        $this->assertSame(['count' => 1], $dashboard);
    }

    public function test_ai_answer_key_is_namespaced_by_hash(): void
    {
        $this->assertSame('ai_answer:abc123', TenantCache::aiAnswerKey('abc123'));
    }
}
