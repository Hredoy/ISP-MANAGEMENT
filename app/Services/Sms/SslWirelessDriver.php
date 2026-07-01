<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * SSL Wireless (Bangladesh) HTTP SMS gateway.
 * Credentials: api_token, sid, api_url (defaults to their v3 endpoint).
 */
class SslWirelessDriver implements SmsDriverInterface
{
    public function send(array $credentials, string $to, string $message): array
    {
        $url = $credentials['api_url'] ?? 'https://smsplus.sslwireless.com/api/v3/send-sms';

        try {
            $response = Http::timeout(10)->post($url, [
                'api_token' => $credentials['api_token'] ?? '',
                'sid' => $credentials['sid'] ?? '',
                'msisdn' => $to,
                'sms' => $message,
                'csms_id' => (string) now()->timestamp,
            ]);

            if (! $response->successful()) {
                return ['ok' => false, 'message' => "SSL_WIRELESS_HTTP_{$response->status()}"];
            }

            return ['ok' => true, 'message' => 'SMS_SENT'];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }
}
