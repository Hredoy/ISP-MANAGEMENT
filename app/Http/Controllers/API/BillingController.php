<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\CreditTransaction;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Setting;
use App\Services\InvoiceService;
use App\Support\TenantCache;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class BillingController extends Controller
{
    public function __construct(private readonly InvoiceService $invoiceService) {}

    public function index(Request $request): Response
    {
        $payments = Payment::with('client:id,full_name,pppoe_username,phone_number', 'invoice:id,payment_id,invoice_number,status,pdf_path')
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
            'clients' => Client::orderBy('full_name')->get(['id', 'full_name', 'pppoe_username', 'monthly_bill', 'expiry_date', 'status', 'credit_balance']),
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
            'txn_id' => 'nullable|string|max:100',
            'note' => 'nullable|string|max:500',
            'apply_credit' => 'nullable|boolean',
        ]);

        $invoiceService = $this->invoiceService;

        DB::transaction(function () use ($data, $invoiceService) {
            $client = Client::lockForUpdate()->findOrFail($data['client_id']);
            $paidAt = isset($data['paid_at']) ? Carbon::parse($data['paid_at']) : now();

            $recordedAmount = (float) $data['amount'];

            // Auto-apply credit balance when requested and payment is completed/partial
            $creditApplied = 0.0;
            if (($data['apply_credit'] ?? false) && in_array($data['status'], ['completed', 'partial']) && $client->credit_balance > 0) {
                $beforeCredit = $recordedAmount;
                $recordedAmount = $invoiceService->applyCredit($client, $recordedAmount);
                $creditApplied = round($beforeCredit - $recordedAmount, 2);
            }

            // If partial payment results in overpayment against monthly bill, add excess to credit
            $creditAdded = 0.0;
            if ($data['status'] === 'partial' && $recordedAmount > $client->monthly_bill) {
                $excess = round($recordedAmount - (float) $client->monthly_bill, 2);
                $invoiceService->addCredit($client->fresh(), $excess, 'Overpayment credit', null);
                $creditAdded = $excess;
            }

            $payment = Payment::create([
                'client_id' => $client->id,
                'amount' => $recordedAmount > 0 ? $recordedAmount : (float) $data['amount'],
                'method' => $data['method'],
                'status' => $data['status'],
                'billing_period' => $data['billing_period'] ?? $paidAt->copy()->startOfMonth()->toDateString(),
                'paid_at' => in_array($data['status'], ['completed', 'partial']) ? $paidAt : null,
                'meta' => [
                    'note' => $data['note'] ?? null,
                    'txn_id' => $data['txn_id'] ?? null,
                    'credit_applied' => $creditApplied ?: null,
                    'credit_added' => $creditAdded ?: null,
                ],
            ]);

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

            // Generate invoice for completed or partial payments
            if (in_array($data['status'], ['completed', 'partial'])) {
                $invoiceService->createForPayment($payment);
            }
        });

        TenantCache::forgetDashboardAndClients();

        return back()->with('message', 'PAYMENT_RECORDED');
    }

    public function downloadInvoice(Invoice $invoice): HttpResponse
    {
        abort_unless($invoice->pdf_path && Storage::disk('local')->exists($invoice->pdf_path), 404);

        return response(
            Storage::disk('local')->get($invoice->pdf_path),
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.$invoice->invoice_number.'.pdf"',
            ]
        );
    }

    public function regenerateInvoicePdf(Invoice $invoice): RedirectResponse
    {
        $this->invoiceService->generatePdf($invoice);
        $invoice->update(['pdf_path' => "invoices/{$invoice->invoice_number}.pdf"]);

        return back()->with('message', 'INVOICE_PDF_REGENERATED');
    }

    public function invoices(Request $request): Response
    {
        $invoices = Invoice::with('client:id,full_name,pppoe_username')
            ->when($request->string('status')->toString(), fn ($q, $s) => $q->where('status', $s))
            ->latest('issued_at')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Billing/Invoices', [
            'invoices' => $invoices,
            'filters' => $request->only(['status']),
        ]);
    }

    public function adjustCredit(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'client_id' => 'required|integer|exists:clients,id',
            'amount' => 'required|numeric|min:0.01',
            'type' => 'required|in:credit,debit',
            'reason' => 'required|string|max:255',
        ]);

        DB::transaction(function () use ($data) {
            $client = Client::lockForUpdate()->findOrFail($data['client_id']);

            if ($data['type'] === 'credit') {
                $this->invoiceService->addCredit($client, (float) $data['amount'], $data['reason']);
            } else {
                abort_if($client->credit_balance < $data['amount'], 422, 'Insufficient credit balance.');
                $this->invoiceService->deductCredit($client, (float) $data['amount'], $data['reason']);
            }
        });

        return back()->with('message', 'CREDIT_ADJUSTED');
    }

    public function creditHistory(Request $request, Client $client): Response
    {
        $transactions = CreditTransaction::where('client_id', $client->id)
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Billing/CreditHistory', [
            'client' => $client->only('id', 'full_name', 'pppoe_username', 'credit_balance'),
            'transactions' => $transactions,
        ]);
    }
}
