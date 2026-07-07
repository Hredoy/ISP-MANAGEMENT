<?php

namespace App\Http\Controllers\API\Mikrotik;

use App\Http\Controllers\Controller;
use App\Models\Mikrotik;
use App\Services\MikroTik\MikroTikServiceFactory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MikrotikFirewallController extends Controller
{
    public function index(Mikrotik $mikrotik, MikroTikServiceFactory $factory): Response
    {
        return Inertia::render('Mikrotik/Firewall', [
            'router' => $mikrotik->only(['id', 'name']),
            'rules' => $factory->make($mikrotik)->getFirewallRules(),
        ]);
    }

    public function store(Request $request, Mikrotik $mikrotik, MikroTikServiceFactory $factory): RedirectResponse
    {
        $data = $request->validate([
            'chain' => 'required|in:input,forward,output',
            'action' => 'required|in:accept,drop,reject,fasttrack-connection',
            'protocol' => 'nullable|string|max:50',
            'dst_port' => 'nullable|string|max:100',
            'src_address' => 'nullable|string|max:100',
            'dst_address' => 'nullable|string|max:100',
            'comment' => 'nullable|string|max:255',
        ]);

        $factory->make($mikrotik)->addFirewallRule($data);

        return back()->with('message', 'FIREWALL_RULE_ADDED');
    }

    public function update(Request $request, Mikrotik $mikrotik, string $rule, MikroTikServiceFactory $factory): RedirectResponse
    {
        $data = $request->validate([
            'chain' => 'nullable|in:input,forward,output',
            'action' => 'nullable|in:accept,drop,reject,fasttrack-connection',
            'protocol' => 'nullable|string|max:50',
            'dst_port' => 'nullable|string|max:100',
            'src_address' => 'nullable|string|max:100',
            'dst_address' => 'nullable|string|max:100',
            'comment' => 'nullable|string|max:255',
            'disabled' => 'nullable|boolean',
        ]);

        if (! $factory->make($mikrotik)->updateFirewallRule($rule, $data)) {
            return back()->withErrors(['rule' => 'RULE_NOT_FOUND']);
        }

        return back()->with('message', 'FIREWALL_RULE_UPDATED');
    }

    public function destroy(Mikrotik $mikrotik, string $rule, MikroTikServiceFactory $factory): RedirectResponse
    {
        $factory->make($mikrotik)->deleteFirewallRule($rule);

        return back()->with('message', 'FIREWALL_RULE_DELETED');
    }

    public function move(Request $request, Mikrotik $mikrotik, string $rule, MikroTikServiceFactory $factory): RedirectResponse
    {
        $data = $request->validate(['direction' => 'required|in:up,down']);

        $factory->make($mikrotik)->moveFirewallRule($rule, $data['direction']);

        return back()->with('message', 'FIREWALL_RULE_MOVED');
    }
}
