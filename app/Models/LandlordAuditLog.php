<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LandlordAuditLog extends Model
{
    protected $fillable = [
        'actor_type',
        'actor_id',
        'tenant_id',
        'tenant_application_id',
        'action',
        'message',
        'properties',
    ];

    protected $casts = [
        'properties' => 'array',
    ];
}
