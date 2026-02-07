<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\Mikrotik;
use App\Services\MikrotikService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RouterOS\Query;

class PackageController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Packages/Index', [
            'packages' => Package::with('mikrotik')->get()
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Packages/Create', [
            'routers' => Mikrotik::all(['id', 'name'])
        ]);
    }

    public function store(Request $request, MikrotikService $service): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string',
            'mikrotik_id' => 'required|exists:mikrotiks,id',
            'rate_limit' => 'required', // 10M/10M
            'price' => 'required|numeric',
            'local_address' => 'nullable',
            'remote_address' => 'nullable',
            'description' => 'nullable'
        ]);

        $router = Mikrotik::findOrFail($data['mikrotik_id']);
        $conn = $service->connect($router->host, $router->username, $router->password);

        if ($conn) {
            $query = (new Query('/ppp/profile/add'))
                ->equal('name', $data['name'])
                ->equal('rate-limit', $data['rate_limit'])
                ->equal('local-address', $data['local_address'] ?? '')
                ->equal('remote-address', $data['remote_address'] ?? '');
            $conn->query($query)->read();
        }

        Package::create($data);
        return redirect()->route('dashboard.packages.index')->with('message', 'PACKAGE_CREATED_ON_ROUTER');
    }

    public function destroy(Package $package, MikrotikService $service): RedirectResponse
    {
        $router = $package->mikrotik;
        $conn = $service->connect($router->host, $router->username, $router->password);

        if ($conn) {
            // Find and remove profile from MikroTik
            $allProfiles = $conn->query((new \RouterOS\Query('/ppp/profile/print'))
                ->equal('.proplist', 'name,.id')
            )->read();

            $targetProfile = collect($allProfiles)->where('name', $package->name)->first();
            if ($targetProfile && isset($targetProfile['.id'])) {
                // 3. Prevent deletion of default system profiles (Safety Guard)
                $systemIds = ['*0', '*FFFFFFFE']; // Standard MikroTik default IDs

                if (in_array($targetProfile['.id'], $systemIds)) {
                    return back()->with('error', 'PROTECTED_RESOURCE: Cannot delete system default profiles.');
                }

                // 4. Remove using the found .id
                $conn->query(
                    (new \RouterOS\Query('/ppp/profile/remove'))
                        ->equal('numbers', $targetProfile['.id'])
                )->read();
            }

            if (!$targetProfile) {
                // The profile is already gone from the router, so just delete from DB
                $package->delete();
                return back()->with('message', 'DB_SYNCED: Profile was already missing from Router.');
            }
        }

        $package->delete();
        return back()->with('message', 'PACKAGE_REMOVED');
    }
}
