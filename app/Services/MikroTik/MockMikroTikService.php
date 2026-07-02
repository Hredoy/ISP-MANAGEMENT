<?php

namespace App\Services\MikroTik;

use App\Events\PppUserCreated;
use App\Events\PppUserDeleted;
use App\Events\PppUserUpdated;
use App\Events\UserDisconnected;
use App\Models\Client as ISPClient;
use App\Models\Mikrotik;
use App\Models\MikrotikActivityLog;
use App\Models\MockMikrotikInterface;
use App\Models\MockMikrotikProfile;
use App\Models\MockMikrotikQueue;
use App\Models\MockMikrotikSession;
use App\Models\MockMikrotikSystem;
use App\Models\MockMikrotikUser;
use App\Services\MikroTik\Contracts\MikroTikServiceInterface;
use App\Services\MikroTik\Exceptions\MikroTikCommandException;
use InvalidArgumentException;

/**
 * Simulates a RouterOS device entirely against the mock_mikrotik_* tables for one router - no
 * socket is ever opened. Every method returns data shaped exactly like RealMikroTikService's
 * RouterOS-wire-format output (hyphenated keys, stringy "true"/"false" booleans) so callers can't
 * tell the difference.
 */
class MockMikroTikService implements MikroTikServiceInterface
{
    public function __construct(private readonly Mikrotik $mikrotik)
    {
        //
    }

    public function testConnection(): array
    {
        return [
            'ok' => true,
            'message' => 'CONNECTION_OK (DEMO_MODE)',
            'stats' => $this->getSystemResources(),
        ];
    }

    public function getRouterInfo(): array
    {
        return [
            ...$this->getSystemResources(),
            'name' => $this->mikrotik->name,
        ];
    }

    public function getSystemResources(): array
    {
        $system = $this->system();

        return [
            'uptime' => $this->formatDuration($system->uptime_seconds),
            'version' => $system->version ?? '7.15.3 (stable)',
            'board-name' => $system->board_name ?? 'CHR',
            'cpu-load' => (string) $system->cpu_load,
            'free-memory' => (string) $system->free_memory,
            'total-memory' => (string) $system->total_memory,
            'free-hdd-space' => (string) $system->free_hdd_space,
            'total-hdd-space' => (string) $system->total_hdd_space,
        ];
    }

    public function getInterfaces(): array
    {
        return $this->interfaces()->get()->map(fn (MockMikrotikInterface $i) => [
            '.id' => "*{$i->id}",
            'name' => $i->name,
            'type' => $i->type,
            'running' => $this->bool($i->running),
            'disabled' => $this->bool($i->disabled),
            'mac-address' => $i->mac_address,
            'rx-byte' => (string) $i->rx_bytes,
            'tx-byte' => (string) $i->tx_bytes,
            'rx-bits-per-second' => (string) $i->rx_bps,
            'tx-bits-per-second' => (string) $i->tx_bps,
        ])->all();
    }

    public function getPPPProfiles(): array
    {
        return $this->profiles()->get()->map(fn (MockMikrotikProfile $p) => $this->profileRow($p))->all();
    }

    public function createPPPProfile(array $data): array
    {
        $profile = $this->profiles()->create([
            'mikrotik_id' => $this->mikrotik->id,
            'name' => $data['name'],
            'rate_limit' => $data['rate_limit'] ?? null,
            'local_address' => $data['local_address'] ?? null,
            'remote_address' => $data['remote_address'] ?? null,
        ]);

        return $this->profileRow($profile);
    }

    public function updatePPPProfile(string $name, array $data): bool
    {
        $profile = $this->profiles()->where('name', $name)->first();

        if (! $profile) {
            return false;
        }

        $profile->update([
            'name' => $data['name'] ?? $profile->name,
            'rate_limit' => $data['rate_limit'] ?? $profile->rate_limit,
            'local_address' => $data['local_address'] ?? $profile->local_address,
            'remote_address' => $data['remote_address'] ?? $profile->remote_address,
        ]);

        return true;
    }

    public function deletePPPProfile(string $name): bool
    {
        $profile = $this->profiles()->where('name', $name)->first();

        if (! $profile) {
            return false;
        }

        if ($profile->is_default) {
            throw new MikroTikCommandException('PROTECTED_RESOURCE: Cannot delete system default profiles.');
        }

        $profile->delete();

        return true;
    }

    public function getPPPoEUsers(): array
    {
        return $this->users()->get()->map(fn (MockMikrotikUser $u) => $this->userRow($u))->all();
    }

    public function addPPPoEUser(array $data): array
    {
        $name = $data['name'] ?? $data['pppoe_username'] ?? null;
        $password = $data['password'] ?? $data['pppoe_password'] ?? null;

        if (! $name || ! $password) {
            throw new InvalidArgumentException('PPPoE name and password are required.');
        }

        $user = $this->users()->create([
            'mikrotik_id' => $this->mikrotik->id,
            'username' => $name,
            'password' => $password,
            'profile' => $data['profile'] ?? 'default',
            'service' => $data['service'] ?? 'pppoe',
            'disabled' => false,
            'local_address' => $data['local-address'] ?? null,
            'remote_address' => $data['remote-address'] ?? null,
            'comment' => $data['comment'] ?? null,
        ]);

        event(new PppUserCreated($this->mikrotik, (string) $name));

        return $this->userRow($user);
    }

    public function updatePPPoEUser(string $currentUsername, array $data): bool
    {
        $user = $this->users()->where('username', $currentUsername)->first();

        if (! $user) {
            return false;
        }

        $user->update([
            'username' => $data['name'] ?? $data['pppoe_username'] ?? $user->username,
            'password' => $data['password'] ?? $data['pppoe_password'] ?? $user->password,
            'profile' => $data['profile'] ?? $user->profile,
            'comment' => $data['comment'] ?? $user->comment,
            'disabled' => array_key_exists('disabled', $data) ? (bool) $data['disabled'] : $user->disabled,
        ]);

        event(new PppUserUpdated($this->mikrotik, $currentUsername));

        return true;
    }

    public function removePPPoEUser(string $username): bool
    {
        $user = $this->users()->where('username', $username)->first();

        if (! $user) {
            return false;
        }

        $user->session()->delete();
        $user->delete();

        event(new PppUserDeleted($this->mikrotik, $username));

        return true;
    }

    public function enableUser(string $username): bool
    {
        $user = $this->users()->where('username', $username)->first();

        if (! $user) {
            return false;
        }

        $user->update(['disabled' => false]);
        $this->logActivity('ppp_user.enabled', "PPPoE user '{$username}' enabled.", ['username' => $username]);

        return true;
    }

    public function disableUser(string $username): bool
    {
        $user = $this->users()->where('username', $username)->first();

        if (! $user) {
            return false;
        }

        $user->update(['disabled' => true]);
        $this->disconnectActiveSession($username);
        $this->logActivity('ppp_user.disabled', "PPPoE user '{$username}' disabled.", ['username' => $username]);

        return true;
    }

    public function disconnectActiveSession(string $username): bool
    {
        $session = $this->sessions()->where('username', $username)->first();

        if ($session) {
            $session->delete();
            event(new UserDisconnected($this->mikrotik, $username));
        }

        return true;
    }

    public function getActiveUsers(): array
    {
        return $this->sessions()->get()->map(fn (MockMikrotikSession $s) => [
            '.id' => "*{$s->id}",
            'name' => $s->username,
            'service' => 'pppoe',
            'address' => $s->address,
            'caller-id' => $s->caller_id,
            'uptime' => $this->formatDuration($s->uptime_seconds),
            'session-id' => (string) $s->id,
        ])->all();
    }

    public function getQueues(): array
    {
        return $this->queues()->get()->map(fn (MockMikrotikQueue $q) => $this->queueRow($q))->all();
    }

    public function addSimpleQueue(string $name, string $target, string $maxLimit): array
    {
        $queue = $this->queues()->updateOrCreate(
            ['mikrotik_id' => $this->mikrotik->id, 'name' => $name],
            ['target' => $target, 'max_limit' => $maxLimit, 'disabled' => false],
        );

        $this->logActivity('queue.upserted', "Bandwidth queue '{$name}' set to {$maxLimit} for {$target}.", ['name' => $name, 'target' => $target, 'max_limit' => $maxLimit]);

        return $this->queueRow($queue);
    }

    public function updateQueue(string $name, array $data): bool
    {
        $queue = $this->queues()->where('name', $name)->first();

        if (! $queue) {
            return false;
        }

        $queue->update([
            'target' => $data['target'] ?? $queue->target,
            'max_limit' => $data['max_limit'] ?? $queue->max_limit,
            'disabled' => array_key_exists('disabled', $data) ? (bool) $data['disabled'] : $queue->disabled,
        ]);

        $this->logActivity('queue.updated', "Bandwidth queue '{$name}' updated.", ['name' => $name]);

        return true;
    }

    public function deleteQueue(string $name): bool
    {
        $queue = $this->queues()->where('name', $name)->first();

        if (! $queue) {
            return false;
        }

        $queue->delete();
        $this->logActivity('queue.deleted', "Bandwidth queue '{$name}' deleted.", ['name' => $name]);

        return true;
    }

    public function getHotspotUsers(): array
    {
        // No dedicated mock table for hotspot users - this app provisions PPPoE, not hotspot,
        // so an empty list (matching a router with no hotspot users configured) is accurate.
        return [];
    }

    public function getDhcpLeases(): array
    {
        return $this->sessions()->with('user')->get()->map(fn (MockMikrotikSession $s) => [
            '.id' => "*{$s->id}",
            'address' => $s->address,
            'mac-address' => $s->user?->mac_address,
            'host-name' => $s->username,
            'status' => 'bound',
        ])->all();
    }

    public function reboot(): bool
    {
        $this->system()->update(['uptime_seconds' => 0]);
        $this->logActivity('router.rebooted', "Router '{$this->mikrotik->name}' rebooted (demo mode).");

        return true;
    }

    public function syncRouterData(): array
    {
        $synced = 0;

        foreach ($this->getPPPoEUsers() as $secret) {
            if (empty($secret['name']) || ISPClient::where('pppoe_username', $secret['name'])->exists()) {
                continue;
            }

            ISPClient::create([
                'mikrotik_id' => $this->mikrotik->id,
                'pppoe_username' => $secret['name'],
                'pppoe_password' => $secret['password'] ?? '',
                'package_name' => $secret['profile'] ?? 'default',
                'full_name' => $secret['comment'] ?? $secret['name'],
                'phone_number' => 'N/A',
                'monthly_bill' => 0,
                'full_address' => 'Synced from MikroTik (Demo Mode)',
                'expiry_date' => now()->addMonth()->toDateString(),
                'status' => in_array($secret['disabled'] ?? 'false', ['true', 'yes'], true) ? 'Suspended' : 'Active',
                'additional_notes' => 'AUTO_SYNCED_FROM_MIKROTIK',
            ]);

            $synced++;
        }

        $this->mikrotik->forceFill([
            'last_pppoe_sync_at' => now(),
            'last_sync_at' => now(),
        ])->save();

        return ['synced' => $synced];
    }

    private function system(): MockMikrotikSystem
    {
        return MockMikrotikSystem::firstOrCreate(
            ['mikrotik_id' => $this->mikrotik->id],
            [
                'cpu_load' => random_int(3, 25),
                'free_memory' => 96 * 1024 * 1024,
                'total_memory' => 128 * 1024 * 1024,
                'free_hdd_space' => 14 * 1024 * 1024,
                'total_hdd_space' => 16 * 1024 * 1024,
                'uptime_seconds' => random_int(3600, 86400 * 10),
                'board_name' => 'CHR',
                'version' => '7.15.3 (stable)',
            ],
        );
    }

    private function users()
    {
        return MockMikrotikUser::where('mikrotik_id', $this->mikrotik->id);
    }

    private function profiles()
    {
        return MockMikrotikProfile::where('mikrotik_id', $this->mikrotik->id);
    }

    private function interfaces()
    {
        return MockMikrotikInterface::where('mikrotik_id', $this->mikrotik->id);
    }

    private function queues()
    {
        return MockMikrotikQueue::where('mikrotik_id', $this->mikrotik->id);
    }

    private function sessions()
    {
        return MockMikrotikSession::where('mikrotik_id', $this->mikrotik->id);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function logActivity(string $eventType, string $description, array $meta = []): void
    {
        MikrotikActivityLog::on($this->mikrotik->getConnectionName())->create([
            'mikrotik_id' => $this->mikrotik->id,
            'event_type' => $eventType,
            'description' => $description,
            'meta' => $meta,
        ]);
    }

    private function userRow(MockMikrotikUser $user): array
    {
        return [
            '.id' => "*{$user->id}",
            'name' => $user->username,
            'password' => $user->password,
            'profile' => $user->profile,
            'service' => $user->service,
            'disabled' => $this->bool($user->disabled),
            'comment' => $user->comment,
            'local-address' => $user->local_address,
            'remote-address' => $user->remote_address,
            'caller-id' => $user->caller_id ?? $user->mac_address,
        ];
    }

    private function profileRow(MockMikrotikProfile $profile): array
    {
        return [
            '.id' => $profile->is_default ? '*0' : "*{$profile->id}",
            'name' => $profile->name,
            'rate-limit' => $profile->rate_limit,
            'local-address' => $profile->local_address,
            'remote-address' => $profile->remote_address,
        ];
    }

    private function queueRow(MockMikrotikQueue $queue): array
    {
        return [
            '.id' => "*{$queue->id}",
            'name' => $queue->name,
            'target' => $queue->target,
            'max-limit' => $queue->max_limit,
            'disabled' => $this->bool($queue->disabled),
            'bytes' => "{$queue->bytes_in}/{$queue->bytes_out}",
        ];
    }

    private function bool(bool $value): string
    {
        return $value ? 'true' : 'false';
    }

    private function formatDuration(int $seconds): string
    {
        $weeks = intdiv($seconds, 604800);
        $seconds %= 604800;
        $days = intdiv($seconds, 86400);
        $seconds %= 86400;
        $hours = intdiv($seconds, 3600);
        $seconds %= 3600;
        $minutes = intdiv($seconds, 60);
        $seconds %= 60;

        $parts = [];

        if ($weeks > 0) {
            $parts[] = "{$weeks}w";
        }
        if ($days > 0) {
            $parts[] = "{$days}d";
        }
        $parts[] = sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);

        return implode('', $parts);
    }
}
