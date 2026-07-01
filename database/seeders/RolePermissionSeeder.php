<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $modules = config('isp_os.modules', []);
        $actions = config('isp_os.actions', []);
        $matrix = config('isp_os.role_permissions', []);

        $permissions = collect($modules)
            ->flatMap(fn (string $module) => collect($actions)->map(fn (string $action) => "{$module}.{$action}"))
            ->values();

        $permissions->each(fn (string $name) => Permission::findOrCreate($name, 'web'));
        $permissions->each(fn (string $name) => Permission::findOrCreate($name, 'api'));

        foreach ($matrix as $roleName => $allowed) {
            foreach (['web', 'api'] as $guard) {
                $role = Role::findOrCreate($roleName, $guard);
                $role->syncPermissions($this->expandPermissions($allowed, $permissions));
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function expandPermissions(array $allowed, $permissions): array
    {
        if (in_array('*', $allowed, true)) {
            return $permissions->all();
        }

        return collect($allowed)
            ->flatMap(function (string $permission) use ($permissions) {
                if (str_ends_with($permission, '.*')) {
                    $prefix = substr($permission, 0, -1);

                    return $permissions->filter(fn (string $name) => str_starts_with($name, $prefix));
                }

                return [$permission];
            })
            ->unique()
            ->values()
            ->all();
    }
}
