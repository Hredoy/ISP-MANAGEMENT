<?php

namespace App\Services\MikroTik\DTO;

/**
 * The effective Real/Demo mode for one router, plus where it came from - used by the admin panel
 * to explain *why* a router is in a given mode (its own override vs the tenant-wide default).
 */
final readonly class ModeResolution
{
    public const SOURCE_ROUTER_OVERRIDE = 'router_override';

    public const SOURCE_GLOBAL = 'global';

    public function __construct(
        public string $mode,
        public string $source,
    ) {
        //
    }

    /**
     * @return array{mode: string, source: string}
     */
    public function toArray(): array
    {
        return ['mode' => $this->mode, 'source' => $this->source];
    }
}
