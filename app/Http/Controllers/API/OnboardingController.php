<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\TenantApplication;
use App\Services\SmsService;
use App\Services\TenantProvisioningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class OnboardingController extends Controller
{
    public function __invoke(Request $request, TenantProvisioningService $provisioningService, SmsService $smsService): JsonResponse
    {
        $validated = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'district' => ['required', 'string', 'max:100'],
            'logo' => ['nullable', 'image', 'max:2048'],
            'contact_name' => ['required', 'string', 'max:255'],
            'contact_email' => ['required', 'email', 'max:255'],
            'contact_phone' => ['required', 'string', 'max:50'],
            'plan' => ['required', Rule::in(['nano', 'starter', 'pro', 'enterprise'])],
            'mikrotik_ip' => ['required', 'ip'],
            'olt_ip' => ['nullable', 'ip'],
            'olt_brand' => ['nullable', 'string', 'max:100'],
        ]);

        $slug = $this->uniqueSlug($validated['company_name']);
        $logoPath = $request->file('logo')?->store('tenant-logos', 'public');
        $adminPassword = Str::password(12);

        $application = TenantApplication::create([
            'organization_name' => $validated['company_name'],
            'slug' => $slug,
            'district' => $validated['district'],
            'logo_path' => $logoPath,
            'plan' => $validated['plan'],
            'contact_name' => $validated['contact_name'],
            'email' => $validated['contact_email'],
            'phone' => $validated['contact_phone'],
            'mikrotik_ip' => $validated['mikrotik_ip'],
            'olt_ip' => $validated['olt_ip'] ?? null,
            'olt_brand' => $validated['olt_brand'] ?? null,
            'status' => 'pending',
        ]);

        $application = $provisioningService->approve($application, $adminPassword);
        $loginUrl = 'https://'.$application->subdomain.'/login';

        $smsService->sendAdminCredentials($application->phone, $loginUrl, $application->email, $adminPassword);
        $application->forceFill(['sms_sent_at' => now()])->save();

        return response()->json([
            'message' => 'Tenant provisioned successfully.',
            'tenant' => [
                'id' => $application->id,
                'company_name' => $application->organization_name,
                'slug' => $application->slug,
                'subdomain' => $application->subdomain,
                'database_name' => $application->database_name,
                'plan' => $application->plan,
                'login_url' => $loginUrl,
                'admin_email' => $application->email,
                'sms_sent' => true,
            ],
            'steps' => [
                ['key' => 'tenant', 'label' => 'Tenant created', 'status' => 'complete'],
                ['key' => 'database', 'label' => 'Isolated database migrated', 'status' => 'complete'],
                ['key' => 'seed', 'label' => 'Default packages and admin seeded', 'status' => 'complete'],
                ['key' => 'sms', 'label' => 'Admin credentials sent by SMS', 'status' => 'complete'],
            ],
        ], 201);
    }

    private function uniqueSlug(string $companyName): string
    {
        $base = Str::slug($companyName);
        $slug = $base;
        $counter = 2;

        while (TenantApplication::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$counter;
            $counter++;
        }

        return $slug;
    }
}
