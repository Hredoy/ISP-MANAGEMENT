<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MockMikrotikQueue extends Model
{
    protected $guarded = [];

    protected $casts = [
        'disabled' => 'boolean',
        'bytes_in' => 'integer',
        'bytes_out' => 'integer',
    ];

    public function mikrotik(): BelongsTo
    {
        return $this->belongsTo(Mikrotik::class);
    }
}
