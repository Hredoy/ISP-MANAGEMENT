<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Stancl\Tenancy\Database\Models\Domain;
use Symfony\Component\HttpFoundation\Response;

class SetTenantDatabase
{
    public function handle(Request $request, Closure $next): Response
    {
        $host = $request->getHost();
        $appHost = parse_url(config('app.url'), PHP_URL_HOST);
        $centralDomains = array_values(array_unique(array_filter([
            $appHost,
            ...array_map('trim', explode(',', env('CENTRAL_DOMAINS', '127.0.0.1,localhost,'.env('LANDLORD_DOMAIN', 'localhost')))),
        ])));

        if (in_array($host, $centralDomains, true) || in_array(preg_replace('/^www\./', '', $host), $centralDomains, true)) {
            return $next($request);
        }

        $domain = Domain::where('domain', $host)->first();

        if (! $domain) {
            abort(404, 'Tenant not found or not approved yet.');
        }

        tenancy()->initialize($domain->tenant);

        try {
            return $next($request);
        } finally {
            tenancy()->end();
        }
    }
}
