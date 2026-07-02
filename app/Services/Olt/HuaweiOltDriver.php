<?php

namespace App\Services\Olt;

use App\Services\Olt\Support\TelnetClient;
use phpseclib3\Net\SSH2;
use Throwable;

/**
 * Huawei MA5600T/MA5800-series CLI automation (telnet or SSH).
 * Command syntax follows Huawei's standard GPON ONT provisioning flow;
 * verify against the target firmware version before production use.
 */
class HuaweiOltDriver extends AbstractSnmpOltDriver
{
    private const OID_ONT_GENERAL_TABLE = '1.3.6.1.4.1.2011.6.128.1.1.2.45.1';
    private const OID_ONT_SERIAL = '1.3.6.1.4.1.2011.6.128.1.1.2.45.1.10';
    private const OID_ONT_STATUS = '1.3.6.1.4.1.2011.6.128.1.1.2.45.1.15';
    private const OID_ONT_MAC = '1.3.6.1.4.1.2011.6.128.1.1.2.45.1.21';
    private const OID_ONT_OPTICAL_INFO = '1.3.6.1.4.1.2011.6.128.1.1.4.51.1';
    private const OID_ONT_RX_POWER = '1.3.6.1.4.1.2011.6.128.1.1.4.51.1.4';
    private const OID_ONT_TX_POWER = '1.3.6.1.4.1.2011.6.128.1.1.4.51.1.3';

    public function listOnus(): array
    {
        $serials = $this->snmp()->walk(self::OID_ONT_SERIAL);
        $statuses = $this->snmp()->walk(self::OID_ONT_STATUS);
        $macs = $this->snmp()->walk(self::OID_ONT_MAC);
        $onus = [];

        foreach ($serials as $oid => $serial) {
            [$ponPort, $onuId] = $this->parseOnuIndex($oid);
            $optical = $this->getOpticalInfo($ponPort, $onuId);
            $rx = $optical['rx_dbm'] ?? null;

            $onus[] = [
                'vendor' => 'huawei',
                'pon_port' => $ponPort,
                'onu_id' => $onuId,
                'serial_number' => $serial,
                'mac_address' => $this->valueForIndex($macs, $ponPort, $onuId),
                'status' => $this->normalizeStatus($this->valueForIndex($statuses, $ponPort, $onuId)),
                'rx_dbm' => $rx,
                'tx_dbm' => $optical['tx_dbm'] ?? null,
                'signal' => $this->signalColor($rx),
                'raw' => ['general_table_oid' => self::OID_ONT_GENERAL_TABLE],
            ];
        }

        return $onus;
    }

    public function getOpticalInfo(string $ponPort, string $onuId): array
    {
        $rx = $this->snmp()->get(self::OID_ONT_RX_POWER.'.'.$ponPort.'.'.$onuId);
        $tx = $this->snmp()->get(self::OID_ONT_TX_POWER.'.'.$ponPort.'.'.$onuId);

        return [
            'oid' => self::OID_ONT_OPTICAL_INFO,
            'rx_dbm' => $this->dbm($rx),
            'tx_dbm' => $this->dbm($tx),
        ];
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

    private function valueForIndex(array $values, string $ponPort, string $onuId): ?string
    {
        foreach ($values as $oid => $value) {
            if (str_ends_with($oid, ".{$ponPort}.{$onuId}")) {
                return $value;
            }
        }

        return null;
    }

    private function normalizeStatus(?string $status): string
    {
        return match ((string) $status) {
            '1' => 'online',
            '2' => 'offline',
            '3' => 'dying-gasp',
            default => $status ?: 'unknown',
        };
    }
}
