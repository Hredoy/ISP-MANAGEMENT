<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubZone extends Model
{
    protected $guarded = [];

    /**
     * @return BelongsTo
     */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }
}
