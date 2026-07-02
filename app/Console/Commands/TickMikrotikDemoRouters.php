<?php

namespace App\Console\Commands;

use App\Events\UserConnected;
use App\Events\UserDisconnected;
use App\Models\Mikrotik;
use App\Models\MockMikrotikInterface;
use App\Models\MockMikrotikLog;
use App\Models\MockMikrotikSession;
use App\Models\MockMikrotikSystem;
use App\Models\MockMikrotikUser;
use App\Services\MikroTik\ModeResolver;
use Illuminate\Console\Command;

/**
 * Advances one minute of simulated time for every router effectively in Demo Mode: uptime ticks
 * forward, interface traffic counters grow, a handful of users randomly connect/disconnect, and
 * one router log line is appended - so the Demo Mode dashboard looks like a live router instead
 * of a static fixture. Scheduled via `tenants:run mikrotik:demo:tick` in routes/console.php.
 *
 * All Mock* writes explicitly target the router's own connection (`Mikrotik::on('tenant')` sets
 * this) rather than relying on Eloquent relations or the container's default connection matching
 * the tenant - this app keeps a dedicated 'tenant' connection distinct from the default one (see
 * MikrotikController::query()'s same `tenancy()->initialized` check), so relations built off a
 * fresh related-model instance would otherwise silently hit the wrong database.
 */
class TickMikrotikDemoRouters extends Command
{
    protected $signature = 'mikrotik:demo:tick';

    protected $description = 'Advance one minute of simulated time for every Demo Mode MikroTik router.';

    public function handle(ModeResolver $modeResolver): int
    {
        $query = tenancy()->initialized ? Mikrotik::on('tenant') : Mikrotik::query();

        $routers = $query->get()->filter(
            fn (Mikrotik $router) => $modeResolver->resolve($router) === ModeResolver::MODE_DEMO
        );

        foreach ($routers as $router) {
            $this->tick($router, $router->getConnectionName());
        }

        $this->info("Ticked {$routers->count()} demo router(s).");

        return self::SUCCESS;
    }

    private function tick(Mikrotik $router, ?string $connection): void
    {
        $this->advanceSystemClock($router, $connection);
        $connectedDelta = $this->advanceInterfaceTraffic($router, $connection);
        $sessionChanges = $this->toggleRandomSessions($router, $connection);
        $this->appendLogEntry($router, $connection, $sessionChanges);
    }

    private function advanceSystemClock(Mikrotik $router, ?string $connection): void
    {
        $system = MockMikrotikSystem::on($connection)->firstOrCreate(
            ['mikrotik_id' => $router->id],
            [
                'cpu_load' => random_int(3, 25),
                'free_memory' => 96 * 1024 * 1024,
                'total_memory' => 128 * 1024 * 1024,
                'free_hdd_space' => 14 * 1024 * 1024,
                'total_hdd_space' => 16 * 1024 * 1024,
                'uptime_seconds' => 0,
                'board_name' => 'CHR',
                'version' => '7.15.3 (stable)',
            ],
        );

        $system->update([
            'uptime_seconds' => $system->uptime_seconds + 60,
            'cpu_load' => max(1, min(95, $system->cpu_load + random_int(-8, 8))),
            'free_memory' => max(0, min($system->total_memory, $system->free_memory + random_int(-5_000_000, 5_000_000))),
        ]);
    }

    private function advanceInterfaceTraffic(Mikrotik $router, ?string $connection): int
    {
        $interfaces = MockMikrotikInterface::on($connection)
            ->where('mikrotik_id', $router->id)
            ->where('running', true)
            ->get();

        foreach ($interfaces as $interface) {
            $interface->update([
                'rx_bytes' => $interface->rx_bytes + random_int(0, 20_000_000),
                'tx_bytes' => $interface->tx_bytes + random_int(0, 8_000_000),
                'rx_bps' => random_int(0, 100_000_000),
                'tx_bps' => random_int(0, 50_000_000),
            ]);
        }

        return $interfaces->count();
    }

    /**
     * @return array{connected: int, disconnected: int}
     */
    private function toggleRandomSessions(Mikrotik $router, ?string $connection): array
    {
        $users = MockMikrotikUser::on($connection)->where('mikrotik_id', $router->id)->where('disabled', false)->get();

        if ($users->isEmpty()) {
            return ['connected' => 0, 'disconnected' => 0];
        }

        $activeSessions = MockMikrotikSession::on($connection)->where('mikrotik_id', $router->id)->get()->keyBy('mock_mikrotik_user_id');

        $sampleSize = min($users->count(), max(1, (int) ceil($users->count() * 0.02)));
        $connected = 0;
        $disconnected = 0;

        foreach ($users->random($sampleSize) as $user) {
            $session = $activeSessions->get($user->id);

            if ($session) {
                if (random_int(1, 100) <= 40) {
                    $session->delete();
                    event(new UserDisconnected($router, $user->username));
                    $disconnected++;
                }
            } elseif (random_int(1, 100) <= 40) {
                $address = $user->ip_address ?? sprintf('10.%d.%d.%d', random_int(0, 255), random_int(0, 255), random_int(2, 254));

                MockMikrotikSession::on($connection)->create([
                    'mikrotik_id' => $router->id,
                    'mock_mikrotik_user_id' => $user->id,
                    'username' => $user->username,
                    'address' => $address,
                    'caller_id' => $user->mac_address,
                    'uptime_seconds' => 0,
                ]);
                event(new UserConnected($router, $user->username, $address));
                $connected++;
            }
        }

        return ['connected' => $connected, 'disconnected' => $disconnected];
    }

    /**
     * @param  array{connected: int, disconnected: int}  $sessionChanges
     */
    private function appendLogEntry(Mikrotik $router, ?string $connection, array $sessionChanges): void
    {
        MockMikrotikLog::on($connection)->create([
            'mikrotik_id' => $router->id,
            'topics' => 'system,info',
            'message' => "Heartbeat: +{$sessionChanges['connected']} connected, +{$sessionChanges['disconnected']} disconnected.",
            'logged_at' => now(),
        ]);
    }
}
