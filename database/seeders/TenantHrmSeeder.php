<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Designation;
use App\Models\LeaveType;
use App\Models\OfficeLocation;
use App\Models\Setting;
use App\Models\Shift;
use App\Models\Team;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class TenantHrmSeeder extends Seeder
{
    public function run(): void
    {
        $connection = Schema::connection('tenant')->hasTable('departments') ? 'tenant' : config('database.default');

        $this->seedRolesAndPermissions($connection);

        $department = Department::on($connection)->firstOrCreate(
            ['name' => 'Operations'],
            ['code' => 'OPS', 'description' => 'Default ISP operations department.']
        );

        Designation::on($connection)->firstOrCreate(
            ['name' => 'Network Technician'],
            ['department_id' => $department->id, 'code' => 'TECH']
        );

        Team::on($connection)->firstOrCreate(
            ['name' => 'Field Support'],
            ['department_id' => $department->id, 'description' => 'Default field support team.']
        );

        OfficeLocation::on($connection)->firstOrCreate(
            ['name' => 'Main Office'],
            ['address' => 'Main service center']
        );

        Shift::on($connection)->firstOrCreate(
            ['name' => 'General Shift'],
            ['starts_at' => '09:00:00', 'ends_at' => '18:00:00', 'grace_minutes' => 10]
        );

        foreach ([
            ['Casual Leave', 10, true],
            ['Sick Leave', 14, true],
            ['Unpaid Leave', 0, false],
        ] as [$name, $days, $paid]) {
            LeaveType::on($connection)->firstOrCreate(
                ['name' => $name],
                ['days_per_year' => $days, 'is_paid' => $paid]
            );
        }

        Setting::on($connection)->updateOrCreate(['key' => 'hrm'], ['value' => [
            'attendance' => ['late_grace_minutes' => 10, 'weekends' => ['Friday']],
            'leave' => ['approval_flow' => ['HR Manager', 'Tenant Admin']],
            'payroll' => ['currency' => 'BDT', 'salary_day' => 1, 'overtime_enabled' => false],
        ]]);
    }

    private function seedRolesAndPermissions(string $connection): void
    {
        $modules = config('isp_os.modules', []);
        $actions = config('isp_os.actions', []);
        $permissions = collect($modules)
            ->flatMap(fn (string $module) => collect($actions)->map(fn (string $action) => "{$module}.{$action}"))
            ->values();
        $matrix = collect(config('isp_os.role_permissions', []))
            ->only(config('isp_os.tenant_default_roles', []));
        $now = now();

        foreach ($permissions as $permission) {
            foreach (['web', 'api'] as $guard) {
                if (! DB::connection($connection)->table('permissions')->where('name', $permission)->where('guard_name', $guard)->exists()) {
                    DB::connection($connection)->table('permissions')->insert([
                        'id' => (string) Str::uuid(),
                        'name' => $permission,
                        'guard_name' => $guard,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }

        foreach ($matrix as $roleName => $allowed) {
            foreach (['web', 'api'] as $guard) {
                if (! DB::connection($connection)->table('roles')->where('name', $roleName)->where('guard_name', $guard)->exists()) {
                    DB::connection($connection)->table('roles')->insert([
                        'id' => (string) Str::uuid(),
                        'name' => $roleName,
                        'guard_name' => $guard,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }

                $roleId = DB::connection($connection)->table('roles')->where('name', $roleName)->where('guard_name', $guard)->value('id');
                $expanded = $this->expandPermissions($allowed, $permissions);
                $permissionIds = DB::connection($connection)->table('permissions')
                    ->where('guard_name', $guard)
                    ->whereIn('name', $expanded)
                    ->pluck('id');

                DB::connection($connection)->table('role_has_permissions')->where('role_id', $roleId)->delete();

                foreach ($permissionIds as $permissionId) {
                    DB::connection($connection)->table('role_has_permissions')->insertOrIgnore([
                        'permission_id' => $permissionId,
                        'role_id' => $roleId,
                    ]);
                }
            }
        }

        $tenantAdminRoleId = DB::connection($connection)->table('roles')
            ->where('name', 'Tenant Admin')
            ->where('guard_name', 'web')
            ->value('id');

        if ($tenantAdminRoleId) {
            DB::connection($connection)->table('users')->orderBy('id')->get(['id'])->each(function ($user) use ($connection, $tenantAdminRoleId): void {
                DB::connection($connection)->table('model_has_roles')->insertOrIgnore([
                    'role_id' => $tenantAdminRoleId,
                    'model_type' => \App\Models\User::class,
                    'model_id' => $user->id,
                ]);
            });
        }
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
