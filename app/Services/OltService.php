<?php

namespace App\Services;

use App\Models\Olt;
use App\Models\Onu;
use App\Services\Olt\OltDriverFactory;
use Illuminate\Support\Facades\Log;
use Throwable;

class OltService
{
    public function __construct(private readonly OltDriverFactory $factory) {}

    public function testConnection(Olt $olt): array
    {
        try {
            $result = $this->factory->make($olt)->testConnection();

            if (($result['ok'] ?? false) && isset($result['sysDescr'])) {
                $olt->forceFill(['sys_descr' => $result['sysDescr']])->save();
            }

            return $result;
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

    public function detectVendor(Olt $olt): string
    {
        $vendor = $this->factory->detectVendor($olt);
        $olt->forceFill(['vendor' => $vendor])->save();

        return $vendor;
    }

    public function listOnus(Olt $olt): array
    {
        return $this->factory->make($olt)->listOnus();
    }

    public function syncOnus(Olt $olt): array
    {
        $driver = $this->factory->make($olt);
        $synced = 0;

        foreach ($driver->listOnus() as $row) {
            $serial = $row['serial_number'] ?: null;

            if (! $serial) {
                continue;
            }

            $signal = $row['signal'] ?? $driver->signalColor($row['rx_dbm'] ?? null);

            Onu::updateOrCreate(
                ['serial_number' => $serial],
                [
                    'olt_id' => $olt->id,
                    'client_id' => $this->matchingClientId($row),
                    'mac_address' => $row['mac_address'] ?? null,
                    'pon_port' => $row['pon_port'] ?? null,
                    'onu_id' => $row['onu_id'] ?? null,
                    'status' => $row['status'] ?? 'unknown',
                    'rx_dbm' => $row['rx_dbm'] ?? null,
                    'tx_dbm' => $row['tx_dbm'] ?? null,
                    'signal_color' => $signal['color'] ?? null,
                    'signal_label' => trim(($signal['emoji'] ?? '').' '.($signal['label'] ?? '')),
                    'signal_metrics' => $row,
                    'last_seen_at' => now(),
                ]
            );

            $synced++;
        }

        $olt->forceFill(['last_onu_sync_at' => now()])->save();

        return ['ok' => true, 'message' => 'ONUS_SYNCED', 'synced' => $synced];
    }

    private function matchingClientId(array $row): ?int
    {
        $query = \App\Models\Client::query();

        if (! empty($row['serial_number'])) {
            $query->orWhere('onu_serial', $row['serial_number']);
        }

        if (! empty($row['mac_address'])) {
            $query->orWhere('onu_mac', $row['mac_address']);
        }

        return $query->value('id');
    }
}
