<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TenantProvisioningLog extends Model
{
    protected $fillable = [
        'tenant_id',
        'tenant_application_id',
        'step',
        'status',
        'message',
        'context',
    ];

    protected $casts = [
        'context' => 'array',
    ];
}
