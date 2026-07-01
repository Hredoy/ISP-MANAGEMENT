<?php

namespace App\Services;

use App\Models\Olt;
use App\Services\Olt\OltDriverFactory;
use Illuminate\Support\Facades\Log;
use Throwable;

class OltService
{
    public function __construct(private readonly OltDriverFactory $factory) {}

    public function testConnection(Olt $olt): array
    {
        try {
            return $this->factory->make($olt)->testConnection();
        } catch (Throwable $e) {
            Log::error('OLT connection test failed: '.$e->getMessage());

            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function bindOnu(Olt $olt, array $params): array
    {
        try {
            return $this->factory->make($olt)->bindOnu($params);
        } catch (Throwable $e) {
            Log::error('OLT ONU bind failed: '.$e->getMessage());

            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function unbindOnu(Olt $olt, array $params): array
    {
        try {
            return $this->factory->make($olt)->unbindOnu($params);
        } catch (Throwable $e) {
            Log::error('OLT ONU unbind failed: '.$e->getMessage());

            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }
}
