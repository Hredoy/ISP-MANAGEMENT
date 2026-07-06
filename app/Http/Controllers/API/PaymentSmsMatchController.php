<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Services\MikroTik\MikroTikServiceFactory;
use App\Services\SmsService;
use App\Support\TenantCache;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

class PaymentSmsMatchController extends Controller
{
    public function __invoke(Request $request, MikroTikServiceFactory $factory, SmsService $smsService): JsonResponse
    {
        $data = $request->validate([
            'provider' => 'required|string|in:bKash,Nagad,Rocket',
            'amount' => 'required|numeric|min:0.01',
            'sender_phone' => 'nullable|string',
            'transaction_id' => 'required|string',
            'sms_timestamp' => 'nullable|string',
            'raw_sender' => 'nullable|string',
            'raw_body' => 'nullable|string',
        ]);

        if (PaymentTransaction::where('transaction_id', $data['transaction_id'])->exists()) {
            return response()->json(['status' => 'duplicate', 'message' => 'Transaction already processed.']);
        }

        $meta = array_filter([
            'sender_phone' => $data['sender_phone'] ?? null,
            'sms_timestamp' => $data['sms_timestamp'] ?? null,
            'raw_sender' => $data['raw_sender'] ?? null,
            'raw_body' => $data['raw_body'] ?? null,
        ], fn ($value) => $value !== null);

        $clients = ($data['sender_phone'] ?? null)
            ? Client::where('phone_number', $data['sender_phone'])->get()
            : collect();

        if ($clients->count() > 1) {
            return $this->recordAndRespond($data, $meta, null, 'multiple_clients', 'Multiple clients share this phone number — resolve manually.');
        }

        if ($clients->isEmpty()) {
            return $this->recordAndRespond($data, $meta, null, 'unmatched', 'No client found for this phone number — held for manual review.');
        }

        $client = $clients->first();

        if ((float) $data['amount'] < (float) $client->monthly_bill) {
            return $this->recordAndRespond($data, $meta, $client, 'partial', 'Amount is less than the monthly bill — flagged as a partial payment.');
        }

        $newExpiryDate = Carbon::parse($client->expiry_date)->max(now())->addDays(30)->toDateString();
        $wasSuspended = $client->status === 'Suspended';

        if ($wasSuspended) {
            try {
                $factory->make($client->mikrotik)->enableUser($client->pppoe_username);
            } catch (Throwable $e) {
                Log::error('Auto-unsuspend on payment failed: '.$e->getMessage());
                $meta['router_warning'] = 'ROUTER_UNREACHABLE: Could not auto-unsuspend on MikroTik.';
            }
        }

        $client->update([
            'expiry_date' => $newExpiryDate,
            'status' => 'Active',
        ]);

        $smsResult = $smsService->sendPaymentConfirmation($client, (float) $data['amount'], $newExpiryDate);
        if (! $smsResult['ok']) {
            $meta['sms_warning'] = 'CONFIRMATION_SMS_FAILED: '.$smsResult['message'];
        }

        return $this->recordAndRespond($data, $meta, $client, 'completed', $meta['router_warning'] ?? $meta['sms_warning'] ?? null, $newExpiryDate);
    }

    private function recordAndRespond(
        array $data,
        array $meta,
        ?Client $client,
        string $status,
        ?string $message,
        ?string $newExpiryDate = null,
    ): JsonResponse {
        try {
            $payment = Payment::create([
                'client_id' => $client?->id,
                'amount' => $data['amount'],
                'method' => 'sms_'.strtolower($data['provider']),
                'status' => $status,
                'paid_at' => $status === 'completed' ? now() : null,
                'meta' => $meta,
            ]);

            PaymentTransaction::create([
                'payment_id' => $payment->id,
                'gateway' => $data['provider'],
                'transaction_id' => $data['transaction_id'],
                'amount' => $data['amount'],
                'status' => $status,
                'payload' => $meta,
            ]);
        } catch (QueryException|UniqueConstraintViolationException $e) {
            if (str_contains($e->getMessage(), 'transaction_id')) {
                return response()->json(['status' => 'duplicate', 'message' => 'Transaction already processed.']);
            }

            throw $e;
        }

        // Payment received: only the dashboard (revenue/recent-payments) and clients widgets
        // show payment-derived data - no need to flush devices/reports/ai_answer.
        TenantCache::forgetDashboardAndClients();

        return response()->json([
            'status' => $status,
            'message' => $message ?: ucfirst($status),
            'payment_id' => $payment->id,
            'new_expiry_date' => $newExpiryDate,
        ]);
    }
}
