<?php

namespace App\Services;

use App\Models\Client;
use App\Models\CreditTransaction;
use App\Models\Invoice;
use App\Models\Payment;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class InvoiceService
{
    /**
     * Generate a sequential invoice number like INV-2026-0001.
     * Uses a DB lock to avoid duplicates under concurrent requests.
     */
    public function nextInvoiceNumber(): array
    {
        $year = now()->year;
        $prefix = "INV-{$year}-";

        $max = Invoice::where('invoice_number', 'like', "{$prefix}%")
            ->lockForUpdate()
            ->max('sequence_number') ?? 0;

        $seq = $max + 1;

        return [
            'sequence_number' => $seq,
            'invoice_number' => $prefix.str_pad($seq, 4, '0', STR_PAD_LEFT),
        ];
    }

    /**
     * Create an Invoice record for a completed or partial payment,
     * generate its PDF, and persist the file path.
     */
    public function createForPayment(Payment $payment): Invoice
    {
        $payment->loadMissing('client');
        $client = $payment->client;

        ['sequence_number' => $seq, 'invoice_number' => $number] = $this->nextInvoiceNumber();

        $taxRate = (float) config('billing.tax_rate', 0);
        $subtotal = (float) $payment->amount;
        $taxAmount = round($subtotal * $taxRate / 100, 2);
        $total = round($subtotal + $taxAmount, 2);

        $invoice = Invoice::create([
            'id' => Str::uuid(),
            'client_id' => $client?->id,
            'payment_id' => $payment->id,
            'sequence_number' => $seq,
            'invoice_number' => $number,
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'total' => $total,
            'status' => $payment->status === 'completed' ? 'paid' : 'issued',
            'issued_at' => now(),
            'meta' => [
                'client_name' => $client?->full_name,
                'pppoe_username' => $client?->pppoe_username,
                'package_name' => $client?->package_name,
                'period' => $payment->billing_period?->format('F Y'),
                'method' => $payment->method,
                'txn_id' => data_get($payment->meta, 'txn_id'),
                'note' => data_get($payment->meta, 'note'),
            ],
        ]);

        $pdfPath = $this->generatePdf($invoice);
        $invoice->update(['pdf_path' => $pdfPath]);

        return $invoice;
    }

    /**
     * Render the invoice Blade template to PDF and store it.
     * Returns the storage path.
     */
    public function generatePdf(Invoice $invoice): string
    {
        $invoice->loadMissing('client');

        $pdf = Pdf::loadView('invoices.pdf', ['invoice' => $invoice]);

        $path = "invoices/{$invoice->invoice_number}.pdf";
        Storage::disk('local')->put($path, $pdf->output());

        return $path;
    }

    /**
     * Apply available credit to reduce the effective amount due,
     * record credit debit transaction, return adjusted amount.
     */
    public function applyCredit(Client $client, float $amountDue): float
    {
        if ($client->credit_balance <= 0) {
            return $amountDue;
        }

        $applied = min((float) $client->credit_balance, $amountDue);
        $newBalance = round((float) $client->credit_balance - $applied, 2);

        $client->lockForUpdate();
        $client->update(['credit_balance' => $newBalance]);

        CreditTransaction::create([
            'id' => Str::uuid(),
            'client_id' => $client->id,
            'amount' => $applied,
            'type' => 'debit',
            'reason' => 'Applied to payment',
            'balance_after' => $newBalance,
        ]);

        return round($amountDue - $applied, 2);
    }

    /**
     * Add credit to a client's wallet (ISP manual adjustment or overpayment).
     */
    public function addCredit(Client $client, float $amount, string $reason, ?string $paymentId = null): CreditTransaction
    {
        $newBalance = round((float) $client->credit_balance + $amount, 2);
        $client->update(['credit_balance' => $newBalance]);

        return CreditTransaction::create([
            'id' => Str::uuid(),
            'client_id' => $client->id,
            'payment_id' => $paymentId,
            'amount' => $amount,
            'type' => 'credit',
            'reason' => $reason,
            'balance_after' => $newBalance,
        ]);
    }

    /**
     * Deduct credit from a client's wallet (ISP manual adjustment).
     */
    public function deductCredit(Client $client, float $amount, string $reason): CreditTransaction
    {
        $newBalance = max(0, round((float) $client->credit_balance - $amount, 2));
        $client->update(['credit_balance' => $newBalance]);

        return CreditTransaction::create([
            'id' => Str::uuid(),
            'client_id' => $client->id,
            'amount' => $amount,
            'type' => 'debit',
            'reason' => $reason,
            'balance_after' => $newBalance,
        ]);
    }
}
