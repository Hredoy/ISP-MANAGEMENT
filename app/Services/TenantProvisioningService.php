<?php

namespace App\Services;

use App\Models\TenantApplication;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TenantProvisioningService
{
    public function approve(TenantApplication $application): TenantApplication
    {
        if ($application->status === 'approved') {
            return $application;
        }

        $slug = Str::slug($application->organization_name);
        $databaseName = env('TENANT_DB_PREFIX', 'production_').$slug;
        $subdomain = $slug.'.'.env('LANDLORD_DOMAIN', 'localhost');

        if (config('database.default') !== 'mysql') {
            throw new \RuntimeException('Tenant provisioning currently supports mysql connection only.');
        }

        DB::statement(sprintf('CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci', $databaseName));

        Config::set('database.connections.tenant.database', $databaseName);
        DB::purge('tenant');

        Artisan::call('migrate', [
            '--database' => 'tenant',
            '--force' => true,
        ]);

        $application->update([
            'slug' => $slug,
            'status' => 'approved',
            'database_name' => $databaseName,
            'subdomain' => $subdomain,
            'approved_at' => now(),
        ]);

        return $application->fresh();
    }
}
