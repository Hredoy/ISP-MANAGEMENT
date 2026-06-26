<?php

namespace App\Http\Controllers;

use App\Models\TenantApplication;
use App\Services\TenantProvisioningService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class LandlordTenantController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Landlord/Tenants', [
            'applications' => TenantApplication::latest()->get(),
        ]);
    }

    public function approve(TenantApplication $application, TenantProvisioningService $provisioningService): RedirectResponse
    {
        $provisioningService->approve($application);

        return redirect()->back()->with('success', 'Tenant approved, database provisioned, and migrations completed.');
    }
}
