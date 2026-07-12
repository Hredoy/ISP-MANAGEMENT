<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Payment;
use App\Models\Reseller;
use App\Models\ResellerCommission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ResellerController extends Controller
{
    /** ISP admin: list all resellers with stats. */
    public function index(): Response
    {
        $resellers = Reseller::withCount('clients')
            ->withSum('commissions', 'amount')
            ->with('parent:id,name')
            ->latest()
            ->get();

        return Inertia::render('Reseller/Index', [
            'resellers' => $resellers,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Reseller/Create', [
            'parentResellers' => Reseller::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'               => 'required|string|max:100',
            'phone'              => 'nullable|string|max:20',
            'email'              => 'nullable|email|max:100',
            'commission_rate'    => 'required|numeric|min:0|max:100',
            'parent_reseller_id' => 'nullable|uuid|exists:resellers,id',
            'status'             => 'required|in:active,inactive',
        ]);

        Reseller::create($data);

        return redirect()->route('dashboard.resellers.index')
            ->with('message', 'RESELLER_CREATED');
    }

    public function edit(Reseller $reseller): Response
    {
        return Inertia::render('Reseller/Edit', [
            'reseller'        => $reseller,
            'parentResellers' => Reseller::where('id', '!=', $reseller->id)
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, Reseller $reseller): RedirectResponse
    {
        $data = $request->validate([
            'name'               => 'required|string|max:100',
            'phone'              => 'nullable|string|max:20',
            'email'              => 'nullable|email|max:100',
            'commission_rate'    => 'required|numeric|min:0|max:100',
            'parent_reseller_id' => 'nullable|uuid|exists:resellers,id',
            'status'             => 'required|in:active,inactive',
        ]);

        $reseller->update($data);

        return redirect()->route('dashboard.resellers.index')
            ->with('message', 'RESELLER_UPDATED');
    }

    public function destroy(Reseller $reseller): RedirectResponse
    {
        $reseller->delete();

        return back()->with('message', 'RESELLER_DELETED');
    }

    /** Reseller's own dashboard: their clients + commissions + wallet. */
    public function dashboard(): Response
    {
        $user = auth()->user();
        $reseller = Reseller::where('user_id', $user->id)->firstOrFail();

        $clients = Client::where('reseller_id', $reseller->id)
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $commissions = ResellerCommission::where('reseller_id', $reseller->id)
            ->with('client:id,full_name', 'payment:id,amount,paid_at')
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $today = now()->toDateString();
        $monthStart = now()->startOfMonth();

        $summary = [
            'wallet_balance'     => $reseller->wallet_balance,
            'commission_month'   => ResellerCommission::where('reseller_id', $reseller->id)
                ->where('created_at', '>=', $monthStart)
                ->sum('amount'),
            'commission_pending' => ResellerCommission::where('reseller_id', $reseller->id)
                ->where('status', 'pending')
                ->sum('amount'),
            'clients_total'      => Client::where('reseller_id', $reseller->id)->count(),
            'clients_active'     => Client::where('reseller_id', $reseller->id)
                ->where('status', 'Active')
                ->where('expiry_date', '>=', $today)
                ->count(),
        ];

        return Inertia::render('Reseller/Dashboard', [
            'reseller'    => $reseller,
            'clients'     => $clients,
            'commissions' => $commissions,
            'summary'     => $summary,
        ]);
    }

    /** ISP admin: reseller detail — all clients + commissions for one reseller. */
    public function show(Reseller $reseller): Response
    {
        $reseller->load('parent:id,name', 'children:id,name,commission_rate,status');

        $clients = Client::where('reseller_id', $reseller->id)
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $commissions = ResellerCommission::where('reseller_id', $reseller->id)
            ->with('client:id,full_name', 'payment:id,amount,paid_at')
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $monthStart = now()->startOfMonth();

        $summary = [
            'wallet_balance'   => $reseller->wallet_balance,
            'commission_total' => ResellerCommission::where('reseller_id', $reseller->id)->sum('amount'),
            'commission_month' => ResellerCommission::where('reseller_id', $reseller->id)
                ->where('created_at', '>=', $monthStart)
                ->sum('amount'),
            'clients_count'    => $reseller->clients_count ?? Client::where('reseller_id', $reseller->id)->count(),
        ];

        return Inertia::render('Reseller/Show', [
            'reseller'    => $reseller,
            'clients'     => $clients,
            'commissions' => $commissions,
            'summary'     => $summary,
        ]);
    }

    /** Mark a commission as paid and deduct from wallet. */
    public function markCommissionPaid(Reseller $reseller, ResellerCommission $commission): RedirectResponse
    {
        abort_if($commission->reseller_id !== $reseller->id, 403);
        abort_if($commission->status === 'paid', 422, 'Already paid.');

        $commission->update(['status' => 'paid', 'paid_at' => now()]);
        $reseller->decrement('wallet_balance', $commission->amount);

        return back()->with('message', 'COMMISSION_MARKED_PAID');
    }
}
