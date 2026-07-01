<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrganizationApplicationRequest;
use App\Models\LandlordAuditLog;
use App\Models\Module;
use App\Models\TenantApplication;
use App\Services\TenantProvisioningService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class TenantApplicationController extends Controller
{
    public function create(TenantProvisioningService $provisioningService): Response
    {
        $provisioningService->ensureModulesSeeded();

        return Inertia::render('Tenant/Apply', [
            'modules' => Module::where('is_active', true)->orderBy('sort_order')->get(['name', 'slug']),
        ]);
    }

    public function store(StoreOrganizationApplicationRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $application = TenantApplication::create([
            ...$validated,
            'contact_name' => $validated['owner_name'],
            'plan' => $validated['package_request'] ?? $validated['plan'] ?? 'starter',
            'slug' => $this->uniqueSlug($validated['organization_name']),
            'status' => 'pending',
        ]);

        LandlordAuditLog::create([
            'tenant_application_id' => $application->id,
            'action' => 'application_submitted',
            'message' => 'Organization application submitted.',
            'properties' => ['source' => $request->user() ? 'landlord' : 'public'],
        ]);

        return redirect()->back()->with('success', 'Application submitted. The landlord team will review it shortly.');
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
