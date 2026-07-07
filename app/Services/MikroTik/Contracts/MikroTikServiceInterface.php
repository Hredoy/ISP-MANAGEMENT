<?php

namespace App\Services\MikroTik\Contracts;

/**
 * Router-scoped abstraction over RouterOS operations. One instance is bound to exactly one
 * `App\Models\Mikrotik` router (constructed via `App\Services\MikroTik\MikroTikServiceFactory`).
 *
 * Implementations must return data in RouterOS's own raw wire shape (hyphenated keys such as
 * `cpu-load`, `rx-bits-per-second`; stringy `"true"`/`"false"` booleans) since existing callers
 * read these keys directly. `MockMikroTikService` must replicate this shape exactly, not "clean
 * it up" - the whole point of Demo Mode is that callers can't tell the difference.
 */
interface MikroTikServiceInterface
{
    /**
     * @return array{ok: bool, message: string, stats: array<string, mixed>|null}
     */
    public function testConnection(): array;

    /**
     * Router identity + version info, merged with getSystemResources()'s numeric stats.
     *
     * @return array<string, mixed>
     */
    public function getRouterInfo(): array;

    /**
     * Raw /system/resource/print shape: cpu-load, total-memory, free-memory,
     * total-hdd-space, free-hdd-space, uptime, version, board-name, ...
     *
     * @return array<string, mixed>
     */
    public function getSystemResources(): array;

    /**
     * All interfaces with live traffic counters merged in (rx-bits-per-second/tx-bits-per-second).
     *
     * @return list<array<string, mixed>>
     */
    public function getInterfaces(): array;

    /**
     * @return list<array<string, mixed>>
     */
    public function getPPPProfiles(): array;

    /**
     * @param  array{name: string, rate_limit?: string, local_address?: string, remote_address?: string}  $data
     * @return array<string, mixed>
     */
    public function createPPPProfile(array $data): array;

    /**
     * @param  array{name?: string, rate_limit?: string, local_address?: string, remote_address?: string}  $data
     */
    public function updatePPPProfile(string $name, array $data): bool;

    /**
     * @throws \App\Services\MikroTik\Exceptions\MikroTikCommandException if the profile is a protected system default
     */
    public function deletePPPProfile(string $name): bool;

    /**
     * @return list<array<string, mixed>>
     */
    public function getPPPoEUsers(): array;

    /**
     * @param  array{name?: string, pppoe_username?: string, password?: string, pppoe_password?: string, profile?: string, service?: string, local-address?: string, remote-address?: string, comment?: string}  $data
     * @return array<string, mixed>
     */
    public function addPPPoEUser(array $data): array;

    /**
     * @param  array{name?: string, pppoe_username?: string, password?: string, pppoe_password?: string, profile?: string, comment?: string, disabled?: bool}  $data
     */
    public function updatePPPoEUser(string $currentUsername, array $data): bool;

    public function removePPPoEUser(string $username): bool;

    /** Sets the PPP secret's disabled flag to "no". */
    public function enableUser(string $username): bool;

    /** Sets the PPP secret's disabled flag to "yes" and kills any active session. */
    public function disableUser(string $username): bool;

    /** Kills an active session without touching the secret's disabled flag. */
    public function disconnectActiveSession(string $username): bool;

    /**
     * Raw /ppp/active/print rows.
     *
     * @return list<array<string, mixed>>
     */
    public function getActiveUsers(): array;

    /**
     * @return list<array<string, mixed>>
     */
    public function getQueues(): array;

    /**
     * Idempotent upsert-by-name - safe to retry. Callers depend on this for retryable
     * provisioning steps.
     *
     * @return array<string, mixed>
     */
    public function addSimpleQueue(string $name, string $target, string $maxLimit): array;

    /**
     * @param  array{target?: string, max_limit?: string, disabled?: bool}  $data
     */
    public function updateQueue(string $name, array $data): bool;

    public function deleteQueue(string $name): bool;

    /**
     * Raw /queue/tree/print rows - hierarchical queues (parent/child), distinct from the flat
     * /queue/simple rows returned by getQueues().
     *
     * @return list<array<string, mixed>>
     */
    public function getQueueTree(): array;

    /**
     * Idempotent upsert-by-name, mirroring addSimpleQueue()'s retry-safety.
     *
     * @param  array{name: string, parent?: string, packet_mark?: string, max_limit?: string, limit_at?: string, priority?: int}  $data
     * @return array<string, mixed>
     */
    public function addQueueTree(array $data): array;

    /**
     * @param  array{parent?: string, max_limit?: string, limit_at?: string, priority?: int, disabled?: bool}  $data
     */
    public function updateQueueTree(string $name, array $data): bool;

    public function deleteQueueTree(string $name): bool;

    /**
     * Raw /ip/firewall/filter/print rows, in the router's current rule order (order matters for
     * firewall evaluation).
     *
     * @return list<array<string, mixed>>
     */
    public function getFirewallRules(): array;

    /**
     * @param  array{chain: string, action: string, protocol?: string, dst_port?: string, src_address?: string, dst_address?: string, comment?: string}  $data
     * @return array<string, mixed>
     */
    public function addFirewallRule(array $data): array;

    /**
     * @param  string  $id  The rule's RouterOS `.id` (or the mock service's equivalent synthetic id)
     * @param  array{chain?: string, action?: string, protocol?: string, dst_port?: string, src_address?: string, dst_address?: string, comment?: string, disabled?: bool}  $data
     */
    public function updateFirewallRule(string $id, array $data): bool;

    public function deleteFirewallRule(string $id): bool;

    /**
     * Swaps the rule with its immediate neighbour in the given direction - order matters for
     * firewall evaluation, so this is a swap, not an arbitrary reposition.
     *
     * @param  'up'|'down'  $direction
     */
    public function moveFirewallRule(string $id, string $direction): bool;

    /**
     * Bulk-kills active PPP sessions (e.g. from a multi-select in the admin panel) without
     * touching the underlying secrets' disabled flag.
     *
     * @param  list<string>  $usernames
     * @return int Number of sessions actually found and killed.
     */
    public function killActiveSessions(array $usernames): int;

    /**
     * @return list<array<string, mixed>>
     */
    public function getHotspotUsers(): array;

    /**
     * @return list<array<string, mixed>>
     */
    public function getDhcpLeases(): array;

    public function reboot(): bool;

    /**
     * Pulls current PPP secrets from the router and upserts them into the `clients` table,
     * mirroring the app's existing PPPoE-sync behaviour. Explicit action (e.g. an admin-panel
     * "Sync" button) - no longer a hidden side effect of connecting.
     *
     * @return array{synced: int}
     */
    public function syncRouterData(): array;
}
