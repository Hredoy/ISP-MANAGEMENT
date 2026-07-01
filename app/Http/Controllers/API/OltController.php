<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Olt;
use App\Services\OltService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OltController extends Controller
{
    private const VENDORS = ['huawei', 'zte', 'vsol'];

    public function index(): Response
    {
        return Inertia::render('Olts/Index', [
            'olts' => Olt::orderBy('name')->get(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Olts/Create');
    }

    public function store(Request $request, OltService $service): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'vendor' => 'required|in:'.implode(',', self::VENDORS),
            'host' => 'required|string|max:255',
            'port' => 'required|integer|min:1|max:65535',
            'username' => 'nullable|string|max:255',
            'password' => 'nullable|string|max:255',
            'snmp_community' => 'nullable|string|max:255',
        ]);

        $olt = Olt::create([...$data, 'is_active' => true]);

        $test = $service->testConnection($olt);

        if (! $test['ok']) {
            return redirect()->route('dashboard.olts.index')
                ->with('error', "OLT_SAVED_BUT_UNREACHABLE: {$test['message']}");
        }

        return redirect()->route('dashboard.olts.index')->with('message', 'OLT_ADDED_SUCCESSFULLY');
    }

    public function edit(Olt $olt): Response
    {
        return Inertia::render('Olts/Edit', [
            'olt' => $olt,
        ]);
    }

    public function update(Request $request, Olt $olt): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'vendor' => 'required|in:'.implode(',', self::VENDORS),
            'host' => 'required|string|max:255',
            'port' => 'required|integer|min:1|max:65535',
            'username' => 'nullable|string|max:255',
            'password' => 'nullable|string|max:255',
            'snmp_community' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ]);

        $olt->update($data);

        return redirect()->route('dashboard.olts.index')->with('message', 'OLT_UPDATED_SUCCESSFULLY');
    }

    public function destroy(Olt $olt): RedirectResponse
    {
        $olt->delete();

        return back()->with('message', 'OLT_REMOVED');
    }

    public function checkConnection(Olt $olt, OltService $service): JsonResponse
    {
        $result = $service->testConnection($olt);

        if (! $result['ok']) {
            return response()->json($result, 422);
        }

        $olt->forceFill(['last_ping' => now()])->save();

        return response()->json($result);
    }
}
