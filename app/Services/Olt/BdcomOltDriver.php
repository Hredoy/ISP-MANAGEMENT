<?php

namespace App\Services\Olt;

class BdcomOltDriver extends AbstractSnmpOltDriver
{
    private const OID_ONU_SERIAL = '1.3.6.1.4.1.3320.101.10.1.1.3';
    private const OID_ONU_STATUS = '1.3.6.1.4.1.3320.101.10.1.1.26';
    private const OID_ONU_MAC = '1.3.6.1.4.1.3320.101.10.1.1.7';
    private const OID_ONU_RX_POWER = '1.3.6.1.4.1.3320.101.10.5.1.5';
    private const OID_ONU_TX_POWER = '1.3.6.1.4.1.3320.101.10.5.1.6';

    public function bindOnu(array $params): array
    {
        return ['ok' => false, 'message' => 'BDCOM P3310 ONU binding is read-only in this integration. Provision from OLT CLI, then sync ONUs.'];
    }

    public function unbindOnu(array $params): array
    {
        return ['ok' => false, 'message' => 'BDCOM P3310 ONU unbind is read-only in this integration.'];
    }

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
                'vendor' => 'bdcom',
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
