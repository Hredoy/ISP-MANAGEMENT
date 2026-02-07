<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Package extends Model
{
    protected $guarded = [];

    public function mikrotik(): BelongsTo
    {
        return $this->belongsTo(Mikrotik::class);
    }
}
