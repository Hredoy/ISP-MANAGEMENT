<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TenantApplication extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_name',
        'owner_name',
        'slug',
        'district',
        'logo_path',
        'plan',
        'contact_name',
        'email',
        'phone',
        'address',
        'domain_request',
        'business_type',
        'package_request',
        'module_request',
        'notes',
        'rejection_reason',
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
        'converted_at',
    ];

    protected $casts = [
        'module_request' => 'array',
        'sms_sent_at' => 'datetime',
        'approved_at' => 'datetime',
        'converted_at' => 'datetime',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
