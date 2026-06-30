<?php

namespace App\Http\Controllers;

use App\Models\TenantApplication;
use App\Services\TenantProvisioningService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TenantApplicationController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Tenant/Apply');
    }

    public function store(Request $request, TenantProvisioningService $provisioningService): RedirectResponse
    {
        if ($request->filled('custom_domain')) {
            $request->merge([
                'custom_domain' => Str::lower(trim(preg_replace('#^https?://#', '', $request->string('custom_domain')->toString()), " \t\n\r\0\x0B/")),
            ]);
        }

        $centralDomains = array_filter(array_map('trim', explode(',', env('CENTRAL_DOMAINS', '127.0.0.1,localhost,'.env('LANDLORD_DOMAIN', 'localhost')))));

        $validated = $request->validate([
            'organization_name' => ['required', 'string', 'max:255'],
            'contact_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'custom_domain' => [
                'nullable',
                'string',
                'max:255',
                'regex:/^(?!-)(?:[a-z0-9-]{1,63}\.)+[a-z]{2,63}$/',
                Rule::notIn($centralDomains),
                'unique:tenant_applications,custom_domain',
                'unique:domains,domain',
            ],
        ]);

        $application = TenantApplication::create([
            ...$validated,
            'slug' => $this->uniqueSlug($validated['organization_name']),
            'status' => 'pending',
        ]);

        $application = $provisioningService->approve($application);

        return redirect()->back()->with('success', 'Organization created. Tenant database and domains are ready: '.$application->subdomain);
    }

    private function uniqueSlug(string $organizationName): string
    {
        $base = Str::slug($organizationName);

        do {
            $slug = $base.'-'.Str::lower(Str::random(6));
        } while (TenantApplication::where('slug', $slug)->exists());

        return $slug;
    }
}
