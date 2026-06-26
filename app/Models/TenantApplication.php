<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TenantApplication extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_name',
        'slug',
        'contact_name',
        'email',
        'phone',
        'status',
        'database_name',
        'subdomain',
        'approved_at',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
    ];
}
