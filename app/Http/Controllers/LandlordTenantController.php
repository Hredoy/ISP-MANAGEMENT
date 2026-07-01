<?php

namespace App\Http\Controllers;

use App\Http\Requests\RejectOrganizationApplicationRequest;
use App\Http\Requests\StoreOrganizationApplicationRequest;
use App\Http\Requests\UpdateTenantModulesRequest;
use App\Http\Requests\UpdateTenantStatusRequest;
use App\Models\Module;
use App\Models\Tenant;
use App\Models\TenantApplication;
use App\Services\TenantProvisioningService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class LandlordTenantController extends Controller
{
    public function index(): RedirectResponse
    {
        return redirect()->route('landlord.dashboard');
    }

    public function dashboard(TenantProvisioningService $provisioningService): Response
    {
        $provisioningService->ensureModulesSeeded();

        $tenants = Tenant::with(['domains', 'modules.module'])
            ->latest()
            ->get();
        $applications = TenantApplication::latest()->get();

        return Inertia::render('Landlord/Dashboard', [
            'tenants' => $tenants,
            'stats' => $this->stats($tenants, $applications),
        ]);
    }

    public function applications(TenantProvisioningService $provisioningService): Response
    {
        $provisioningService->ensureModulesSeeded();

        return Inertia::render('Landlord/Applications', [
            'applications' => TenantApplication::latest()->get(),
            'modules' => Module::where('is_active', true)->orderBy('sort_order')->get(['name', 'slug']),
        ]);
    }

    public function tenants(TenantProvisioningService $provisioningService): Response
    {
        $provisioningService->ensureModulesSeeded();

        return Inertia::render('Landlord/Tenants', [
            'tenants' => Tenant::with(['domains', 'modules.module', 'provisioningLogs' => fn ($query) => $query->latest()->limit(8)])
                ->latest()
                ->get(),
            'modules' => Module::where('is_active', true)->orderBy('sort_order')->get(['name', 'slug']),
        ]);
    }

    public function store(StoreOrganizationApplicationRequest $request): RedirectResponse
    {
        TenantApplication::create([
            ...$request->validated(),
            'contact_name' => $request->validated('owner_name'),
            'plan' => $request->validated('package_request') ?? $request->validated('plan') ?? 'starter',
            'slug' => $this->uniqueSlug($request->validated('organization_name')),
            'status' => 'pending',
        ]);

        return redirect()->back()->with('success', 'Application created.');
    }

    public function approve(TenantApplication $application, TenantProvisioningService $provisioningService): RedirectResponse
    {
        $provisioningService->approve($application);

        return redirect()->back()->with('success', 'Application approved. It is ready to convert into a tenant.');
    }

    public function reject(RejectOrganizationApplicationRequest $request, TenantApplication $application, TenantProvisioningService $provisioningService): RedirectResponse
    {
        $provisioningService->reject($application, $request->validated('reason'));

        return redirect()->back()->with('success', 'Application rejected.');
    }

    public function convert(TenantApplication $application, TenantProvisioningService $provisioningService): RedirectResponse
    {
        $provisioningService->convert($application);

        return redirect()->back()->with('success', 'Tenant converted and provisioned successfully.');
    }

    public function updateModules(UpdateTenantModulesRequest $request, Tenant $tenant, TenantProvisioningService $provisioningService): RedirectResponse
    {
        $provisioningService->setTenantModules($tenant, $request->validated('modules') ?? []);

        return redirect()->back()->with('success', 'Tenant module access updated.');
    }

    public function updateStatus(UpdateTenantStatusRequest $request, Tenant $tenant, TenantProvisioningService $provisioningService): RedirectResponse
    {
        $provisioningService->setTenantStatus($tenant, $request->validated('status'), $request->validated('message'));

        return redirect()->back()->with('success', 'Tenant status updated.');
    }

    private function uniqueSlug(string $organizationName): string
    {
        $base = \Illuminate\Support\Str::slug($organizationName);

        do {
            $slug = $base.'-'.\Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(6));
        } while (TenantApplication::where('slug', $slug)->exists());

        return $slug;
    }

    private function stats($tenants, $applications): array
    {
        return [
            'total_tenants' => $tenants->count(),
            'active_tenants' => $tenants->where('status', Tenant::STATUS_ACTIVE)->count(),
            'pending_applications' => $applications->where('status', 'pending')->count(),
            'rejected_applications' => $applications->where('status', 'rejected')->count(),
            'suspended_tenants' => $tenants->where('status', Tenant::STATUS_SUSPENDED)->count(),
            'pending_setup_tenants' => $tenants->where('status', Tenant::STATUS_PENDING_SETUP)->count(),
        ];
    }
}
