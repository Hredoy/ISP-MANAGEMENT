<?php

namespace App\Services\Olt;

use App\Models\Olt;
use App\Services\Olt\Support\TelnetClient;
use phpseclib3\Net\SSH2;
use Throwable;

/**
 * Huawei MA5600T/MA5800-series CLI automation (telnet or SSH).
 * Command syntax follows Huawei's standard GPON ONT provisioning flow;
 * verify against the target firmware version before production use.
 */
class HuaweiOltDriver implements OltDriverInterface
{
    public function __construct(private readonly Olt $olt) {}

    public function testConnection(): array
    {
        try {
            $this->withSession(fn () => null);

            return ['ok' => true, 'message' => 'CONNECTION_OK'];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function bindOnu(array $params): array
    {
        $serial = $params['serial'] ?? $params['mac'] ?? null;

        if (! $serial) {
            return ['ok' => false, 'message' => 'ONU serial number or MAC is required for Huawei binding.'];
        }

        $onuId = $params['onuId'] ?? '0';
        $desc = addslashes($params['description'] ?? '');

        $commands = [
            'enable',
            'config',
            'interface gpon '.$params['ponPort'],
            "ont add {$onuId} sn-auth {$serial} omci desc \"{$desc}\"",
            'quit',
            'quit',
        ];

        try {
            $output = $this->withSession(fn ($session) => $this->runCommands($session, $commands));

            return ['ok' => true, 'message' => 'ONU_BOUND', 'output' => $output];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function unbindOnu(array $params): array
    {
        $onuId = $params['onuId'] ?? '0';

        $commands = [
            'enable',
            'config',
            'interface gpon '.$params['ponPort'],
            "ont delete {$onuId}",
            'quit',
            'quit',
        ];

        try {
            $output = $this->withSession(fn ($session) => $this->runCommands($session, $commands));

            return ['ok' => true, 'message' => 'ONU_UNBOUND', 'output' => $output];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    private function withSession(callable $callback)
    {
        if ((int) $this->olt->port === 22) {
            $ssh = new SSH2($this->olt->host, 22, 8);

            if (! $ssh->login($this->olt->username, $this->olt->password)) {
                throw new \RuntimeException('Huawei OLT SSH authentication failed.');
            }

            return $callback($ssh);
        }

        $telnet = new TelnetClient($this->olt->host, $this->olt->port ?: 23);
        $telnet->connect();
        $telnet->waitFor('Username:');
        $telnet->sendAndWait($this->olt->username ?? '', 'Password:');
        $telnet->sendAndWait($this->olt->password ?? '', '>');

        try {
            return $callback($telnet);
        } finally {
            $telnet->close();
        }
    }

    private function runCommands($session, array $commands): string
    {
        $output = '';

        foreach ($commands as $command) {
            $output .= $session instanceof SSH2
                ? $session->exec($command)
                : $session->sendAndWait($command, '#');
        }

        return $output;
    }
}
