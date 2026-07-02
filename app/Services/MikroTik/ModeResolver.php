<?php

namespace App\Services\MikroTik;

use App\Models\Mikrotik;
use App\Models\Setting;
use App\Services\MikroTik\DTO\ModeResolution;

/**
 * Resolves the effective Real/Demo mode for a router: an explicit per-router override wins,
 * otherwise falls back to the tenant-wide "mikrotik_mode" setting.
 */
class ModeResolver
{
    public const MODE_DEMO = 'demo';

    public const MODE_REAL = 'real';

    /**
     * @return self::MODE_DEMO|self::MODE_REAL
     */
    public function resolve(Mikrotik $mikrotik): string
    {
        return $this->resolveWithSource($mikrotik)->mode;
    }

    /**
     * Same resolution as resolve(), plus which source decided it - lets the admin panel explain
     * *why* a router is in a given mode instead of just showing the end result.
     */
    public function resolveWithSource(Mikrotik $mikrotik): ModeResolution
    {
        if (in_array($mikrotik->mode, [self::MODE_DEMO, self::MODE_REAL], true)) {
            return new ModeResolution($mikrotik->mode, ModeResolution::SOURCE_ROUTER_OVERRIDE);
        }

        return new ModeResolution($this->globalMode(), ModeResolution::SOURCE_GLOBAL);
    }

    /**
     * @return self::MODE_DEMO|self::MODE_REAL
     */
    public function globalMode(): string
    {
        $value = Setting::where('key', 'mikrotik_mode')->value('value') ?? [];
        $mode = $value['mode'] ?? self::MODE_REAL;

        return $mode === self::MODE_DEMO ? self::MODE_DEMO : self::MODE_REAL;
    }
}
