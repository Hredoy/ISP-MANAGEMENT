<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Payment;
use App\Models\Reseller;
use App\Models\ResellerCommission;
use App\Models\Setting;
use App\Support\TenantCache;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class BillingController extends Controller
{
    public function index(Request $request): Response
    {
        $payments = Payment::with('client:id,full_name,pppoe_username,phone_number')
            ->when($request->string('status')->toString(), fn ($query, $status) => $query->where('status', $status))
            ->when($request->string('method')->toString(), fn ($query, $method) => $query->where('method', $method))
            ->latest('paid_at')
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $today = now()->toDateString();
        $monthStart = now()->startOfMonth();

        return Inertia::render('Billing/Index', [
            'payments' => $payments,
            'clients' => Client::orderBy('full_name')->get(['id', 'full_name', 'pppoe_username', 'monthly_bill', 'expiry_date', 'status']),
            'filters' => $request->only(['status', 'method']),
            'settings' => Setting::where('key', 'billing')->value('value') ?? [],
            'summary' => [
                'revenue_today' => Payment::where('status', 'completed')->whereDate('paid_at', $today)->sum('amount'),
                'revenue_month' => Payment::where('status', 'completed')->where('paid_at', '>=', $monthStart)->sum('amount'),
                'due_amount' => Client::where('status', '!=', 'Suspended')->where('expiry_date', '<', $today)->sum('monthly_bill'),
                'expired_clients' => Client::where('status', '!=', 'Suspended')->where('expiry_date', '<', $today)->count(),
            ],
        ]);
    }

    public function storePayment(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'client_id' => 'required|integer|exists:clients,id',
            'amount' => 'required|numeric|min:1',
            'method' => 'required|string|max:40',
            'status' => 'required|in:pending,completed,failed,partial',
            'billing_period' => 'nullable|date',
            'paid_at' => 'nullable|date',
            'extend_days' => 'nullable|integer|min:0|max:365',
            'note' => 'nullable|string|max:500',
        ]);

        DB::transaction(function () use ($data) {
            $client = Client::with('reseller')->lockForUpdate()->findOrFail($data['client_id']);
            $paidAt = isset($data['paid_at']) ? Carbon::parse($data['paid_at']) : now();

            $payment = Payment::create([
                'client_id' => $client->id,
                'amount' => $data['amount'],
                'method' => $data['method'],
                'status' => $data['status'],
                'billing_period' => $data['billing_period'] ?? $paidAt->copy()->startOfMonth()->toDateString(),
                'paid_at' => $data['status'] === 'completed' ? $paidAt : null,
                'meta' => ['note' => $data['note'] ?? null],
            ]);

            if ($data['status'] === 'completed' && $client->reseller_id) {
                $this->creditCommissions($client, $payment);
            }

            if ($data['status'] === 'completed' && (int) ($data['extend_days'] ?? 0) > 0) {
                $baseDate = $client->expiry_date && Carbon::parse($client->expiry_date)->isFuture()
                    ? Carbon::parse($client->expiry_date)
                    : now();

                $client->update([
                    'expiry_date' => $baseDate->addDays((int) $data['extend_days'])->toDateString(),
                    'status' => 'Active',
                    'throttled_at' => null,
                ]);
            }
        });

        TenantCache::forgetDashboardAndClients();

        return back()->with('message', 'PAYMENT_RECORDED');
    }

    /** Walk the reseller tree and create a commission record at each level. */
    private function creditCommissions(Client $client, Payment $payment): void
    {
        $reseller = $client->reseller;

        while ($reseller) {
            if ((float) $reseller->commission_rate <= 0) {
                $reseller = $reseller->parent;
                continue;
            }

            $amount = round((float) $payment->amount * (float) $reseller->commission_rate / 100, 2);

            ResellerCommission::create([
                'reseller_id' => $reseller->id,
                'client_id'   => $client->id,
                'payment_id'  => $payment->id,
                'amount'      => $amount,
                'status'      => 'pending',
            ]);

            $reseller->increment('wallet_balance', $amount);

            $reseller = $reseller->parent;
        }
    }
}
