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
        $message = "Welcome {$client->full_name}! Your {$client->package_name} internet is now active. ".
            "PPPoE Username: {$client->pppoe_username}. Expiry: {$client->expiry_date}.";

        return $this->sendToClient($client, $message, 'Welcome SMS');
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function sendPaymentConfirmation(Client $client, float $amount, string $newExpiryDate): array
    {
        $message = "Payment of Tk {$amount} received for {$client->full_name}. ".
            "Your {$client->package_name} internet is active until {$newExpiryDate}. Thank you!";

        return $this->sendToClient($client, $message, 'Payment confirmation SMS');
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function sendExpiryReminder(Client $client, int $daysRemaining): array
    {
        $message = "Dear {$client->full_name}, your {$client->package_name} internet expires in ".
            "{$daysRemaining} day(s) on {$client->expiry_date}. Please pay Tk {$client->monthly_bill} to avoid interruption.";

        return $this->sendToClient($client, $message, 'Expiry reminder SMS');
    }

    /**
     * @return array{ok: bool, message: string}
     */
    private function sendToClient(Client $client, string $message, string $context): array
    {
        $gateway = SmsGateway::where('is_active', true)->first();

        if (! $gateway) {
            return ['ok' => false, 'message' => 'NO_ACTIVE_SMS_GATEWAY: Configure one in Integrations.'];
        }

        try {
            $result = $this->factory->make($gateway)->send($gateway->credentials, $client->phone_number, $message);

            if (! $result['ok']) {
                Log::error("{$context} failed: ".$result['message']);
            }

            return $result;
        } catch (Throwable $e) {
            Log::error("{$context} failed: ".$e->getMessage());

            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }
}
