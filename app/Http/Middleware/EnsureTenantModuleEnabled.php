<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantModuleEnabled
{
    public function handle(Request $request, Closure $next, string $module): Response
    {
        if (tenancy()->initialized && ! tenant()->hasEnabledModule($module)) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'This module is disabled for the tenant.'], 403);
            }

            abort(403, 'This module is disabled for the tenant.');
        }

        return $next($request);
    }
}
