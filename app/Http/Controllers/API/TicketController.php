<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Ticket;
use App\Services\TicketService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TicketController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Tickets/Index', [
            'tickets' => Ticket::with(['client:id,full_name', 'assignee:id,name'])
                ->latest()
                ->paginate(25),
        ]);
    }

    public function store(Request $request, TicketService $tickets): RedirectResponse
    {
        $data = $request->validate([
            'message' => 'required|string|max:2000',
            'client_id' => 'nullable|integer|exists:clients,id',
            'priority' => 'nullable|in:urgent,normal',
        ]);

        $client = isset($data['client_id']) ? Client::find($data['client_id']) : null;

        $tickets->create($data['message'], $client, $data['priority'] ?? 'normal');

        return redirect()->route('dashboard.tickets.index')->with('message', 'TICKET_CREATED');
    }
}
