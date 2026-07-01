<?php

namespace App\Services\Olt;

use RuntimeException;
use Throwable;

/**
 * VSOL (and other SNMP-managed) OLT ONU authorization via SNMP SET
 * against the vendor's private ONU-auth MIB. OIDs below are placeholders
 * following VSOL's documented private MIB structure — confirm the exact
 * OID tree for the target OLT firmware before production use.
 */
class VsolOltDriver extends AbstractSnmpOltDriver
{
    private const OID_ONU_AUTH_TABLE = '1.3.6.1.4.1.37950.1.1.5.1.1.1';
    private const OID_ONU_SERIAL = '1.3.6.1.4.1.37950.1.1.5.1.2.1';
    private const OID_ONU_STATUS = '1.3.6.1.4.1.37950.1.1.5.1.2.5';
    private const OID_ONU_RX_POWER = '1.3.6.1.4.1.37950.1.1.5.1.3.4';
    private const OID_ONU_TX_POWER = '1.3.6.1.4.1.37950.1.1.5.1.3.5';

    public function listOnus(): array
    {
        $serials = $this->snmp()->walk(self::OID_ONU_SERIAL);
        $statuses = $this->snmp()->walk(self::OID_ONU_STATUS);
        $onus = [];

        foreach ($serials as $oid => $serial) {
            [$ponPort, $onuId] = $this->parseOnuIndex($oid);
            $optical = $this->getOpticalInfo($ponPort, $onuId);
            $rx = $optical['rx_dbm'] ?? null;

            $onus[] = [
                'vendor' => 'vsol',
                'pon_port' => $ponPort,
                'onu_id' => $onuId,
                'serial_number' => $serial,
                'mac_address' => null,
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
            return ['ok' => false, 'message' => 'ONU serial number or MAC is required for VSOL binding.'];
        }

        if (! extension_loaded('snmp')) {
            return ['ok' => false, 'message' => 'PHP snmp extension is not installed on this server.'];
        }

        $onuId = $params['onuId'] ?? '1';
        $oid = self::OID_ONU_AUTH_TABLE.'.'.$params['ponPort'].'.'.$onuId;

        try {
            $result = @snmpset(
                $this->olt->host,
                $this->olt->snmp_community ?: 'private',
                $oid,
                's',
                $serial,
                3000000,
                1
            );

            if ($result === false) {
                throw new RuntimeException('SNMP SET failed for ONU authorization.');
            }

            return ['ok' => true, 'message' => 'ONU_BOUND'];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function unbindOnu(array $params): array
    {
        if (! extension_loaded('snmp')) {
            return ['ok' => false, 'message' => 'PHP snmp extension is not installed on this server.'];
        }

        $onuId = $params['onuId'] ?? '1';
        $oid = self::OID_ONU_AUTH_TABLE.'.'.$params['ponPort'].'.'.$onuId;

        try {
            $result = @snmpset($this->olt->host, $this->olt->snmp_community ?: 'private', $oid, 's', '', 3000000, 1);

            if ($result === false) {
                throw new RuntimeException('SNMP SET failed while clearing ONU authorization.');
            }

            return ['ok' => true, 'message' => 'ONU_UNBOUND'];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
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
            default => $status ?: 'unknown',
        };
    }
}
