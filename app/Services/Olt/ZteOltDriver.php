<?php

namespace App\Services\Olt;

use App\Services\Olt\Support\TelnetClient;
use phpseclib3\Net\SSH2;
use Throwable;

/**
 * ZTE C300/C320-series CLI automation (telnet or SSH).
 * Command syntax follows ZTE's standard GPON ONU binding flow;
 * verify against the target firmware version before production use.
 */
class ZteOltDriver extends AbstractSnmpOltDriver
{
    private const OID_ONU_SERIAL = '1.3.6.1.4.1.3902.1082.500.10.2.3.3.1.6';
    private const OID_ONU_STATUS = '1.3.6.1.4.1.3902.1082.500.10.2.3.8.1.4';
    private const OID_ONU_MAC = '1.3.6.1.4.1.3902.1082.500.10.2.3.3.1.5';
    private const OID_ONU_RX_POWER = '1.3.6.1.4.1.3902.1082.500.10.2.3.10.1.2';
    private const OID_ONU_TX_POWER = '1.3.6.1.4.1.3902.1082.500.10.2.3.10.1.3';

    public function listOnus(): array
    {
        $serials = $this->snmp()->walk(self::OID_ONU_SERIAL);
        $statuses = $this->snmp()->walk(self::OID_ONU_STATUS);
        $macs = $this->snmp()->walk(self::OID_ONU_MAC);
        $onus = [];

        foreach ($serials as $oid => $serial) {
            [$ponPort, $onuId] = $this->parseOnuIndex($oid);
            $optical = $this->getOpticalInfo($ponPort, $onuId);
            $rx = $optical['rx_dbm'] ?? null;

            $onus[] = [
                'vendor' => 'zte',
                'pon_port' => $ponPort,
                'onu_id' => $onuId,
                'serial_number' => $serial,
                'mac_address' => $this->valueForIndex($macs, $ponPort, $onuId),
                'status' => $this->normalizeStatus($this->valueForIndex($statuses, $ponPort, $onuId)),
                'rx_dbm' => $rx,
                'tx_dbm' => $optical['tx_dbm'] ?? null,
                'signal' => $this->signalColor($rx),
            ];
        }

        return $onus;
    }

    public function getOpticalInfo(string $ponPort, string $onuId): array
    {
        return [
            'rx_dbm' => $this->dbm($this->snmp()->get(self::OID_ONU_RX_POWER.'.'.$ponPort.'.'.$onuId)),
            'tx_dbm' => $this->dbm($this->snmp()->get(self::OID_ONU_TX_POWER.'.'.$ponPort.'.'.$onuId)),
        ];
    }

    public function bindOnu(array $params): array
    {
        $serial = $params['serial'] ?? $params['mac'] ?? null;

        if (! $serial) {
            return ['ok' => false, 'message' => 'ONU serial number or MAC is required for ZTE binding.'];
        }

        $onuId = $params['onuId'] ?? '1';
        $desc = addslashes($params['description'] ?? '');

        $commands = [
            'configure terminal',
            'interface gpon-olt_'.$params['ponPort'],
            "onu {$onuId} type ONU sn {$serial}",
            'exit',
            'interface gpon-onu_'.$params['ponPort'].":{$onuId}",
            "description {$desc}",
            'exit',
            'exit',
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
        $onuId = $params['onuId'] ?? '1';

        $commands = [
            'configure terminal',
            'interface gpon-olt_'.$params['ponPort'],
            "no onu {$onuId}",
            'exit',
            'exit',
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
                throw new \RuntimeException('ZTE OLT SSH authentication failed.');
            }

            return $callback($ssh);
        }

        $telnet = new TelnetClient($this->olt->host, $this->olt->port ?: 23);
        $telnet->connect();
        $telnet->waitFor('Username:');
        $telnet->sendAndWait($this->olt->username ?? '', 'Password:');
        $telnet->sendAndWait($this->olt->password ?? '', '#');

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
            '3' => 'los',
            default => $status ?: 'unknown',
        };
    }
}
