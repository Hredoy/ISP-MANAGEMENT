<?php

namespace App\Services;

use App\Events\DeviceStatusChanged;
use App\Models\Fault;
use App\Models\Mikrotik;
use App\Services\MikroTik\MikroTikServiceFactory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sprint 1 · Day 5 · SNMP polling engine - scoped to the routers this app can actually talk to
 * (Mikrotik, via the existing RouterOS API service) rather than raw SNMP walks against generic
 * third-party switches/APs, which is separate, still-TODO scope ("Core switch + access point
 * monitoring"). getSystemResources() already gives CPU/uptime without needing php-snmp at all.
 *
 * Redis optimization from the spec: device status is cached for 30s and the DB (and the
 * DeviceStatusChanged broadcast) is only touched when the cached value actually changes -
 * "95% fewer DB writes" on a fleet that's mostly stable between polls.
 *
 * Auto-fix rules NOT implemented here (see this task's PR for the full explanation):
 * "PPPoE stuck -> force session reset" and "ONU unresponsive -> OLT CLI reboot" both require a
 * reliable stuck/unresponsive signal this codebase doesn't have, and both are actions with real
 * customer impact if triggered on a false positive. "Core link > 95%" needs interface-level
 * bandwidth history this app doesn't track yet. Only "MikroTik CPU > 90% for 5 min -> alert" is
 * implemented, since it's a pure alert (no destructive action) and CPU load is already returned
 * by getSystemResources().
 */
class DevicePollingService
{
    private const STATUS_TTL = 30;

    private const CPU_HIGH_THRESHOLD = 90.0;

    private const CPU_HIGH_SUSTAINED_MINUTES = 5;

    public function __construct(private readonly MikroTikServiceFactory $factory) {}

    /**
     * @return array{polled: int, changed: int, faults_opened: int, faults_resolved: int}
     */
    public function pollAll(): array
    {
        $routers = Mikrotik::all();
        $changed = 0;
        $faultsOpened = 0;
        $faultsResolved = 0;

        foreach ($routers as $router) {
            $result = $this->pollOne($router);
            $changed += $result['changed'] ? 1 : 0;
            $faultsOpened += $result['fault_opened'] ? 1 : 0;
            $faultsResolved += $result['fault_resolved'] ? 1 : 0;
        }

        return [
            'polled' => $routers->count(),
            'changed' => $changed,
            'faults_opened' => $faultsOpened,
            'faults_resolved' => $faultsResolved,
        ];
    }

    /**
     * @return array{changed: bool, fault_opened: bool, fault_resolved: bool}
     */
    private function pollOne(Mikrotik $router): array
    {
        [$status, $cpu] = $this->fetchLiveStatus($router);

        $cacheKey = "device_status:mikrotik:{$router->id}";
        $cached = Cache::get($cacheKey);
        $changed = $cached === null || $cached['status'] !== $status;

        Cache::put($cacheKey, ['status' => $status, 'cpu' => $cpu], self::STATUS_TTL);

        if ($changed) {
            $router->forceFill(['status' => $status])->save();
            DeviceStatusChanged::dispatch($router, $status);
        }

        $faultResult = $this->evaluateCpuFault($router, $cpu);

        return ['changed' => $changed, ...$faultResult];
    }

    /**
     * @return array{0: string, 1: float}
     */
    private function fetchLiveStatus(Mikrotik $router): array
    {
        try {
            $stats = $this->factory->make($router)->getSystemResources();

            if (isset($stats['error'])) {
                return ['offline', 0.0];
            }

            return ['online', (float) ($stats['cpu-load'] ?? 0)];
        } catch (Throwable $e) {
            Log::error('Device poll failed: '.$e->getMessage(), ['mikrotik_id' => $router->id]);

            return ['offline', 0.0];
        }
    }

    /**
     * @return array{fault_opened: bool, fault_resolved: bool}
     */
    private function evaluateCpuFault(Mikrotik $router, float $cpu): array
    {
        $sinceKey = "device_cpu_high_since:mikrotik:{$router->id}";
        $openFault = Fault::where('mikrotik_id', $router->id)
            ->where('title', 'High CPU load')
            ->where('status', 'open')
            ->first();

        if ($cpu <= self::CPU_HIGH_THRESHOLD) {
            Cache::forget($sinceKey);

            if ($openFault) {
                $openFault->update(['status' => 'resolved', 'resolved_at' => now()]);

                return ['fault_opened' => false, 'fault_resolved' => true];
            }

            return ['fault_opened' => false, 'fault_resolved' => false];
        }

        $highSince = Cache::get($sinceKey);
        if (! $highSince) {
            Cache::put($sinceKey, now()->toIso8601String(), now()->addHour());

            return ['fault_opened' => false, 'fault_resolved' => false];
        }

        // Carbon 3's diffInMinutes() is signed by default (negative when the argument is in the
        // past relative to now()) - abs() so this correctly measures elapsed time either way.
        $sustainedMinutes = abs(now()->diffInMinutes(Carbon::parse($highSince)));

        if ($sustainedMinutes >= self::CPU_HIGH_SUSTAINED_MINUTES && ! $openFault) {
            Fault::create([
                'mikrotik_id' => $router->id,
                'title' => 'High CPU load',
                'severity' => 'critical',
                'status' => 'open',
                'detected_at' => now(),
                'notes' => "CPU load at {$cpu}% for at least ".self::CPU_HIGH_SUSTAINED_MINUTES.' minutes.',
            ]);

            return ['fault_opened' => true, 'fault_resolved' => false];
        }

        return ['fault_opened' => false, 'fault_resolved' => false];
    }
}
