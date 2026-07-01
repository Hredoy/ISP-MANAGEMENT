<?php

namespace App\Services\MikroTik;

use App\Events\PppUserCreated;
use App\Events\PppUserDeleted;
use App\Events\PppUserUpdated;
use App\Events\RouterConnected;
use App\Events\RouterDisconnected;
use App\Models\Client as ISPClient;
use App\Models\Mikrotik;
use App\Services\MikroTik\Contracts\MikroTikServiceInterface;
use App\Services\MikroTik\Exceptions\MikroTikAuthenticationException;
use App\Services\MikroTik\Exceptions\MikroTikCommandException;
use App\Services\MikroTik\Exceptions\MikroTikConnectionException;
use App\Services\MikroTik\Exceptions\MikroTikException;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RouterOS\Client;
use RouterOS\Exceptions\BadCredentialsException;
use RouterOS\Exceptions\ClientException;
use RouterOS\Exceptions\QueryException;
use RouterOS\Exceptions\StreamException;
use RouterOS\Query;
use Throwable;

/**
 * Talks to a real RouterOS device over the API (or API-SSL) port for one router.
 * Constructed per-router by MikroTikServiceFactory - never bound as a container singleton.
 */
class RealMikroTikService implements MikroTikServiceInterface
{
    private ?Client $client = null;

    public function __construct(private readonly Mikrotik $mikrotik, private ?Client $injectedClient = null)
    {
        $this->client = $injectedClient;
    }

    public function testConnection(): array
    {
        $stats = $this->getSystemResources();

        if (isset($stats['error'])) {
            return ['ok' => false, 'message' => $stats['error'], 'stats' => null];
        }

        return ['ok' => true, 'message' => 'CONNECTION_OK', 'stats' => $stats];
    }

    public function getRouterInfo(): array
    {
        $resources = $this->getSystemResources();

        $identity = $this->read('/system/identity/print')[0] ?? [];

        return [
            ...$resources,
            'name' => $identity['name'] ?? $this->mikrotik->name,
        ];
    }

    /**
     * Degrades gracefully (returns an 'error' key) rather than throwing - matches the existing
     * `checkConnection()`/`getLiveStats()` controller contracts, which check for that key rather
     * than catching exceptions.
     */
    public function getSystemResources(): array
    {
        $stats = $this->read('/system/resource/print')[0] ?? null;

        if ($stats === null) {
            return ['error' => 'OFFLINE', 'cpu-load' => 0];
        }

        return $stats;
    }

    public function getInterfaces(): array
    {
        $interfaces = $this->read('/interface/print');

        if ($interfaces === []) {
            return [];
        }

        $names = implode(',', array_column($interfaces, 'name'));

        $traffic = $this->read('/interface/monitor-traffic', (new Query('/interface/monitor-traffic'))
            ->equal('interface', $names)
            ->equal('once', 'true'));

        $trafficByName = collect($traffic)->keyBy('name');

        return array_map(function (array $interface) use ($trafficByName) {
            $live = $trafficByName->get($interface['name'], []);

            return [...$interface, ...$live];
        }, $interfaces);
    }

    public function getPPPProfiles(): array
    {
        return $this->read('/ppp/profile/print');
    }

    public function createPPPProfile(array $data): array
    {
        $client = $this->getClient();

        $query = (new Query('/ppp/profile/add'))
            ->equal('name', $data['name'])
            ->equal('rate-limit', $data['rate_limit'] ?? '')
            ->equal('local-address', $data['local_address'] ?? '')
            ->equal('remote-address', $data['remote_address'] ?? '');

        return $this->write($client, $query);
    }

    public function updatePPPProfile(string $name, array $data): bool
    {
        $client = $this->getClient();

        $target = $this->findProfileByName($name);

        if (! $target) {
            return false;
        }

        $query = (new Query('/ppp/profile/set'))->equal('.id', $target['.id']);

        foreach (['name', 'rate_limit', 'local_address', 'remote_address'] as $field) {
            if (isset($data[$field])) {
                $query->equal(str_replace('_', '-', $field), (string) $data[$field]);
            }
        }

        $this->write($client, $query);

        return true;
    }

    public function deletePPPProfile(string $name): bool
    {
        $client = $this->getClient();

        $target = $this->findProfileByName($name);

        if (! $target) {
            return false;
        }

        // Standard MikroTik default profile IDs are protected from deletion.
        if (in_array($target['.id'], ['*0', '*FFFFFFFE'], true)) {
            throw new MikroTikCommandException('PROTECTED_RESOURCE: Cannot delete system default profiles.');
        }

        $this->write($client, (new Query('/ppp/profile/remove'))->equal('numbers', $target['.id']));

        return true;
    }

    public function getPPPoEUsers(): array
    {
        return $this->read('/ppp/secret/print');
    }

    public function addPPPoEUser(array $data): array
    {
        $name = $data['name'] ?? $data['pppoe_username'] ?? null;
        $password = $data['password'] ?? $data['pppoe_password'] ?? null;

        if (! $name || ! $password) {
            throw new InvalidArgumentException('PPPoE name and password are required.');
        }

        $client = $this->getClient();

        $query = (new Query('/ppp/secret/add'))
            ->equal('name', (string) $name)
            ->equal('password', (string) $password);

        foreach (['profile', 'service', 'local-address', 'remote-address', 'comment'] as $field) {
            if (array_key_exists($field, $data) && $data[$field] !== null) {
                $query->equal($field, (string) $data[$field]);
            }
        }

        $result = $this->write($client, $query);

        event(new PppUserCreated($this->mikrotik, (string) $name));

        return $result;
    }

    public function updatePPPoEUser(string $currentUsername, array $data): bool
    {
        $client = $this->getClient();
        $secret = $this->findPPPoESecret($currentUsername);

        if (! $secret) {
            return false;
        }

        $query = (new Query('/ppp/secret/set'))->equal('.id', $secret['.id']);

        $fields = [
            'name' => $data['name'] ?? $data['pppoe_username'] ?? null,
            'password' => $data['password'] ?? $data['pppoe_password'] ?? null,
            'profile' => $data['profile'] ?? null,
            'comment' => $data['comment'] ?? null,
        ];

        foreach ($fields as $field => $value) {
            if ($value !== null) {
                $query->equal($field, (string) $value);
            }
        }

        if (array_key_exists('disabled', $data)) {
            $query->equal('disabled', $data['disabled'] ? 'yes' : 'no');
        }

        $this->write($client, $query);

        event(new PppUserUpdated($this->mikrotik, $currentUsername));

        return true;
    }

    public function removePPPoEUser(string $username): bool
    {
        $client = $this->getClient();
        $secret = $this->findPPPoESecret($username);

        if (! $secret) {
            return false;
        }

        $this->write($client, (new Query('/ppp/secret/remove'))->equal('.id', $secret['.id']));

        event(new PppUserDeleted($this->mikrotik, $username));

        return true;
    }

    public function enableUser(string $username): bool
    {
        $client = $this->getClient();
        $secret = $this->findPPPoESecret($username);

        if (! $secret) {
            return false;
        }

        $this->write($client, (new Query('/ppp/secret/set'))->equal('.id', $secret['.id'])->equal('disabled', 'no'));

        return true;
    }

    public function disableUser(string $username): bool
    {
        $client = $this->getClient();
        $secret = $this->findPPPoESecret($username);

        if (! $secret) {
            return false;
        }

        $this->write($client, (new Query('/ppp/secret/set'))->equal('.id', $secret['.id'])->equal('disabled', 'yes'));
        $this->disconnectActiveSession($username);

        return true;
    }

    public function disconnectActiveSession(string $username): bool
    {
        $client = $this->getClient();

        $sessions = $this->read('/ppp/active/print', (new Query('/ppp/active/print'))
            ->equal('.proplist', '.id,name')
            ->equal('name', $username));

        foreach ($sessions as $session) {
            if (isset($session['.id'])) {
                $this->write($client, (new Query('/ppp/active/remove'))->equal('.id', $session['.id']));
            }
        }

        return true;
    }

    public function getActiveUsers(): array
    {
        return $this->read('/ppp/active/print');
    }

    public function getQueues(): array
    {
        return $this->read('/queue/simple/print');
    }

    public function addSimpleQueue(string $name, string $target, string $maxLimit): array
    {
        $client = $this->getClient();

        $existing = $this->read('/queue/simple/print', (new Query('/queue/simple/print'))
            ->equal('.proplist', '.id,name')
            ->equal('name', $name));

        if (! empty($existing[0]['.id'])) {
            $query = (new Query('/queue/simple/set'))
                ->equal('.id', $existing[0]['.id'])
                ->equal('target', $target)
                ->equal('max-limit', $maxLimit);
        } else {
            $query = (new Query('/queue/simple/add'))
                ->equal('name', $name)
                ->equal('target', $target)
                ->equal('max-limit', $maxLimit);
        }

        return $this->write($client, $query);
    }

    public function updateQueue(string $name, array $data): bool
    {
        $client = $this->getClient();

        $existing = $this->read('/queue/simple/print', (new Query('/queue/simple/print'))
            ->equal('.proplist', '.id,name')
            ->equal('name', $name));

        if (empty($existing[0]['.id'])) {
            return false;
        }

        $query = (new Query('/queue/simple/set'))->equal('.id', $existing[0]['.id']);

        if (isset($data['target'])) {
            $query->equal('target', (string) $data['target']);
        }

        if (isset($data['max_limit'])) {
            $query->equal('max-limit', (string) $data['max_limit']);
        }

        if (array_key_exists('disabled', $data)) {
            $query->equal('disabled', $data['disabled'] ? 'yes' : 'no');
        }

        $this->write($client, $query);

        return true;
    }

    public function deleteQueue(string $name): bool
    {
        $client = $this->getClient();

        $existing = $this->read('/queue/simple/print', (new Query('/queue/simple/print'))
            ->equal('.proplist', '.id,name')
            ->equal('name', $name));

        if (empty($existing[0]['.id'])) {
            return false;
        }

        $this->write($client, (new Query('/queue/simple/remove'))->equal('.id', $existing[0]['.id']));

        return true;
    }

    public function getHotspotUsers(): array
    {
        return $this->read('/ip/hotspot/user/print');
    }

    public function getDhcpLeases(): array
    {
        return $this->read('/ip/dhcp-server/lease/print');
    }

    public function reboot(): bool
    {
        $client = $this->getClient();

        $this->write($client, new Query('/system/reboot'));

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
                'full_address' => 'Synced from MikroTik',
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

    /**
     * Lazily connects and reuses the connection for this instance's lifetime.
     *
     * @throws MikroTikConnectionException
     * @throws MikroTikAuthenticationException
     */
    private function getClient(): Client
    {
        if ($this->client) {
            return $this->client;
        }

        try {
            $this->client = new Client([
                'host' => $this->mikrotik->host,
                'user' => $this->mikrotik->username,
                'pass' => $this->mikrotik->password,
                'port' => (int) $this->mikrotik->port,
                'timeout' => 5,
            ]);

            event(new RouterConnected($this->mikrotik));

            return $this->client;
        } catch (BadCredentialsException $e) {
            Log::error('MikroTik authentication failed: '.$e->getMessage());
            event(new RouterDisconnected($this->mikrotik, $e->getMessage()));

            throw new MikroTikAuthenticationException('AUTH_FAILED: Invalid credentials.', previous: $e);
        } catch (ClientException|StreamException $e) {
            Log::error('MikroTik Connection Failed: '.$e->getMessage());
            event(new RouterDisconnected($this->mikrotik, $e->getMessage()));

            throw new MikroTikConnectionException('OFFLINE: '.$e->getMessage(), previous: $e);
        }
    }

    /**
     * Read-only queries degrade gracefully (matches existing dashboard UX - a transient hiccup
     * shouldn't crash the page, it should just show zeroed/empty data).
     *
     * @return list<array<string, mixed>>
     */
    private function read(string $path, ?Query $query = null): array
    {
        try {
            $client = $this->getClient();

            return $client->query($query ?? new Query($path))->read();
        } catch (MikroTikException $e) {
            return [];
        } catch (Throwable $e) {
            Log::error('MikroTik query failed: '.$e->getMessage());

            return [];
        }
    }

    /**
     * Write/command queries throw on failure so callers can surface a real error instead of
     * silently no-op-ing.
     *
     * @return array<string, mixed>
     *
     * @throws MikroTikCommandException
     * @throws MikroTikConnectionException
     */
    private function write(Client $client, Query $query): array
    {
        try {
            return $client->query($query)->read();
        } catch (QueryException $e) {
            throw new MikroTikCommandException('COMMAND_FAILED: '.$e->getMessage(), previous: $e);
        } catch (ClientException|StreamException $e) {
            throw new MikroTikConnectionException('OFFLINE: '.$e->getMessage(), previous: $e);
        }
    }

    private function findPPPoESecret(string $username): ?array
    {
        $secrets = $this->read('/ppp/secret/print', (new Query('/ppp/secret/print'))
            ->equal('.proplist', '.id,name')
            ->equal('name', $username));

        return $secrets[0] ?? null;
    }

    private function findProfileByName(string $name): ?array
    {
        $profiles = $this->read('/ppp/profile/print', (new Query('/ppp/profile/print'))
            ->equal('.proplist', 'name,.id'));

        return collect($profiles)->firstWhere('name', $name);
    }
}
