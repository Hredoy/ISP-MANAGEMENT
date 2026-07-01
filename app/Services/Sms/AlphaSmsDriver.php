<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Alpha SMS (Bangladesh) HTTP SMS gateway.
 * Credentials: api_key, sender_id, api_url (defaults to their send endpoint).
 */
class AlphaSmsDriver implements SmsDriverInterface
{
    public function send(array $credentials, string $to, string $message): array
    {
        $url = $credentials['api_url'] ?? 'https://api.sms.net.bd/sendsms';

        try {
            $response = Http::timeout(10)->get($url, [
                'api_key' => $credentials['api_key'] ?? '',
                'msg' => $message,
                'to' => $to,
                'sender_id' => $credentials['sender_id'] ?? '',
            ]);

            if (! $response->successful()) {
                return ['ok' => false, 'message' => "ALPHA_SMS_HTTP_{$response->status()}"];
            }

            return ['ok' => true, 'message' => 'SMS_SENT'];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }
}
