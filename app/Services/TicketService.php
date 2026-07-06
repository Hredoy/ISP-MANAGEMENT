<?php

namespace App\Services;

use App\Events\NewTicketCreated;
use App\Models\Client;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Smart ticket flow (Sprint 1 · Day 5 spec), minus the "known active fault in zone? auto-reply
 * with ETA, no ticket" step - there's no fault-detection system yet (that's the separate SNMP
 * polling engine task), so every complaint always becomes a ticket for now.
 */
class TicketService
{
    private const CATEGORY_KEYWORDS = [
        'Connectivity' => ['net nai', 'no internet', 'not working', 'disconnect', 'down', 'ইন্টারনেট নাই', 'নেট নাই'],
        'Speed' => ['slow', 'speed', 'buffering', 'স্লো', 'স্পিড'],
        'Billing' => ['bill', 'payment', 'invoice', 'due', 'charge', 'বিল', 'পেমেন্ট'],
    ];

    public function create(string $message, ?Client $client = null, string $priority = 'normal'): Ticket
    {
        $category = $this->categorize($message);
        $assignee = $this->findLeastLoadedTechnician();

        $ticket = Ticket::create([
            'client_id' => $client?->id,
            'assigned_to' => $assignee?->id,
            'subject' => Str::limit($message, 100),
            'category' => $category,
            'priority' => $priority,
            'status' => $assignee ? 'assigned' : 'open',
            'message' => $message,
            'sla_due_at' => now()->addHours($priority === 'urgent' ? 2 : 24),
        ]);

        NewTicketCreated::dispatch($ticket);

        return $ticket;
    }

    /**
     * Auto-escalates tickets that missed their SLA deadline. Only logs the escalation - there's
     * no reseller/on-call-manager system yet to actually notify (same gap as the billing
     * scheduler's D+7 escalation step).
     */
    public function escalateOverdue(): int
    {
        $overdue = Ticket::whereNotIn('status', ['resolved', 'escalated'])
            ->whereNotNull('sla_due_at')
            ->where('sla_due_at', '<', now())
            ->get();

        foreach ($overdue as $ticket) {
            $ticket->update(['status' => 'escalated']);

            Log::warning('TICKET_SLA_BREACH: ticket missed its SLA deadline.', [
                'ticket_id' => $ticket->id,
                'subject' => $ticket->subject,
                'priority' => $ticket->priority,
                'sla_due_at' => $ticket->sla_due_at,
            ]);
        }

        return $overdue->count();
    }

    private function categorize(string $message): string
    {
        $needle = Str::lower($message);

        foreach (self::CATEGORY_KEYWORDS as $category => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($needle, Str::lower($keyword))) {
                    return $category;
                }
            }
        }

        return 'Other';
    }

    /**
     * Assigns to the on-duty Technician with the fewest currently open/assigned tickets.
     * Spatie's role() scope throws if the role itself has never been created for this tenant
     * (not just "no users have it"), which is the common case for a brand new tenant - so this
     * checks existence first rather than letting ticket creation itself fail.
     */
    private function findLeastLoadedTechnician(): ?User
    {
        if (! Role::where('name', 'Technician')->exists()) {
            return null;
        }

        return User::role('Technician')
            ->withCount(['assignedTickets as open_tickets_count' => function ($query) {
                $query->whereIn('status', ['open', 'assigned']);
            }])
            ->orderBy('open_tickets_count')
            ->first();
    }
}
