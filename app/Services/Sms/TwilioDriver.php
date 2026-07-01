<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Twilio REST API SMS gateway.
 * Credentials: account_sid, auth_token, from_number.
 */
class TwilioDriver implements SmsDriverInterface
{
    public function send(array $credentials, string $to, string $message): array
    {
        $sid = $credentials['account_sid'] ?? '';
        $token = $credentials['auth_token'] ?? '';
        $from = $credentials['from_number'] ?? '';

        try {
            $response = Http::asForm()
                ->withBasicAuth($sid, $token)
                ->timeout(10)
                ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", [
                    'To' => $to,
                    'From' => $from,
                    'Body' => $message,
                ]);

            if (! $response->successful()) {
                return ['ok' => false, 'message' => "TWILIO_HTTP_{$response->status()}"];
            }

            return ['ok' => true, 'message' => 'SMS_SENT'];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }
}
