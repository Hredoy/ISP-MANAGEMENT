<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class SmsService
{
    public function sendAdminCredentials(string $phone, string $loginUrl, string $email, string $password): void
    {
        Log::info('Admin credentials SMS queued.', [
            'phone' => $phone,
            'login_url' => $loginUrl,
            'email' => $email,
            'message' => "Your ISP portal is ready: {$loginUrl}. Login: {$email}. Password: {$password}",
        ]);
    }
}
