<?php

namespace App\Http\Middleware;

use App\Services\TenantPermissionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        if (tenancy()->initialized && ! app(TenantPermissionService::class)->permissionAllowedByLandlord($permission)) {
            return $this->deny($request, 'This permission is disabled by landlord module access.');
        }

        if (! $request->user()?->can($permission)) {
            return $this->deny($request, 'You do not have permission to access this resource.');
        }

        return $next($request);
    }

    private function deny(Request $request, string $message): Response
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 403);
        }

        abort(403, $message);
    }
}
