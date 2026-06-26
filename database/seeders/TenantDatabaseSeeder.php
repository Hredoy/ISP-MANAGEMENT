<?php

namespace Database\Seeders;

use App\Models\TenantApplication;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TenantDatabaseSeeder extends Seeder
{
    /**
     * Seed the default admin user into the freshly provisioned tenant
     * database. Explicitly targets the 'tenant' connection so this is safe
     * to call regardless of the current default connection.
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
