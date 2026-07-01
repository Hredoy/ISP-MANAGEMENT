<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Generic templated HTTP SMS gateway for any provider not covered by a
 * dedicated driver. Credentials: url, method (get|post), headers (object),
 * payload_template (JSON string with {{to}} / {{message}} placeholders).
 */
class CustomHttpDriver implements SmsDriverInterface
{
    public function send(array $credentials, string $to, string $message): array
    {
        $url = $credentials['url'] ?? null;

        if (! $url) {
            return ['ok' => false, 'message' => 'CUSTOM_GATEWAY_URL_NOT_CONFIGURED'];
        }

        $method = strtolower($credentials['method'] ?? 'post');
        $headers = $credentials['headers'] ?? [];
        $template = $credentials['payload_template'] ?? '{"to":"{{to}}","message":"{{message}}"}';

        $payloadJson = str_replace(
            ['{{to}}', '{{message}}'],
            [addslashes($to), addslashes($message)],
            $template
        );

        $payload = json_decode($payloadJson, true) ?? [];

        try {
            $request = Http::withHeaders($headers)->timeout(10);
            $response = $method === 'get' ? $request->get($url, $payload) : $request->post($url, $payload);

            if (! $response->successful()) {
                return ['ok' => false, 'message' => "CUSTOM_GATEWAY_HTTP_{$response->status()}"];
            }

            return ['ok' => true, 'message' => 'SMS_SENT'];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }
}
