<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class TenantPackage extends Model
{
    protected $fillable = [
        'tenant_id',
        'name',
        'status',
        'limits',
    ];

    protected $casts = [
        'limits' => 'array',
    ];

    public function modules(): BelongsToMany
    {
        return $this->belongsToMany(Module::class, 'tenant_package_modules');
    }
}
