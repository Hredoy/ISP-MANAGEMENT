<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MockMikrotikQueueTree extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'disabled' => 'boolean',
        'priority' => 'integer',
    ];

    public function mikrotik(): BelongsTo
    {
        return $this->belongsTo(Mikrotik::class);
    }
}
