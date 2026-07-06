<?php

namespace App\Http\Controllers;

use App\Models\TenantBlog;
use App\Models\TenantComplaint;
use App\Models\TenantConnectionRequest;
use App\Models\TenantFrontendSetting;
use App\Models\TenantManualPayment;
use App\Models\TenantReferral;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TenantFrontendController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Tenant/FrontendAdmin', [
            'settings' => TenantFrontendSetting::on('tenant')->where('key', 'site')->value('value') ?? [],
            'connections' => TenantConnectionRequest::on('tenant')->latest()->limit(20)->get(),
            'complaints' => TenantComplaint::on('tenant')->latest()->limit(20)->get(),
            'referrals' => TenantReferral::on('tenant')->latest()->limit(20)->get(),
            'payments' => TenantManualPayment::on('tenant')->latest()->limit(20)->get(),
            'blogs' => TenantBlog::on('tenant')->latest()->limit(20)->get(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'is_enabled' => ['boolean'],
            'site_name' => ['nullable', 'string', 'max:255'],
            'logo' => ['nullable', 'string', 'max:1000'],
            'favicon' => ['nullable', 'string', 'max:1000'],
            'phone' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'about' => ['nullable', 'string', 'max:3000'],
            'primary_color' => ['nullable', 'string', 'max:20'],
            'accent_color' => ['nullable', 'string', 'max:20'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:500'],
            'seo_keywords' => ['nullable', 'string', 'max:500'],
            'referral_offer' => ['nullable', 'string', 'max:1000'],
            'footer_about' => ['nullable', 'string', 'max:2000'],
            'terms' => ['nullable', 'string', 'max:5000'],
            'privacy' => ['nullable', 'string', 'max:5000'],
            'refund' => ['nullable', 'string', 'max:5000'],
        ]);

        TenantFrontendSetting::on('tenant')->updateOrCreate(['key' => 'site'], ['value' => $validated]);

        return back()->with('success', 'Frontend settings updated.');
    }
}
