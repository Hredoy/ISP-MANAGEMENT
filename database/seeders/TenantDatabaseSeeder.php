<?php

namespace Database\Seeders;

use App\Models\Mikrotik;
use App\Models\Package;
use App\Models\Setting;
use App\Models\TenantApplication;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TenantDatabaseSeeder extends Seeder
{
    public function run(?TenantApplication $application = null): void
    {
        $tenant = tenant();
        $adminEmail = $application?->email ?: $tenant?->getAttribute('admin_email') ?: 'admin@example.com';
        $adminName = $application?->contact_name ?: $tenant?->getAttribute('admin_name') ?: 'Tenant Admin';
        $organizationName = $application?->organization_name ?: $tenant?->getAttribute('organization_name') ?: $tenant?->getTenantKey();

        User::on('tenant')->firstOrCreate(
            ['email' => $adminEmail],
            [
                'name' => $adminName,
                'password' => Hash::make(Str::password(40)),
                'email_verified_at' => now(),
            ]
        );

        $router = Mikrotik::on('tenant')->firstOrCreate(
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
            ['name' => 'Nano', 'rate_limit' => '5M/5M', 'price' => 500],
            ['name' => 'Starter', 'rate_limit' => '10M/10M', 'price' => 800],
            ['name' => 'Pro', 'rate_limit' => '20M/20M', 'price' => 1200],
            ['name' => 'Enterprise', 'rate_limit' => '50M/50M', 'price' => 1500],
        ];

        foreach ($packages as $package) {
            Package::on('tenant')->firstOrCreate(
                ['name' => $package['name']],
                [
                    ...$package,
                    'mikrotik_id' => $router->id,
                    'description' => 'Default seeded ISP package',
                ]
            );
        }

        Setting::on('tenant')->updateOrCreate(['key' => 'organization'], ['value' => [
            'name' => $organizationName,
            'tenant_id' => $tenant?->getTenantKey(),
        ]]);

        Setting::on('tenant')->updateOrCreate(['key' => 'billing'], ['value' => [
            'currency' => 'BDT',
            'billing_day' => 1,
        ]]);

        $this->call(TenantHrmSeeder::class);
    }
}
