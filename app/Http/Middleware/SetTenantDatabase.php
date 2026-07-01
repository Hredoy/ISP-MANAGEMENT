<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Models\TenantApplication;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
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
        $tenant = $domain?->tenant ?? $this->tenantFromWildcardLandlordSubdomain($host);

        if (! $tenant) {
            abort(404, 'Tenant not found or not approved yet.');
        }

        tenancy()->initialize($tenant);

        try {
            return $next($request);
        } finally {
            tenancy()->end();
        }
    }

    private function tenantFromWildcardLandlordSubdomain(string $host): ?Tenant
    {
        $landlordDomain = env('LANDLORD_DOMAIN', 'localhost');

        if (! Str::endsWith($host, '.'.$landlordDomain)) {
            return null;
        }

        $label = Str::beforeLast($host, '.'.$landlordDomain);

        if ($label === '' || Str::contains($label, '.')) {
            return null;
        }

        $application = TenantApplication::query()
            ->where('status', 'converted')
            ->where(function ($query) use ($host, $label) {
                $query->where('subdomain', $host)
                    ->orWhere('slug', $label)
                    ->orWhere('domain_request', $label)
                    ->orWhere('domain_request', $label.'.'.env('LANDLORD_DOMAIN'))
                    ->orWhere('custom_domain', $label.'.'.env('LANDLORD_DOMAIN'));
            })
            ->first();

        return $application?->tenant_id ? Tenant::find($application->tenant_id) : Tenant::find($label);
    }
}
