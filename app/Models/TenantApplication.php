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
        'district',
        'logo_path',
        'plan',
        'contact_name',
        'email',
        'phone',
        'mikrotik_ip',
        'olt_ip',
        'olt_brand',
        'custom_domain',
        'status',
        'tenant_id',
        'database_name',
        'subdomain',
        'admin_email',
        'sms_sent_at',
        'approved_at',
    ];

    protected $casts = [
        'sms_sent_at' => 'datetime',
        'approved_at' => 'datetime',
    ];
}
