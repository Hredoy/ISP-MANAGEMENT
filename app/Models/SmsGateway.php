<?php

namespace App\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class SmsGateway extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected function credentials(): Attribute
    {
        return Attribute::make(
            get: function (?string $value) {
                if ($value === null) {
                    return [];
                }

                try {
                    $decrypted = Crypt::decryptString($value);
                } catch (DecryptException) {
                    $decrypted = $value;
                }

                return json_decode($decrypted, true) ?? [];
            },
            set: fn (?array $value) => $value === null ? null : Crypt::encryptString(json_encode($value)),
        );
    }
}
