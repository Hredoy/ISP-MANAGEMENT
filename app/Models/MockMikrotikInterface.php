<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MockMikrotikInterface extends Model
{
    protected $guarded = [];

    protected $casts = [
        'running' => 'boolean',
        'disabled' => 'boolean',
        'rx_bytes' => 'integer',
        'tx_bytes' => 'integer',
        'rx_bps' => 'integer',
        'tx_bps' => 'integer',
    ];

    public function mikrotik(): BelongsTo
    {
        return $this->belongsTo(Mikrotik::class);
    }
}
