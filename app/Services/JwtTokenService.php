<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Str;
use RuntimeException;

class JwtTokenService
{
    public function issueFor(User $user, array $claims = []): string
    {
        $now = now();
        $ttl = (int) config('isp_os.jwt.ttl_minutes', 43200);

        $payload = array_merge([
            'iss' => config('isp_os.jwt.issuer'),
            'sub' => (string) $user->getKey(),
            'email' => $user->email,
            'roles' => method_exists($user, 'getRoleNames') ? $user->getRoleNames()->values()->all() : [],
            'iat' => $now->timestamp,
            'nbf' => $now->timestamp,
            'exp' => $now->copy()->addMinutes($ttl)->timestamp,
            'jti' => (string) Str::uuid(),
        ], $claims);

        return $this->encode($payload);
    }

    public function encode(array $payload): string
    {
        $secret = $this->secret();
        $header = ['typ' => 'JWT', 'alg' => 'HS256'];
        $segments = [
            $this->base64UrlEncode(json_encode($header, JSON_THROW_ON_ERROR)),
            $this->base64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR)),
        ];

        $signature = hash_hmac('sha256', implode('.', $segments), $secret, true);
        $segments[] = $this->base64UrlEncode($signature);

        return implode('.', $segments);
    }

    private function secret(): string
    {
        $secret = (string) config('isp_os.jwt.secret');

        if (str_starts_with($secret, 'base64:')) {
            $decoded = base64_decode(substr($secret, 7), true);
            $secret = $decoded !== false ? $decoded : $secret;
        }

        if ($secret === '') {
            throw new RuntimeException('JWT_SECRET or APP_KEY must be configured before issuing mobile tokens.');
        }

        return $secret;
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
