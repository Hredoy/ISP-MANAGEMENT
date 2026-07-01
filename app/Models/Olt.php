<?php

namespace App\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class Olt extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
        'last_ping' => 'datetime',
    ];

    public function clients()
    {
        return $this->hasMany(Client::class);
    }

    protected function password(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $this->decryptCredential($value),
            set: fn (?string $value) => $this->encryptCredential($value),
        );
    }

    protected function snmpCommunity(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $this->decryptCredential($value),
            set: fn (?string $value) => $this->encryptCredential($value),
        );
    }

    private function encryptCredential(?string $value): ?string
    {
        return $value === null || $value === '' ? null : Crypt::encryptString($value);
    }

    private function decryptCredential(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (DecryptException) {
            return $value;
        }
    }
}
