<?php

namespace App\Models;

use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase;
    use HasDomains;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_SUSPENDED = 'suspended';
    public const STATUS_PENDING_SETUP = 'pending_setup';

    public static function getCustomColumns(): array
    {
        return [
            'id',
            'organization_name',
            'owner_name',
            'admin_email',
            'status',
            'database_name',
            'database_status',
            'domain_status',
            'suspended_message',
        ];
    }

    public function modules(): HasMany
    {
        return $this->hasMany(TenantModule::class);
    }

    public function packages(): HasMany
    {
        return $this->hasMany(TenantPackage::class);
    }

    public function settings(): HasMany
    {
        return $this->hasMany(TenantSetting::class);
    }

    public function provisioningLogs(): HasMany
    {
        return $this->hasMany(TenantProvisioningLog::class);
    }

    public function hasEnabledModule(string $slug): bool
    {
        return $this->modules()
            ->where('enabled', true)
            ->whereHas('module', fn ($query) => $query->where('slug', $slug)->where('is_active', true))
            ->exists();
    }

    public function isSuspended(): bool
    {
        return $this->status === self::STATUS_SUSPENDED;
    }
}
