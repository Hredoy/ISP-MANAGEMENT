<?php

namespace App\Services\Olt;

use App\Models\Olt;
use App\Services\Olt\Support\SnmpClient;
use InvalidArgumentException;

class OltDriverFactory
{
    public function make(Olt $olt): OltDriverInterface
    {
        $vendor = $olt->vendor === 'auto' ? $this->detectVendor($olt) : $olt->vendor;

        return match ($vendor) {
            'huawei' => new HuaweiOltDriver($olt),
            'zte' => new ZteOltDriver($olt),
            'bdcom' => new BdcomOltDriver($olt),
            'vsol' => new VsolOltDriver($olt),
            default => throw new InvalidArgumentException("Unsupported OLT vendor: {$vendor}"),
        };
    }

    public function detectVendor(Olt $olt): string
    {
        $descr = (new SnmpClient($olt->host, $olt->snmp_community ?: 'public'))->sysDescr() ?? '';
        $haystack = strtolower($descr);

        return match (true) {
            str_contains($haystack, 'huawei') || str_contains($haystack, 'ma5608') || str_contains($haystack, 'ma5683') => 'huawei',
            str_contains($haystack, 'zte') || str_contains($haystack, 'c300') || str_contains($haystack, 'c320') => 'zte',
            str_contains($haystack, 'bdcom') || str_contains($haystack, 'p3310') => 'bdcom',
            str_contains($haystack, 'vsol') => 'vsol',
            default => throw new InvalidArgumentException("Unable to auto-detect OLT vendor from sysDescr: {$descr}"),
        };
    }
}
