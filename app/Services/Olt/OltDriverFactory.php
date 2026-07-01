<?php

namespace App\Services\Olt;

use App\Models\Olt;
use InvalidArgumentException;

class OltDriverFactory
{
    public function make(Olt $olt): OltDriverInterface
    {
        return match ($olt->vendor) {
            'huawei' => new HuaweiOltDriver($olt),
            'zte' => new ZteOltDriver($olt),
            'vsol' => new VsolOltDriver($olt),
            default => throw new InvalidArgumentException("Unsupported OLT vendor: {$olt->vendor}"),
        };
    }
}
