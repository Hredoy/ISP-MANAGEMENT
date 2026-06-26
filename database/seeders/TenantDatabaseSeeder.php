<?php

namespace Database\Seeders;

use App\Models\TenantApplication;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TenantDatabaseSeeder
{
    /**
     * Seed the default admin user into the freshly provisioned tenant
     * database. Explicitly targets the 'tenant' connection so this is safe
     * to call regardless of the current default connection.
     *
     * Deliberately does NOT extend Illuminate\Database\Seeder: that base
     * class is invoked by `php artisan db:seed` via $container->call(),
     * which would silently resolve the required $application param to an
     * empty TenantApplication() instead of erroring.
     */
    public function run(TenantApplication $application): void
    {
        User::on('tenant')->firstOrCreate(
            ['email' => $application->email],
            [
                'name' => $application->contact_name,
                'password' => Hash::make(Str::random(32)),
            ]
        );
    }
}
