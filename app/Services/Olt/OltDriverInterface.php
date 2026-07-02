<?php

namespace App\Services\Olt;

interface OltDriverInterface
{
    /**
     * @return array{ok: bool, message: string}
     */
    public function testConnection(): array;

    /**
     * @param  array{ponPort: string, onuId?: string, serial?: string, mac?: string, description?: string}  $params
     * @return array{ok: bool, message: string}
     */
    public function bindOnu(array $params): array;

    /**
     * @param  array{ponPort: string, onuId?: string, serial?: string, mac?: string}  $params
     * @return array{ok: bool, message: string}
     */
    public function unbindOnu(array $params): array;

    /**
     * @return list<array<string, mixed>>
     */
    public function listOnus(): array;

    /**
     * @return array<string, mixed>
     */
    public function getOpticalInfo(string $ponPort, string $onuId): array;

    /**
     * @return array{color: string, emoji: string, label: string}
     */
    public function signalColor(?float $rxDbm): array;
}
