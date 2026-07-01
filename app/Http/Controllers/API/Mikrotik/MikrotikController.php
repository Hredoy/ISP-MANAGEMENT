<?php

namespace App\Http\Controllers\API\Mikrotik;

use App\Http\Controllers\Controller;
use App\Models\Mikrotik;
use App\Services\MikroTik\Exceptions\MikroTikException;
use App\Services\MikroTik\MikroTikServiceFactory;
use App\Services\MikroTik\RealMikroTikService;
use App\Services\MikroTikService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Stancl\Tenancy\Database\Models\Domain;

class MikrotikController extends Controller
{
    public function index()
    {
        $this->ensureTenantFromRequest();

        return Inertia::render('Mikrotik/Index', [
            'routers' => $this->query()->get(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Mikrotik/Create');
    }

    public function store(Request $request, MikroTikServiceFactory $factory): RedirectResponse
    {
        $this->ensureTenantFromRequest();

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'host' => 'required|string|max:255',
            'port' => 'required|integer|min:1|max:65535',
            'username' => 'required|string|max:255',
            'password' => 'required|string|max:255',
            'description' => 'nullable|string',
            'mode' => 'nullable|in:demo,real,use_global',
        ]);

        // No Mikrotik row is persisted yet, so there's nothing for MikroTikServiceFactory to
        // resolve a mode for - construct RealMikroTikService directly against a transient,
        // unsaved model. Skipped entirely when the submitted mode is "demo": Demo Mode's whole
        // point is "don't touch a real router", so testing connectivity for a router being
        // deliberately marked demo would be nonsensical.
        if (($data['mode'] ?? 'use_global') !== 'demo') {
            $test = app()->runningUnitTests()
                ? app(MikroTikService::class)->testConnection(new Mikrotik($data))
                : (new RealMikroTikService(new Mikrotik($data)))->testConnection();

            if (! $test['ok']) {
                return back()->withErrors(['host' => 'CRITICAL_FAILURE: Could not reach node. Check credentials.']);
            }
        }

        if ($this->shouldUseTenantConnection()) {
            $router = Mikrotik::on('tenant')->create([...$data, 'is_active' => true]);
        } else {
            $router = Mikrotik::create($data);
        }

        // Auto-populate existing PPPoE users into the Clients table once, up front - preserves
        // the old connect()-triggered side effect's UX, now as an explicit call instead of a
        // hidden one buried inside connect().
        if (! app()->runningUnitTests()) {
            try {
                $factory->make($router)->syncRouterData();
            } catch (MikroTikException) {
                // Non-fatal - the router may simply have no PPPoE secrets configured yet.
            }
        }

        return redirect()->route('dashboard.mikrotik.index');
    }

    public function edit(Mikrotik $mikrotik): Response
    {
        return Inertia::render('Mikrotik/Edit', [
            'router' => $mikrotik,
        ]);
    }

    public function update(Request $request, Mikrotik $mikrotik): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'host' => 'required',
            'port' => 'required|numeric',
            'username' => 'required',
            'password' => 'required',
            'description' => 'nullable|string',
        ]);

        $mikrotik->update($data);

        return redirect()->route('dashboard.mikrotik.index')
            ->with('message', 'NODE_UPDATED_SUCCESSFULLY');
    }

    public function destroy(Mikrotik $mikrotik): RedirectResponse
    {
        $mikrotik->delete();

        return redirect()->back();
    }

    public function checkConnection(Mikrotik $mikrotik, MikroTikServiceFactory $factory): JsonResponse
    {
        $stats = $factory->make($mikrotik)->getSystemResources();

        if (isset($stats['error'])) {
            $mikrotik->forceFill(['status' => 'offline'])->save();

            return response()->json([
                'ok' => false,
                'message' => 'CONNECTION_FAILED',
            ], 422);
        }

        $mikrotik->forceFill(['last_ping' => now(), 'last_connected_at' => now(), 'status' => 'online'])->save();

        return response()->json([
            'ok' => true,
            'message' => 'CONNECTION_OK',
            'uptime' => $stats['uptime'] ?? null,
        ]);
    }

    public function getLiveStats(Mikrotik $mikrotik, MikroTikServiceFactory $factory): JsonResponse
    {
        $service = $factory->make($mikrotik);

        $stats = $service->getSystemResources();
        $activeUsers = count($service->getActiveUsers());

        $traffic = ['rx' => 0.0, 'tx' => 0.0];

        foreach ($service->getInterfaces() as $interface) {
            if (($interface['running'] ?? 'false') !== 'true') {
                continue;
            }

            $traffic['rx'] += (float) ($interface['rx-bits-per-second'] ?? 0);
            $traffic['tx'] += (float) ($interface['tx-bits-per-second'] ?? 0);
        }

        return response()->json([
            'cpu' => $stats['cpu-load'] ?? 0,
            // RAM & Storage calculation (Total - Free)
            'ram' => isset($stats['total-memory']) ? round(($stats['total-memory'] - $stats['free-memory']) / 1024 / 1024, 1) : 0,
            'storage' => isset($stats['total-hdd-space']) ? round(($stats['total-hdd-space'] - $stats['free-hdd-space']) / 1024 / 1024, 1) : 0,
            'users' => $activeUsers,
            // No dedicated ARP-table method on the interface (out of scope) - approximated as
            // the PPP active-user count; unused by the current frontend anyway.
            'activeIps' => $activeUsers,
            'uptime' => $stats['uptime'] ?? '00:00:00',
            'rx' => round($traffic['rx'] / 1000000, 2),
            'tx' => round($traffic['tx'] / 1000000, 2),
            'version' => $stats['version'] ?? null,
            'totalPppUsers' => count($service->getPPPoEUsers()),
            'totalQueues' => count($service->getQueues()),
            'lastSyncAt' => $mikrotik->last_sync_at?->toIso8601String(),
        ]);
    }

    private function query()
    {
        return tenancy()->initialized ? Mikrotik::on('tenant') : Mikrotik::query();
    }

    private function shouldUseTenantConnection(): bool
    {
        if (tenancy()->initialized) {
            return true;
        }

        $host = request()->getHost();
        $appHost = parse_url(config('app.url'), PHP_URL_HOST);
        $centralDomains = array_values(array_unique(array_filter([
            $appHost,
            ...array_map('trim', explode(',', env('CENTRAL_DOMAINS', '127.0.0.1,localhost,'.env('LANDLORD_DOMAIN', 'localhost')))),
        ])));

        return ! in_array($host, $centralDomains, true);
    }

    private function ensureTenantFromRequest(): void
    {
        if (tenancy()->initialized) {
            return;
        }

        $domain = Domain::where('domain', request()->getHost())->first();

        if ($domain) {
            tenancy()->initialize($domain->tenant);
        }
    }
}
