<?php

namespace App\Http\Controllers\API\Mikrotik;

use App\Http\Controllers\Controller;
use App\Models\Mikrotik;
use App\Services\MikroTik\DTO\RouterDashboardStats;
use App\Services\MikroTik\Exceptions\MikroTikException;
use App\Services\MikroTik\MikroTikServiceFactory;
use App\Services\MikroTik\RealMikroTikService;
use App\Services\MikroTik\Resources\MikrotikResource;
use App\Support\TenantCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Stancl\Tenancy\Database\Models\Domain;

class MikrotikController extends Controller
{
    public function index()
    {
        $this->ensureTenantFromRequest();

        return Inertia::render('Mikrotik/Index', [
            // ->resolve() instead of ::collection() - a ResourceCollection's default "data" wrapper
            // is meant for top-level JSON responses, not values nested inside an Inertia props array.
            'routers' => $this->query()->get()->map(fn (Mikrotik $mikrotik) => (new MikrotikResource($mikrotik))->resolve()),
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
            'location' => 'nullable|string|max:255',
            'mode' => 'nullable|in:demo,real,use_global',
        ]);

        // No Mikrotik row is persisted yet, so there's nothing for MikroTikServiceFactory to
        // resolve a mode for - construct RealMikroTikService directly against a transient,
        // unsaved model. Skipped entirely when the submitted mode is "demo": Demo Mode's whole
        // point is "don't touch a real router", so testing connectivity for a router being
        // deliberately marked demo would be nonsensical.
        if (($data['mode'] ?? 'use_global') !== 'demo') {
            $test = (new RealMikroTikService(new Mikrotik($data)))->testConnection();

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
        try {
            $factory->make($router)->syncRouterData();
        } catch (MikroTikException) {
            // Non-fatal - the router may simply have no PPPoE secrets configured yet.
        }

        TenantCache::forgetDevices();
        TenantCache::forgetDashboardAndClients();

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
            'location' => 'nullable|string|max:255',
            'mode' => 'nullable|in:demo,real,use_global',
        ]);

        $mikrotik->update($data);
        TenantCache::forgetDevices();

        return redirect()->route('dashboard.mikrotik.index')
            ->with('message', 'NODE_UPDATED_SUCCESSFULLY');
    }

    public function destroy(Mikrotik $mikrotik): RedirectResponse
    {
        $mikrotik->delete();
        TenantCache::forgetDevices();

        return redirect()->back();
    }

    /**
     * Quick per-router mode override, decoupled from update()'s full credential form - lets the
     * admin panel's router list flip Demo/Real/Use_Global without resubmitting username/password.
     */
    public function updateMode(Request $request, Mikrotik $mikrotik): RedirectResponse
    {
        $data = $request->validate([
            'mode' => 'required|in:demo,real,use_global',
        ]);

        $mikrotik->update($data);

        return back()->with('message', 'MODE_UPDATED');
    }

    /**
     * Explicit admin action - pulls current PPP secrets from the router into the `clients`
     * table. Previously this only ever happened as a side effect of store(); the interface
     * docblock on syncRouterData() already documents this as the intended trigger.
     */
    public function sync(Mikrotik $mikrotik, MikroTikServiceFactory $factory): RedirectResponse
    {
        try {
            $result = $factory->make($mikrotik)->syncRouterData();
        } catch (MikroTikException) {
            return back()->withErrors(['host' => 'ROUTER_UNREACHABLE: Could not sync.']);
        }

        return back()->with('message', "SYNCED_{$result['synced']}_CLIENTS");
    }

    public function enable(Mikrotik $mikrotik): RedirectResponse
    {
        $mikrotik->forceFill(['is_active' => true])->save();
        TenantCache::forgetDevices();

        return back()->with('message', 'NODE_ENABLED');
    }

    public function disable(Mikrotik $mikrotik): RedirectResponse
    {
        $mikrotik->forceFill(['is_active' => false])->save();
        TenantCache::forgetDevices();

        return back()->with('message', 'NODE_DISABLED');
    }

    public function setDefault(Mikrotik $mikrotik): RedirectResponse
    {
        DB::connection($mikrotik->getConnectionName())->transaction(function () use ($mikrotik) {
            $this->query()->where('id', '!=', $mikrotik->id)->update(['is_default' => false]);
            $mikrotik->forceFill(['is_default' => true])->save();
        });
        TenantCache::forgetDevices();

        return back()->with('message', 'DEFAULT_NODE_SET');
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

        $stats = RouterDashboardStats::fromServiceData(
            systemResources: $service->getSystemResources(),
            interfaces: $service->getInterfaces(),
            activeUsers: count($service->getActiveUsers()),
            totalPppUsers: count($service->getPPPoEUsers()),
            totalQueues: count($service->getQueues()),
            lastSyncAt: $mikrotik->last_sync_at?->toIso8601String(),
        );

        return response()->json($stats->toArray());
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
