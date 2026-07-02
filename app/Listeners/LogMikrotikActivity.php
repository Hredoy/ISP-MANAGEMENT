<?php

namespace App\Listeners;

use App\Events\PppUserCreated;
use App\Events\PppUserDeleted;
use App\Events\PppUserUpdated;
use App\Events\RouterConnected;
use App\Events\RouterDisconnected;
use App\Events\UserConnected;
use App\Events\UserDisconnected;
use App\Models\Mikrotik;
use App\Models\MikrotikActivityLog;
use Illuminate\Events\Dispatcher;

/**
 * Turns MikroTik domain events into `mikrotik_activity_logs` rows. Works identically in Demo and
 * Real mode since both MockMikroTikService and RealMikroTikService dispatch the same events (see
 * MikroTikServiceInterface implementations) - this listener never needs to know which fired them.
 */
class LogMikrotikActivity
{
    public function handleRouterConnected(RouterConnected $event): void
    {
        $this->log($event->mikrotik, 'router.connected', "Connected to router '{$event->mikrotik->name}'.");
    }

    public function handleRouterDisconnected(RouterDisconnected $event): void
    {
        $this->log(
            $event->mikrotik,
            'router.disconnected',
            "Connection to router '{$event->mikrotik->name}' failed: {$event->reason}",
            ['reason' => $event->reason],
        );
    }

    public function handlePppUserCreated(PppUserCreated $event): void
    {
        $this->log($event->mikrotik, 'ppp_user.created', "PPPoE user '{$event->username}' created.", ['username' => $event->username]);
    }

    public function handlePppUserUpdated(PppUserUpdated $event): void
    {
        $this->log($event->mikrotik, 'ppp_user.updated', "PPPoE user '{$event->username}' updated.", ['username' => $event->username]);
    }

    public function handlePppUserDeleted(PppUserDeleted $event): void
    {
        $this->log($event->mikrotik, 'ppp_user.deleted', "PPPoE user '{$event->username}' deleted.", ['username' => $event->username]);
    }

    public function handleUserConnected(UserConnected $event): void
    {
        $this->log(
            $event->mikrotik,
            'user.connected',
            "'{$event->username}' connected.",
            array_filter(['username' => $event->username, 'address' => $event->address]),
        );
    }

    public function handleUserDisconnected(UserDisconnected $event): void
    {
        $this->log($event->mikrotik, 'user.disconnected', "'{$event->username}' disconnected.", ['username' => $event->username]);
    }

    /**
     * @return array<class-string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            RouterConnected::class => 'handleRouterConnected',
            RouterDisconnected::class => 'handleRouterDisconnected',
            PppUserCreated::class => 'handlePppUserCreated',
            PppUserUpdated::class => 'handlePppUserUpdated',
            PppUserDeleted::class => 'handlePppUserDeleted',
            UserConnected::class => 'handleUserConnected',
            UserDisconnected::class => 'handleUserDisconnected',
        ];
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function log(Mikrotik $mikrotik, string $eventType, string $description, array $meta = []): void
    {
        MikrotikActivityLog::on($mikrotik->getConnectionName())->create([
            'mikrotik_id' => $mikrotik->id,
            'event_type' => $eventType,
            'description' => $description,
            'meta' => $meta,
        ]);
    }
}
