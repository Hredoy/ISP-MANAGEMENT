<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\TenantApplication;

class TenantProvisioningService
{
    public function approve(TenantApplication $application): TenantApplication
    {
        if ($application->status === 'approved') {
            return $application;
        }

        $slug = $application->slug;
        $tenantId = $application->tenant_id ?: $slug;
        $subdomain = $slug.'.'.env('LANDLORD_DOMAIN', 'localhost');

        $tenant = Tenant::find($tenantId) ?? Tenant::create([
            'id' => $tenantId,
            'organization_name' => $application->organization_name,
            'admin_name' => $application->contact_name,
            'admin_email' => $application->email,
        ]);

        $tenant->domains()->firstOrCreate(['domain' => $subdomain]);

        if ($application->custom_domain) {
            $tenant->domains()->firstOrCreate(['domain' => $application->custom_domain]);
        }

        $application->update([
            'slug' => $slug,
            'status' => 'approved',
            'tenant_id' => $tenant->id,
            'database_name' => $tenant->database()->getName(),
            'subdomain' => $subdomain,
            'approved_at' => now(),
        ]);

        return $application->fresh();
    }
}
