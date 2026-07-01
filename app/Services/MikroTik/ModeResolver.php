<?php

namespace App\Services\MikroTik;

use App\Models\Mikrotik;
use App\Models\Setting;

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
        if (in_array($mikrotik->mode, [self::MODE_DEMO, self::MODE_REAL], true)) {
            return $mikrotik->mode;
        }

        return $this->globalMode();
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
