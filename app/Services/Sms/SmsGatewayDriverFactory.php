<?php

namespace App\Services\Sms;

use App\Models\SmsGateway;
use InvalidArgumentException;

class SmsGatewayDriverFactory
{
    public function make(SmsGateway $gateway): SmsDriverInterface
    {
        return match ($gateway->provider) {
            'ssl_wireless' => new SslWirelessDriver,
            'alpha_sms' => new AlphaSmsDriver,
            'twilio' => new TwilioDriver,
            'custom' => new CustomHttpDriver,
            default => throw new InvalidArgumentException("Unsupported SMS provider: {$gateway->provider}"),
        };
    }
}
