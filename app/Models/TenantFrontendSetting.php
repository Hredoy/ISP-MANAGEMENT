<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TenantFrontendSetting extends Model
{
    protected $guarded = [];

    protected $casts = [
        'value' => 'array',
    ];
}
