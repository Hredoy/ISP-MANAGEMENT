<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! tenancy()->initialized) {
            return $next($request);
        }

        $tenant = tenant();

        if ($tenant?->status === 'suspended') {
            auth()->guard('web')->logout();

            if ($request->expectsJson()) {
                return response()->json(['message' => $tenant->suspended_message ?: 'This tenant account is suspended.'], 403);
            }

            abort(403, $tenant->suspended_message ?: 'This tenant account is suspended.');
        }

        if ($tenant?->status && $tenant->status !== 'active') {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'This tenant is not active yet.'], 403);
            }

            abort(403, 'This tenant is not active yet.');
        }

        return $next($request);
    }
}
