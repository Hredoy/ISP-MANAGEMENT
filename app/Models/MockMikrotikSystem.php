<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MockMikrotikSystem extends Model
{
    protected $table = 'mock_mikrotik_system';

    protected $guarded = [];

    protected $casts = [
        'cpu_load' => 'integer',
        'free_memory' => 'integer',
        'total_memory' => 'integer',
        'free_hdd_space' => 'integer',
        'total_hdd_space' => 'integer',
        'uptime_seconds' => 'integer',
    ];

    public function mikrotik(): BelongsTo
    {
        return $this->belongsTo(Mikrotik::class);
    }
}
