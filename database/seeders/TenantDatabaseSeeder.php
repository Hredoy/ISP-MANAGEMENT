<?php

namespace Database\Seeders;

use App\Models\Mikrotik;
use App\Models\Package;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TenantDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = tenant();
        $adminEmail = $tenant?->getAttribute('admin_email') ?: 'admin@example.com';
        $adminName = $tenant?->getAttribute('admin_name') ?: 'Tenant Admin';
        $organizationName = $tenant?->getAttribute('organization_name') ?: $tenant?->getTenantKey();

        User::firstOrCreate(
            ['email' => $adminEmail],
            [
                'name' => $adminName,
                'password' => Hash::make(Str::password(40)),
                'email_verified_at' => now(),
            ]
        );

        $router = Mikrotik::firstOrCreate(
            ['name' => 'Default Router'],
            [
                'host' => '127.0.0.1',
                'port' => 8728,
                'username' => 'admin',
                'password' => '',
                'sitename' => 'Main Office',
            ]
        );

        $packages = [
            ['name' => 'Starter 10 Mbps', 'rate_limit' => '10M/10M', 'price' => 500],
            ['name' => 'Standard 20 Mbps', 'rate_limit' => '20M/20M', 'price' => 800],
            ['name' => 'Premium 50 Mbps', 'rate_limit' => '50M/50M', 'price' => 1500],
        ];

        foreach ($packages as $package) {
            Package::firstOrCreate(
                ['name' => $package['name']],
                [
                    ...$package,
                    'mikrotik_id' => $router->id,
                    'description' => 'Default seeded ISP package',
                ]
            );
        }

        Setting::updateOrCreate(['key' => 'organization'], ['value' => [
            'name' => $organizationName,
            'tenant_id' => $tenant?->getTenantKey(),
        ]]);

        Setting::updateOrCreate(['key' => 'billing'], ['value' => [
            'currency' => 'BDT',
            'billing_day' => 1,
        ]]);
    }
}
