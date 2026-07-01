<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantContext
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! tenancy()->initialized) {
            return redirect()->route('landlord.dashboard');
        }

        return $next($request);
    }
}
