<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TenantFrontendSection extends Model
{
    protected $guarded = [];

    protected $casts = [
        'data' => 'array',
        'is_enabled' => 'boolean',
    ];
}
