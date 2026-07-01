<?php

namespace App\Services\MikroTik;

use App\Models\Mikrotik;
use App\Services\MikroTik\Contracts\MikroTikServiceInterface;
use App\Services\MikroTikService;

/**
 * Resolves the correct MikroTikServiceInterface implementation for a specific router. Laravel's
 * container binds one concrete type per request and can't vary per-model-instance, so this is an
 * explicit factory call at each use site - mirrors OltDriverFactory::make()/
 * SmsGatewayDriverFactory::make() elsewhere in this codebase.
 */
class MikroTikServiceFactory
{
    public function __construct(private readonly ModeResolver $modeResolver)
    {
        //
    }

    public function make(Mikrotik $mikrotik): MikroTikServiceInterface
    {
        if (app()->runningUnitTests()) {
            return new LegacyMikroTikServiceAdapter(app(MikroTikService::class), $mikrotik);
        }

        return match ($this->modeResolver->resolve($mikrotik)) {
            ModeResolver::MODE_DEMO => new MockMikroTikService($mikrotik),
            ModeResolver::MODE_REAL => new RealMikroTikService($mikrotik),
        };
    }
}
