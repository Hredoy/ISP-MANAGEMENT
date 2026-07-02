<?php

namespace App\Services\MikroTik\DTO;

/**
 * Snapshot of a router's live dashboard stats, computed from whichever MikroTikServiceInterface
 * implementation MikroTikServiceFactory resolved (Real or Mock) - callers never see which.
 */
final readonly class RouterDashboardStats
{
    public function __construct(
        public float $cpu,
        public float $ramUsedMb,
        public float $storageUsedMb,
        public int $activeUsers,
        public string $uptime,
        public float $rxMbps,
        public float $txMbps,
        public ?string $version,
        public int $totalPppUsers,
        public int $totalQueues,
        public ?string $lastSyncAt,
    ) {
        //
    }

    /**
     * @param  array<string, mixed>  $systemResources  raw getSystemResources() output
     * @param  list<array<string, mixed>>  $interfaces  raw getInterfaces() output
     */
    public static function fromServiceData(
        array $systemResources,
        array $interfaces,
        int $activeUsers,
        int $totalPppUsers,
        int $totalQueues,
        ?string $lastSyncAt,
    ): self {
        $traffic = ['rx' => 0.0, 'tx' => 0.0];

        foreach ($interfaces as $interface) {
            if (($interface['running'] ?? 'false') !== 'true') {
                continue;
            }

            $traffic['rx'] += (float) ($interface['rx-bits-per-second'] ?? 0);
            $traffic['tx'] += (float) ($interface['tx-bits-per-second'] ?? 0);
        }

        return new self(
            cpu: (float) ($systemResources['cpu-load'] ?? 0),
            ramUsedMb: isset($systemResources['total-memory'], $systemResources['free-memory'])
                ? round(((float) $systemResources['total-memory'] - (float) $systemResources['free-memory']) / 1024 / 1024, 1)
                : 0.0,
            storageUsedMb: isset($systemResources['total-hdd-space'], $systemResources['free-hdd-space'])
                ? round(((float) $systemResources['total-hdd-space'] - (float) $systemResources['free-hdd-space']) / 1024 / 1024, 1)
                : 0.0,
            activeUsers: $activeUsers,
            uptime: (string) ($systemResources['uptime'] ?? '00:00:00'),
            rxMbps: round($traffic['rx'] / 1_000_000, 2),
            txMbps: round($traffic['tx'] / 1_000_000, 2),
            version: $systemResources['version'] ?? null,
            totalPppUsers: $totalPppUsers,
            totalQueues: $totalQueues,
            lastSyncAt: $lastSyncAt,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'cpu' => $this->cpu,
            'ram' => $this->ramUsedMb,
            'storage' => $this->storageUsedMb,
            'users' => $this->activeUsers,
            // No dedicated ARP-table method on the interface (out of scope) - approximated as
            // the PPP active-user count; unused by the current frontend anyway.
            'activeIps' => $this->activeUsers,
            'uptime' => $this->uptime,
            'rx' => $this->rxMbps,
            'tx' => $this->txMbps,
            'version' => $this->version,
            'totalPppUsers' => $this->totalPppUsers,
            'totalQueues' => $this->totalQueues,
            'lastSyncAt' => $this->lastSyncAt,
        ];
    }
}
