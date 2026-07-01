<?php

namespace App\Services\Olt;

use App\Models\Olt;
use App\Services\Olt\Support\SnmpClient;
use Throwable;

abstract class AbstractSnmpOltDriver implements OltDriverInterface
{
    public function __construct(protected readonly Olt $olt) {}

    public function testConnection(): array
    {
        try {
            $descr = $this->snmp()->sysDescr();

            return $descr
                ? ['ok' => true, 'message' => 'CONNECTION_OK', 'sysDescr' => $descr]
                : ['ok' => false, 'message' => 'SNMP sysDescr request failed.'];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function listOnus(): array
    {
        return [];
    }

    public function getOpticalInfo(string $ponPort, string $onuId): array
    {
        return [];
    }

    public function signalColor(?float $rxDbm): array
    {
        if ($rxDbm === null) {
            return ['color' => 'gray', 'emoji' => '⚪', 'label' => 'unknown'];
        }

        if ($rxDbm > -25) {
            return ['color' => 'green', 'emoji' => '🟢', 'label' => 'good'];
        }

        if ($rxDbm >= -27) {
            return ['color' => 'yellow', 'emoji' => '🟡', 'label' => 'warning'];
        }

        return ['color' => 'red', 'emoji' => '🔴', 'label' => 'critical'];
    }

    protected function snmp(): SnmpClient
    {
        return new SnmpClient($this->olt->host, $this->olt->snmp_community ?: 'public');
    }

    protected function parseOnuIndex(string $oid): array
    {
        $parts = explode('.', trim($oid, '.'));
        $onuId = array_pop($parts) ?: '0';
        $ponPort = array_pop($parts) ?: '0';

        return [$ponPort, $onuId];
    }

    protected function dbm(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        $number = (float) preg_replace('/[^0-9\-.]/', '', (string) $value);

        return $number < -100 ? $number / 100 : $number;
    }
}
