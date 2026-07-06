<?php

namespace App\Console\Commands;

use App\Services\TicketService;
use Illuminate\Console\Command;

class EscalateOverdueTickets extends Command
{
    protected $signature = 'tickets:escalate-overdue';

    protected $description = 'Escalate tickets that have missed their SLA deadline.';

    public function handle(TicketService $tickets): int
    {
        $count = $tickets->escalateOverdue();

        $this->info("Escalated {$count} overdue ticket(s).");

        return self::SUCCESS;
    }
}
