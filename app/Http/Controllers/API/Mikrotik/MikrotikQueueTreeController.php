<?php

namespace App\Http\Controllers\API\Mikrotik;

use App\Http\Controllers\Controller;
use App\Models\Mikrotik;
use App\Services\MikroTik\MikroTikServiceFactory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MikrotikQueueTreeController extends Controller
{
    public function index(Mikrotik $mikrotik, MikroTikServiceFactory $factory): Response
    {
        return Inertia::render('Mikrotik/QueueTree', [
            'router' => $mikrotik->only(['id', 'name']),
            'nodes' => $factory->make($mikrotik)->getQueueTree(),
        ]);
    }

    public function store(Request $request, Mikrotik $mikrotik, MikroTikServiceFactory $factory): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'parent' => 'nullable|string|max:255',
            'packet_mark' => 'nullable|string|max:255',
            'max_limit' => 'required|string|max:255',
            'limit_at' => 'nullable|string|max:255',
            'priority' => 'nullable|integer|min:1|max:8',
        ]);

        $factory->make($mikrotik)->addQueueTree($data);

        return back()->with('message', 'QUEUE_NODE_SAVED');
    }

    public function update(Request $request, Mikrotik $mikrotik, string $name, MikroTikServiceFactory $factory): RedirectResponse
    {
        $data = $request->validate([
            'parent' => 'nullable|string|max:255',
            'max_limit' => 'nullable|string|max:255',
            'limit_at' => 'nullable|string|max:255',
            'priority' => 'nullable|integer|min:1|max:8',
            'disabled' => 'nullable|boolean',
        ]);

        if (! $factory->make($mikrotik)->updateQueueTree($name, $data)) {
            return back()->withErrors(['name' => 'QUEUE_NODE_NOT_FOUND']);
        }

        return back()->with('message', 'QUEUE_NODE_UPDATED');
    }

    public function destroy(Mikrotik $mikrotik, string $name, MikroTikServiceFactory $factory): RedirectResponse
    {
        $factory->make($mikrotik)->deleteQueueTree($name);

        return back()->with('message', 'QUEUE_NODE_DELETED');
    }
}
