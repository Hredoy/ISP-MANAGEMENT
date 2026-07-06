<?php

namespace App\Events;

use App\Models\Mikrotik;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** "Device down -> red badge in nav" (Sprint 1 Day 5 realtime spec). */
class DeviceStatusChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Mikrotik $router, public string $status) {}

    public function broadcastOn(): Channel
    {
        return new Channel('tenant.devices');
    }

    public function broadcastWith(): array
    {
        return [
            'router_id' => $this->router->id,
            'name' => $this->router->name,
            'status' => $this->status,
        ];
    }
}
