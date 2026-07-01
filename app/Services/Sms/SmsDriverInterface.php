<?php

namespace App\Services\Sms;

interface SmsDriverInterface
{
    /**
     * @param  array<string, mixed>  $credentials
     * @return array{ok: bool, message: string}
     */
    public function send(array $credentials, string $to, string $message): array;
}
