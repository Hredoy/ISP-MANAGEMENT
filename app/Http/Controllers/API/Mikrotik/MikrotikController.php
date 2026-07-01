<?php

namespace App\Http\Controllers\API\Mikrotik;

use App\Http\Controllers\Controller;
use App\Models\Mikrotik;
use App\Services\MikroTikService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RouterOS\Query;
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

    public function store(Request $request, MikroTikService $mikroTikService): RedirectResponse
    {
        $this->ensureTenantFromRequest();

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'host' => 'required|string|max:255',
            'port' => 'required|integer|min:1|max:65535',
            'username' => 'required|string|max:255',
            'password' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        // Test connection before saving
        $test = $mikroTikService->testConnection($request->host, $request->username, $request->password, (int) $request->port);

        if (! $test['ok']) {
            return back()->withErrors(['host' => 'CRITICAL_FAILURE: Could not reach node. Check credentials.']);
        }

        if ($this->shouldUseTenantConnection()) {
            Mikrotik::on('tenant')->create([...$data, 'is_active' => true]);
        } else {
            Mikrotik::create($data);
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

    public function checkConnection(Mikrotik $mikrotik, MikroTikService $service): JsonResponse
    {
        $stats = $service->getSystemStats($mikrotik);

        if (isset($stats['error'])) {
            return response()->json([
                'ok' => false,
                'message' => 'CONNECTION_FAILED',
            ], 422);
        }

        $mikrotik->forceFill(['last_ping' => now()])->save();

        return response()->json([
            'ok' => true,
            'message' => 'CONNECTION_OK',
            'uptime' => $stats['uptime'] ?? null,
        ]);
    }

    public function getLiveStats(Mikrotik $mikrotik, MikroTikService $service): JsonResponse
    {
        // 1. Get System Stats (CPU, RAM, Uptime)
        $stats = $service->getSystemStats($mikrotik);

        // 2. Connect for specialized queries
        $client = $service->connect($mikrotik);

        $activeUsers = 0;
        $activeIps = 0;
        $traffic = ['rx' => 0, 'tx' => 0];

        if ($client) {
            // Fetch Active PPPoE Users
            $activeUsers = count($client->query('/ppp/active/print')->read());
            $activeIps = count($client->query('/ip/arp/print')->read());
            $printQuery = (new Query('/interface/print'))
                ->where('running', 'true'); // Some clients use 'where' for API queries

            $activeInterfaces = $client->query($printQuery)->read();

            $interfaceNames = implode(',', array_column($activeInterfaces, 'name'));

            $trafficQuery = (new Query('/interface/monitor-traffic'))
                ->equal('interface', $interfaceNames)
                ->equal('once', 'true');     // Try 'true' instead of an empty string

            $trafficResponse = $client->query($trafficQuery)->read();

            if (! empty($trafficResponse)) {
                // Convert bits per second to Mbps (Megabits)
                $traffic['rx'] = round($trafficResponse[0]['rx-bits-per-second'] / 1000000, 2);
                $traffic['tx'] = round($trafficResponse[0]['tx-bits-per-second'] / 1000000, 2);
            }
        }

        return response()->json([
            'cpu' => $stats['cpu-load'] ?? 0,
            // RAM & Storage calculation (Total - Free)
            'ram' => isset($stats['total-memory']) ? round(($stats['total-memory'] - $stats['free-memory']) / 1024 / 1024, 1) : 0,
            'storage' => isset($stats['total-hdd-space']) ? round(($stats['total-hdd-space'] - $stats['free-hdd-space']) / 1024 / 1024, 1) : 0,
            'users' => $activeUsers,
            'activeIps' => $activeIps,
            'uptime' => $stats['uptime'] ?? '00:00:00',
            'rx' => $traffic['rx'],
            'tx' => $traffic['tx'],
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
