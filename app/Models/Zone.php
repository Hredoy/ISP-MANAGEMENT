<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Zone extends Model
{
    protected $guarded = [];

    /**
     * @return HasMany
     */
    public function subZones(): HasMany
    {
        return $this->hasMany(SubZone::class);
    }
}
