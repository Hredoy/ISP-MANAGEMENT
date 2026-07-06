<?php

namespace App\Events;

use App\Models\Ticket;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * "New ticket -> dashboard alert instantly" (Sprint 1 Day 5 realtime spec). Broadcasts
 * immediately (no queue worker required) since ticket volume is low and this is a UI alert,
 * not a background job. Public channel - no per-tenant auth channel yet, since there's no
 * Soketi server actually deployed to test presence/private channel auth against (see this
 * PR's honesty flag).
 */
class NewTicketCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Ticket $ticket) {}

    public function broadcastOn(): Channel
    {
        return new Channel('tenant.tickets');
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->ticket->id,
            'subject' => $this->ticket->subject,
            'priority' => $this->ticket->priority,
            'category' => $this->ticket->category,
        ];
    }
}
