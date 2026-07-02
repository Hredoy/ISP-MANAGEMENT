<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\Role;
use App\Services\TenantPermissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TenantRolePermissionController extends Controller
{
    public function index(TenantPermissionService $permissionService): Response
    {
        $allowed = $permissionService->allowedPermissionNames();

        return Inertia::render('Tenant/Roles/Index', [
            'roles' => Role::with('permissions')
                ->whereIn('name', config('isp_os.tenant_default_roles', []))
                ->where('guard_name', 'web')
                ->orderBy('name')
                ->get(),
            'permissions' => Permission::where('guard_name', 'web')
                ->whereIn('name', $allowed)
                ->orderBy('name')
                ->get()
                ->groupBy(fn ($permission) => str($permission->name)->before('.')->toString()),
            'disabledPermissionNames' => Permission::where('guard_name', 'web')
                ->whereNotIn('name', $allowed)
                ->pluck('name'),
        ]);
    }

    public function update(Request $request, Role $role, TenantPermissionService $permissionService): RedirectResponse
    {
        abort_unless(in_array($role->name, config('isp_os.tenant_default_roles', []), true), 403);

        $data = $request->validate([
            'permissions' => ['array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $allowed = $permissionService->allowedPermissionNames();
        $permissions = collect($data['permissions'] ?? [])
            ->intersect($allowed)
            ->values()
            ->all();

        $role->syncPermissions($permissions);

        return back()->with('success', 'Role permissions updated.');
    }
}
