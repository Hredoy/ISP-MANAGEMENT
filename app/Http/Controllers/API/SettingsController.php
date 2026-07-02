<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\MikroTik\ModeResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
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
