<?php

namespace App\Http\Controllers\API\Mikrotik;

use App\Http\Controllers\Controller;
use App\Models\Mikrotik;
use App\Services\MikroTik\MikroTikServiceFactory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MikrotikPppoeSessionController extends Controller
{
    public function index(Mikrotik $mikrotik, MikroTikServiceFactory $factory): Response
    {
        $sessions = $factory->make($mikrotik)->getActiveUsers();
        $counts = collect($sessions)->countBy('name');

        return Inertia::render('Mikrotik/PppoeSessions', [
            'router' => $mikrotik->only(['id', 'name']),
            'sessions' => collect($sessions)->map(fn (array $session) => [
                ...$session,
                'is_duplicate' => ($counts[$session['name'] ?? ''] ?? 0) > 1,
            ])->values()->all(),
        ]);
    }

    public function destroy(Mikrotik $mikrotik, string $username, MikroTikServiceFactory $factory): RedirectResponse
    {
        $factory->make($mikrotik)->disconnectActiveSession($username);

        return back()->with('message', 'SESSION_KILLED');
    }

    public function bulkDestroy(Request $request, Mikrotik $mikrotik, MikroTikServiceFactory $factory): RedirectResponse
    {
        $data = $request->validate([
            'usernames' => 'required|array|min:1',
            'usernames.*' => 'string',
        ]);

        $killed = $factory->make($mikrotik)->killActiveSessions($data['usernames']);

        return back()->with('message', "KILLED_{$killed}_SESSIONS");
    }
}
