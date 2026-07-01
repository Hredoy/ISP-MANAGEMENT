<?php

namespace App\Services\MikroTik;

use App\Models\Mikrotik;
use App\Services\MikroTik\Contracts\MikroTikServiceInterface;
use App\Services\MikroTikService as LegacyMikroTikService;

class LegacyMikroTikServiceAdapter implements MikroTikServiceInterface
{
    public function __construct(
        private readonly LegacyMikroTikService $service,
        private readonly Mikrotik $router,
    ) {
        //
    }

    public function testConnection(): array
    {
        return $this->service->testConnection($this->router);
    }

    public function getRouterInfo(): array
    {
        return $this->getSystemResources();
    }

    public function getSystemResources(): array
    {
        return $this->service->getSystemStats($this->router);
    }

    public function getInterfaces(): array
    {
        return [];
    }

    public function getPPPProfiles(): array
    {
        return [];
    }

    public function createPPPProfile(array $data): array
    {
        return [];
    }

    public function updatePPPProfile(string $name, array $data): bool
    {
        return true;
    }

    public function deletePPPProfile(string $name): bool
    {
        return true;
    }

    public function getPPPoEUsers(): array
    {
        return $this->service->getPPPoEUsers($this->router);
    }

    public function addPPPoEUser(array $data): array
    {
        return $this->service->addPPPoEUser($this->router, $data);
    }

    public function updatePPPoEUser(string $currentUsername, array $data): bool
    {
        return $this->service->updatePPPoEUser($this->router, $currentUsername, $data);
    }

    public function removePPPoEUser(string $username): bool
    {
        return $this->service->removePPPoEUser($this->router, $username);
    }

    public function enableUser(string $username): bool
    {
        return $this->service->unsuspendUser($this->router, $username);
    }

    public function disableUser(string $username): bool
    {
        return $this->service->suspendUser($this->router, $username);
    }

    public function disconnectActiveSession(string $username): bool
    {
        return true;
    }

    public function getActiveUsers(): array
    {
        return $this->service->getActiveSessions($this->router);
    }

    public function getQueues(): array
    {
        return $this->service->getQueues($this->router);
    }

    public function addSimpleQueue(string $name, string $target, string $maxLimit): array
    {
        return $this->service->addSimpleQueue($this->router, $name, $target, $maxLimit);
    }

    public function updateQueue(string $name, array $data): bool
    {
        return true;
    }

    public function deleteQueue(string $name): bool
    {
        return true;
    }

    public function getHotspotUsers(): array
    {
        return $this->service->getHotspotUsers($this->router);
    }

    public function getDhcpLeases(): array
    {
        return [];
    }

    public function reboot(): bool
    {
        return true;
    }

    public function syncRouterData(): array
    {
        return ['synced' => $this->service->syncPPPoEUsersToClients($this->router)];
    }
}
