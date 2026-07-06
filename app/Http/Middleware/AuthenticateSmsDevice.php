<?php

namespace App\Http\Middleware;

use App\Models\SmsDeviceToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateSmsDevice
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        $device = $token ? SmsDeviceToken::findActiveByPlainToken($token) : null;

        if (! $device) {
            return response()->json(['message' => 'Invalid or missing device token.'], 401);
        }

        $device->update(['last_used_at' => now()]);

        return $next($request);
    }
}
