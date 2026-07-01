<?php

namespace App\Services;

use App\Models\Client as ISPClient;
use App\Models\Mikrotik;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RouterOS\Client;
use RouterOS\Query;
use Throwable;

class MikroTikService
{
    protected ?Client $client = null;

    public function connect(Mikrotik|string $router, ?string $user = null, ?string $pass = null, int $port = 8728): Client|false
    {
        try {
            $syncRouter = $router instanceof Mikrotik ? $router : null;

            if ($router instanceof Mikrotik) {
                $host = $router->host;
                $user = $router->username;
                $pass = $router->password;
                $port = (int) $router->port;
            } else {
                $host = $router;
            }

            $this->client = new Client([
                'host' => $host,
                'user' => $user,
                'pass' => $pass,
                'port' => (int) $port,
                'timeout' => 5,
            ]);

            if ($syncRouter && ! $syncRouter->last_pppoe_sync_at) {
                $this->syncPPPoEUsersToClients($syncRouter);
            }

            return $this->client;
        } catch (Throwable $e) {
            Log::error('MikroTik Connection Failed: '.$e->getMessage());

            return false;
        }
    }

    public function testConnection(Mikrotik|string $router, ?string $user = null, ?string $pass = null, int $port = 8728): array
    {
        $stats = $this->getSystemStats($router, $user, $pass, $port);

        if (isset($stats['error'])) {
            return [
                'ok' => false,
                'message' => $stats['error'],
                'stats' => null,
            ];
        }

        return [
            'ok' => true,
            'message' => 'CONNECTION_OK',
            'stats' => $stats,
        ];
    }

    public function getSystemStats(Mikrotik|string $router, ?string $user = null, ?string $pass = null, int $port = 8728): array
    {
        $client = $this->connectWithoutSync($router, $user, $pass, $port);

        if (! $client) {
            return ['error' => 'OFFLINE', 'cpu-load' => 0];
        }

        return $this->read($client, '/system/resource/print')[0] ?? ['error' => 'NO_DATA'];
    }

    public function getPPPoEUsers(Mikrotik|Client $router): array
    {
        return $this->read($this->clientFor($router), '/ppp/secret/print');
    }

    public function getActiveSessions(Mikrotik|Client $router): array
    {
        return $this->read($this->clientFor($router), '/ppp/active/print');
    }

    public function addPPPoEUser(Mikrotik|Client $router, array $data): array
    {
        $name = $data['name'] ?? $data['pppoe_username'] ?? null;
        $password = $data['password'] ?? $data['pppoe_password'] ?? null;

        if (! $name || ! $password) {
            throw new InvalidArgumentException('PPPoE name and password are required.');
        }

        $query = (new Query('/ppp/secret/add'))
            ->equal('name', (string) $name)
            ->equal('password', (string) $password);

        foreach (['profile', 'service', 'local-address', 'remote-address', 'comment'] as $field) {
            if (array_key_exists($field, $data) && $data[$field] !== null) {
                $query->equal($field, (string) $data[$field]);
            }
        }

        return $this->clientFor($router)->query($query)->read();
    }

    public function updatePPPoEUser(Mikrotik|Client $router, string $currentUsername, array $data): bool
    {
        $client = $this->clientFor($router);
        $secret = $this->findPPPoESecret($client, $currentUsername);

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

        $client->query($query)->read();

        return true;
    }

    public function removePPPoEUser(Mikrotik|Client $router, string $username): bool
    {
        $client = $this->clientFor($router);
        $secret = $this->findPPPoESecret($client, $username);

        if (! $secret) {
            return false;
        }

        $client->query((new Query('/ppp/secret/remove'))->equal('.id', $secret['.id']))->read();

        return true;
    }

    public function suspendUser(Mikrotik|Client $router, string $username): bool
    {
        $client = $this->clientFor($router);
        $secret = $this->findPPPoESecret($client, $username);

        if (! $secret) {
            return false;
        }

        $client->query((new Query('/ppp/secret/set'))->equal('.id', $secret['.id'])->equal('disabled', 'yes'))->read();
        $this->removeActiveSession($client, $username);

        return true;
    }

    public function unsuspendUser(Mikrotik|Client $router, string $username): bool
    {
        $client = $this->clientFor($router);
        $secret = $this->findPPPoESecret($client, $username);

        if (! $secret) {
            return false;
        }

        $client->query((new Query('/ppp/secret/set'))->equal('.id', $secret['.id'])->equal('disabled', 'no'))->read();

        return true;
    }

    public function getIPPools(Mikrotik|Client $router): array
    {
        return $this->read($this->clientFor($router), '/ip/pool/print');
    }

    public function getQueues(Mikrotik|Client $router): array
    {
        return $this->read($this->clientFor($router), '/queue/simple/print');
    }

    /**
     * Creates or updates a /queue/simple entry targeting a static IP.
     * Only meaningful when the client has a fixed target address — PPPoE
     * clients on a dynamic pool are already rate-limited via their PPP
     * profile's rate-limit (see PackageController), so callers should skip
     * this when no static target is available.
     */
    public function addSimpleQueue(Mikrotik|Client $router, string $name, string $target, string $maxLimit): array
    {
        $client = $this->clientFor($router);

        $existing = $client->query((new Query('/queue/simple/print'))
            ->equal('.proplist', '.id,name')
            ->equal('name', $name))->read();

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

        return $client->query($query)->read();
    }

    public function getHotspotUsers(Mikrotik|Client $router): array
    {
        return $this->read($this->clientFor($router), '/ip/hotspot/user/print');
    }

    public function syncPPPoEUsersToClients(Mikrotik $router): int
    {
        $client = $this->connectWithoutSync($router);

        if (! $client) {
            return 0;
        }

        $synced = 0;

        foreach ($this->getPPPoEUsers($client) as $secret) {
            if (empty($secret['name']) || ISPClient::where('pppoe_username', $secret['name'])->exists()) {
                continue;
            }

            ISPClient::create([
                'mikrotik_id' => $router->id,
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

        $router->forceFill(['last_pppoe_sync_at' => now()])->save();

        return $synced;
    }

    public function getInterfaceTraffic(Mikrotik|string $router, ?string $user = null, ?string $pass = null, string $interface = 'ether1'): ?array
    {
        $client = $this->connectWithoutSync($router, $user, $pass);

        if (! $client) {
            return null;
        }

        $query = (new Query('/interface/monitor-traffic'))
            ->equal('interface', $interface)
            ->equal('once', '');

        return $client->query($query)->read();
    }

    protected function connectWithoutSync(Mikrotik|string $router, ?string $user = null, ?string $pass = null, int $port = 8728): Client|false
    {
        try {
            if ($router instanceof Mikrotik) {
                $host = $router->host;
                $user = $router->username;
                $pass = $router->password;
                $port = (int) $router->port;
            } else {
                $host = $router;
            }

            return new Client([
                'host' => $host,
                'user' => $user,
                'pass' => $pass,
                'port' => (int) $port,
                'timeout' => 5,
            ]);
        } catch (Throwable $e) {
            Log::error('MikroTik Connection Failed: '.$e->getMessage());

            return false;
        }
    }

    protected function clientFor(Mikrotik|Client $router): Client
    {
        if ($router instanceof Client) {
            return $router;
        }

        $client = $this->connect($router);

        if (! $client) {
            throw new \RuntimeException('MikroTik router is unreachable.');
        }

        return $client;
    }

    protected function read(Client $client, string $path): array
    {
        try {
            return $client->query(new Query($path))->read();
        } catch (Throwable $e) {
            Log::error('MikroTik query failed: '.$e->getMessage());

            return [];
        }
    }

    protected function findPPPoESecret(Client $client, string $username): ?array
    {
        $secrets = $client->query((new Query('/ppp/secret/print'))
            ->equal('.proplist', '.id,name')
            ->equal('name', $username))->read();

        return $secrets[0] ?? null;
    }

    protected function removeActiveSession(Client $client, string $username): void
    {
        $sessions = $client->query((new Query('/ppp/active/print'))
            ->equal('.proplist', '.id,name')
            ->equal('name', $username))->read();

        foreach ($sessions as $session) {
            if (isset($session['.id'])) {
                $client->query((new Query('/ppp/active/remove'))->equal('.id', $session['.id']))->read();
            }
        }
    }
}
