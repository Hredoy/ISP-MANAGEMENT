<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Surgical per-tenant cache keys (Sprint 1 · Day 4 · Performance spec). Keys are plain logical
 * names, not manually prefixed with a tenant ID - stancl/tenancy's CacheTenancyBootstrapper
 * (enabled in config/tenancy.php) already tags every Cache:: call with the current tenant via
 * Container::extend(), so two tenants calling Cache::remember('dashboard', ...) land in
 * different cache entries without this class needing to know the tenant ID itself. That
 * tagging only works correctly because SetTenantDatabase runs before the cache is first
 * resolved in a request (see bootstrap/app.php's middleware prepend) - if that ordering ever
 * regresses, these keys would silently leak across tenants, so don't move that middleware.
 *
 * "dashboard", "clients", "devices", and "ai_answer:{hash}" (see App\Services\Chat\ChatbotService)
 * are wired to real callers. "reports:monthly" is defined here per spec but has nothing to cache
 * yet - Advanced Reports is still a TODO roadmap task.
 */
class TenantCache
{
    public const DASHBOARD_KEY = 'dashboard';

    public const DASHBOARD_TTL = 300; // 5 min

    public const CLIENTS_KEY = 'clients';

    public const CLIENTS_TTL = 120; // 2 min

    public const DEVICES_KEY = 'devices';

    public const DEVICES_TTL = 30; // 30 sec

    public const REPORTS_MONTHLY_KEY = 'reports:monthly';

    public const REPORTS_MONTHLY_TTL = 3600; // 1 hr

    public static function rememberDashboard(callable $callback): mixed
    {
        return Cache::remember(self::DASHBOARD_KEY, self::DASHBOARD_TTL, $callback);
    }

    public static function rememberClients(callable $callback): mixed
    {
        return Cache::remember(self::CLIENTS_KEY, self::CLIENTS_TTL, $callback);
    }

    public static function rememberDevices(callable $callback): mixed
    {
        return Cache::remember(self::DEVICES_KEY, self::DEVICES_TTL, $callback);
    }

    public static function aiAnswerKey(string $hash): string
    {
        return "ai_answer:{$hash}";
    }

    /** Payment received: only the dashboard (revenue/recent-payments) and clients widgets show payment data. */
    public static function forgetDashboardAndClients(): void
    {
        Cache::forget(self::DASHBOARD_KEY);
        Cache::forget(self::CLIENTS_KEY);
    }

    /** Device change: only the devices widget is affected. */
    public static function forgetDevices(): void
    {
        Cache::forget(self::DEVICES_KEY);
    }
}
