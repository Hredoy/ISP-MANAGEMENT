<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Client extends Model
{
    protected $guarded = [];

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }
    public function sub_zone(): BelongsTo
    {
        return $this->belongsTo(SubZone::class);
    }
}
