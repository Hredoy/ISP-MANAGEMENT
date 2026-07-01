<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantModule extends Model
{
    protected $fillable = [
        'tenant_id',
        'module_id',
        'enabled',
        'enabled_at',
        'disabled_at',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'enabled_at' => 'datetime',
        'disabled_at' => 'datetime',
    ];

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
