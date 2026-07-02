<?php

namespace App\Services;

use Illuminate\Support\Collection;

class TenantPermissionService
{
    public function allowedPermissionNames(): Collection
    {
        $permissions = collect(config('isp_os.modules', []))
            ->flatMap(fn (string $module) => collect(config('isp_os.actions', []))->map(fn (string $action) => "{$module}.{$action}"));

        if (! tenancy()->initialized || ! tenant()) {
            return $permissions->values();
        }

        $enabledModuleSlugs = collect(tenant()->modules()
            ->where('enabled', true)
            ->whereHas('module', fn ($query) => $query->where('is_active', true))
            ->with('module:id,slug')
            ->get()
            ->pluck('module.slug'));

        $map = collect(config('isp_os.module_permission_map', []));

        return $permissions
            ->filter(function (string $permission) use ($enabledModuleSlugs, $map): bool {
                $permissionModule = str($permission)->before('.')->toString();

                return $enabledModuleSlugs->contains(function (string $enabledModule) use ($map, $permissionModule): bool {
                    return $enabledModule === $permissionModule
                        || collect($map->get($enabledModule, []))->contains($permissionModule);
                });
            })
            ->values();
    }

    public function permissionAllowedByLandlord(string $permission): bool
    {
        return $this->allowedPermissionNames()->contains($permission);
    }
}
