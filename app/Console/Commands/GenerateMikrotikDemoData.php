<?php

namespace App\Console\Commands;

use App\Models\Mikrotik;
use App\Models\MockMikrotikInterface;
use App\Models\MockMikrotikProfile;
use App\Models\MockMikrotikQueue;
use App\Models\MockMikrotikSession;
use App\Models\MockMikrotikSystem;
use App\Models\MockMikrotikUser;
use App\Services\MikroTik\ModeResolver;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Seeds realistic-looking demo data for one or more routers in Demo Mode, so the admin panel
 * dashboard has something to show without ever touching a real RouterOS device.
 *
 * Intended to run inside a tenant context - either via `tenants:run mikrotik:demo:generate` (see
 * routes/console.php's scheduled `mikrotik:demo:tick`) or directly against the central DB in a
 * single-tenant setup. Every write explicitly targets the router's own connection (matching
 * MikrotikController::query()'s `tenancy()->initialized` check) rather than assuming the default
 * connection is already the tenant one.
 */
class GenerateMikrotikDemoData extends Command
{
    protected $signature = 'mikrotik:demo:generate
        {mikrotik? : Router ID to seed. Defaults to every router effectively in Demo Mode}
        {--users=500 : Number of PPPoE users to generate}
        {--sessions=100 : Number of those users to give an active session}
        {--profiles=20 : Number of PPP profiles to generate}
        {--interfaces=8 : Number of interfaces to generate}
        {--queues=50 : Number of bandwidth queues to generate}';

    protected $description = 'Generate demo data (PPPoE users, sessions, profiles, interfaces, queues) for MikroTik Demo Mode routers.';

    public function handle(ModeResolver $modeResolver): int
    {
        $routers = $this->resolveTargetRouters($modeResolver);

        if ($routers->isEmpty()) {
            $this->warn('No routers currently in Demo Mode to seed. Set a router (or the global setting) to Demo first.');

            return self::SUCCESS;
        }

        $userCount = max(0, (int) $this->option('users'));
        $sessionCount = min($userCount, max(0, (int) $this->option('sessions')));
        $profileCount = max(0, (int) $this->option('profiles'));
        $interfaceCount = max(0, (int) $this->option('interfaces'));
        $queueCount = max(0, (int) $this->option('queues'));

        foreach ($routers as $router) {
            $connection = $router->getConnectionName();

            $connection
                ? $router->getConnection()->transaction(fn () => $this->seedRouter($router, $connection, $userCount, $sessionCount, $profileCount, $interfaceCount, $queueCount))
                : $this->seedRouter($router, $connection, $userCount, $sessionCount, $profileCount, $interfaceCount, $queueCount);

            $this->info("Seeded router #{$router->id} ({$router->name}): {$userCount} users, {$sessionCount} sessions, {$profileCount} profiles, {$interfaceCount} interfaces, {$queueCount} queues.");
        }

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, Mikrotik>
     */
    private function resolveTargetRouters(ModeResolver $modeResolver): Collection
    {
        $query = tenancy()->initialized ? Mikrotik::on('tenant') : Mikrotik::query();

        if ($id = $this->argument('mikrotik')) {
            $router = (clone $query)->find($id);

            return $router ? collect([$router]) : collect();
        }

        return $query->get()->filter(
            fn (Mikrotik $router) => $modeResolver->resolve($router) === ModeResolver::MODE_DEMO
        )->values();
    }

    private function seedRouter(Mikrotik $router, ?string $connection, int $userCount, int $sessionCount, int $profileCount, int $interfaceCount, int $queueCount): void
    {
        MockMikrotikSystem::on($connection)->updateOrCreate(
            ['mikrotik_id' => $router->id],
            [
                'cpu_load' => random_int(3, 30),
                'free_memory' => 96 * 1024 * 1024,
                'total_memory' => 128 * 1024 * 1024,
                'free_hdd_space' => 14 * 1024 * 1024,
                'total_hdd_space' => 16 * 1024 * 1024,
                'uptime_seconds' => random_int(3600, 86400 * 30),
                'board_name' => 'CHR',
                'version' => '7.15.3 (stable)',
            ],
        );

        $defaultProfile = MockMikrotikProfile::on($connection)->firstOrCreate(
            ['mikrotik_id' => $router->id, 'name' => 'default'],
            ['rate_limit' => '5M/5M', 'is_default' => true],
        );

        $profiles = $this->createMany(MockMikrotikProfile::factory(), max(0, $profileCount - 1), $connection, ['mikrotik_id' => $router->id])
            ->push($defaultProfile)
            ->pluck('name');

        $users = $this->createMany(MockMikrotikUser::factory(), $userCount, $connection, ['mikrotik_id' => $router->id]);

        $users->each(function (MockMikrotikUser $user) use ($profiles) {
            $user->update(['profile' => $profiles->random()]);
        });

        // Pick distinct users (a real router only ever has one active session per secret) so
        // MockMikrotikUser::session() (a hasOne) stays meaningful. mock_mikrotik_user_id is a
        // required FK, so each session is built (not just made+saved-then-patched) with its
        // owning user already attached.
        $sessionUsers = $sessionCount > 0 ? $users->random(min($sessionCount, $users->count())) : collect();

        MockMikrotikSession::factory()
            ->count($sessionUsers->count())
            ->sequence(...$sessionUsers->values()->map(fn (MockMikrotikUser $user) => [
                'mikrotik_id' => $router->id,
                'mock_mikrotik_user_id' => $user->id,
                'username' => $user->username,
            ])->all())
            ->make()
            ->each(function (MockMikrotikSession $session) use ($connection) {
                $session->setConnection($connection);
                $session->save();
            });

        $this->createMany(MockMikrotikInterface::factory(), $interfaceCount, $connection, ['mikrotik_id' => $router->id]);
        $this->createMany(MockMikrotikQueue::factory(), $queueCount, $connection, ['mikrotik_id' => $router->id]);
    }

    /**
     * Builds `$count` factory instances against `$connection` explicitly, rather than relying on
     * the container's default database connection matching the tenant.
     *
     * @return Collection<int, Model>
     */
    private function createMany(Factory $factory, int $count, ?string $connection, array $attributes): Collection
    {
        return $factory->count($count)->make($attributes)->each(function (Model $model) use ($connection) {
            $model->setConnection($connection);
            $model->save();
        });
    }
}
