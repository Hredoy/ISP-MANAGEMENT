<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\MikroTik\ModeResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Settings/Index', [
            'organization' => Setting::where('key', 'organization')->value('value') ?? [],
            'billing' => Setting::where('key', 'billing')->value('value') ?? [],
            'notifications' => Setting::where('key', 'notifications')->value('value') ?? [],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'organization.name' => 'required|string|max:120',
            'organization.phone' => 'nullable|string|max:40',
            'organization.email' => 'nullable|email|max:120',
            'organization.address' => 'nullable|string|max:500',
            'billing.billing_day' => 'required|integer|min:1|max:28',
            'billing.currency' => 'required|string|max:10',
            'billing.payment_methods' => 'nullable|array',
            'billing.payment_methods.*' => 'string|max:40',
            'notifications.sms_enabled' => 'boolean',
            'notifications.payment_confirmation' => 'boolean',
            'notifications.expiry_reminder' => 'boolean',
        ]);

        foreach (['organization', 'billing', 'notifications'] as $key) {
            Setting::updateOrCreate(
                ['key' => $key],
                ['value' => Arr::get($data, $key, [])],
            );
        }

        return back()->with('message', 'TENANT_SETTINGS_UPDATED');
    }

    public function editMikrotikMode(): Response
    {
        return Inertia::render('Settings/MikrotikMode', [
            'mode' => (new ModeResolver)->globalMode(),
        ]);
    }

    public function updateMikrotikMode(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'mode' => 'required|in:demo,real',
        ]);

        Setting::updateOrCreate(
            ['key' => 'mikrotik_mode'],
            ['value' => ['mode' => $data['mode']]],
        );

        return back()->with('message', 'MIKROTIK_MODE_UPDATED');
    }
}
