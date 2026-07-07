<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase;
    use HasDomains;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const STATUS_SUSPENDED = 'suspended';

    public const STATUS_PENDING_SETUP = 'pending_setup';

    public const SSL_NOT_APPLICABLE = 'not_applicable';

    public const SSL_PENDING_DNS = 'pending_dns';

    public const SSL_ISSUING = 'issuing';

    public const SSL_ACTIVE = 'active';

    public const SSL_FAILED = 'failed';

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
            'ssl_status',
            'ssl_last_checked_at',
            'ssl_issued_at',
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

    /**
     * The tenant's custom domain, if any - i.e. whichever of its domains is not the free
     * {slug}.LANDLORD_DOMAIN subdomain (which never needs DNS/SSL provisioning).
     */
    public function customDomainName(): ?string
    {
        $platformDomain = env('LANDLORD_DOMAIN', 'yourplatform.com');

        return $this->domains
            ->pluck('domain')
            ->first(fn (string $domain) => ! str_ends_with($domain, '.'.$platformDomain));
    }
}
