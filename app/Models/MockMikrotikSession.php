<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MockMikrotikSession extends Model
{
    protected $guarded = [];

    protected $casts = [
        'uptime_seconds' => 'integer',
    ];

    public function mikrotik(): BelongsTo
    {
        return $this->belongsTo(Mikrotik::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(MockMikrotikUser::class, 'mock_mikrotik_user_id');
    }
}
