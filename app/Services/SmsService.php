<?php

namespace App\Services;

use App\Models\Client;
use App\Models\SmsGateway;
use App\Services\Sms\SmsGatewayDriverFactory;
use Illuminate\Support\Facades\Log;
use Throwable;

class SmsService
{
    public function __construct(private readonly SmsGatewayDriverFactory $factory) {}

    public function sendAdminCredentials(string $phone, string $loginUrl, string $email, string $password): void
    {
        Log::info('Admin credentials SMS queued.', [
            'phone' => $phone,
            'login_url' => $loginUrl,
            'email' => $email,
            'message' => "Your ISP portal is ready: {$loginUrl}. Login: {$email}. Password: {$password}",
        ]);
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function sendWelcomeMessage(Client $client): array
    {
        $gateway = SmsGateway::where('is_active', true)->first();

        if (! $gateway) {
            return ['ok' => false, 'message' => 'NO_ACTIVE_SMS_GATEWAY: Configure one in Integrations.'];
        }

        $message = "Welcome {$client->full_name}! Your {$client->package_name} internet is now active. ".
            "PPPoE Username: {$client->pppoe_username}. Expiry: {$client->expiry_date}.";

        try {
            $result = $this->factory->make($gateway)->send($gateway->credentials, $client->phone_number, $message);

            if (! $result['ok']) {
                Log::error('Welcome SMS failed: '.$result['message']);
            }

            return $result;
        } catch (Throwable $e) {
            Log::error('Welcome SMS failed: '.$e->getMessage());

            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }
}
