<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SmsDeviceToken extends Model
{
    protected $guarded = [];

    protected $hidden = ['token_hash'];

    protected $casts = [
        'is_active' => 'boolean',
        'last_used_at' => 'datetime',
    ];

    /**
     * Creates a device token record and returns the one-time plaintext value
     * (only ever available at creation time, same pattern as Sanctum tokens).
     * Hashed with sha256 rather than bcrypt: these are high-entropy random
     * tokens (not user-chosen secrets), so a fast indexed lookup is safe and
     * matches how Laravel Sanctum stores its own API tokens.
     */
    public static function generate(?string $label = null, ?string $connection = null): array
    {
        $plainToken = Str::random(40);

        $record = new static([
            'label' => $label,
            'token_hash' => hash('sha256', $plainToken),
            'is_active' => true,
        ]);

        if ($connection) {
            $record->setConnection($connection);
        }

        $record->save();

        return [$record, $plainToken];
    }

    public static function findActiveByPlainToken(string $plainToken): ?self
    {
        return static::where('token_hash', hash('sha256', $plainToken))
            ->where('is_active', true)
            ->first();
    }
}
