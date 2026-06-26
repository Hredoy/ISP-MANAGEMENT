<?php

namespace App\Http\Middleware;

use App\Models\TenantApplication;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class SetTenantDatabase
{
    public function handle(Request $request, Closure $next): Response
    {
        $host = $request->getHost();
        $landlordDomain = env('LANDLORD_DOMAIN', 'localhost');

        if ($host === $landlordDomain || $host === 'www.'.$landlordDomain) {
            return $next($request);
        }

        if (! str_ends_with($host, '.'.$landlordDomain)) {
            return $next($request);
        }

        $subdomain = str_replace('.'.$landlordDomain, '', $host);

        $tenant = TenantApplication::where('slug', $subdomain)
            ->where('status', 'approved')
            ->first();

        if (! $tenant || ! $tenant->database_name) {
            abort(404, 'Tenant not found or not approved yet.');
        }

        Config::set('database.connections.tenant.database', $tenant->database_name);
        Config::set('database.default', 'tenant');
        DB::purge('tenant');
        DB::reconnect('tenant');

        return $next($request);
    }
}
