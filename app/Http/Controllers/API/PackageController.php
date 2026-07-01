<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Mikrotik;
use App\Models\Package;
use App\Services\MikroTik\Exceptions\MikroTikCommandException;
use App\Services\MikroTik\Exceptions\MikroTikException;
use App\Services\MikroTik\MikroTikServiceFactory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PackageController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Packages/Index', [
            'packages' => Package::with('mikrotik')->get(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Packages/Create', [
            'routers' => Mikrotik::all(['id', 'name']),
        ]);
    }

    public function store(Request $request, MikroTikServiceFactory $factory): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string',
            'mikrotik_id' => 'required|exists:mikrotiks,id',
            'rate_limit' => 'required', // 10M/10M
            'price' => 'required|numeric',
            'local_address' => 'nullable',
            'remote_address' => 'nullable',
            'description' => 'nullable',
        ]);

        $router = Mikrotik::findOrFail($data['mikrotik_id']);

        try {
            $factory->make($router)->createPPPProfile([
                'name' => $data['name'],
                'rate_limit' => $data['rate_limit'],
                'local_address' => $data['local_address'] ?? null,
                'remote_address' => $data['remote_address'] ?? null,
            ]);
        } catch (MikroTikException) {
            // Matches prior behaviour: the profile is created locally even if the router is
            // unreachable, rather than blocking package management on router availability.
        }

        Package::create($data);

        return redirect()->route('dashboard.packages.index')->with('message', 'PACKAGE_CREATED_ON_ROUTER');
    }

    public function edit(Package $package): Response
    {
        return Inertia::render('Packages/Edit', [
            'package' => $package,
            'routers' => Mikrotik::all(['id', 'name']),
        ]);
    }

    public function update(Request $request, Package $package, MikroTikServiceFactory $factory): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string',
            'mikrotik_id' => 'required|exists:mikrotiks,id',
            'rate_limit' => 'required',
            'price' => 'required|numeric',
            'local_address' => 'nullable',
            'remote_address' => 'nullable',
            'description' => 'nullable',
        ]);

        $router = Mikrotik::findOrFail($data['mikrotik_id']);

        try {
            $factory->make($router)->updatePPPProfile($package->name, [
                'name' => $data['name'],
                'rate_limit' => $data['rate_limit'],
                'local_address' => $data['local_address'] ?? null,
                'remote_address' => $data['remote_address'] ?? null,
            ]);
        } catch (MikroTikException) {
            // Soft-fail, same as store() above.
        }

        $package->update($data);

        return redirect()->route('dashboard.packages.index')->with('message', 'PACKAGE_UPDATED');
    }

    public function destroy(Package $package, MikroTikServiceFactory $factory): RedirectResponse
    {
        $router = $package->mikrotik;

        try {
            $deleted = $factory->make($router)->deletePPPProfile($package->name);

            if (! $deleted) {
                // The profile is already gone from the router, so just delete from DB.
                $package->delete();

                return back()->with('message', 'DB_SYNCED: Profile was already missing from Router.');
            }
        } catch (MikroTikCommandException $e) {
            return back()->with('error', $e->getMessage());
        } catch (MikroTikException) {
            // Router unreachable — soft-fail, matches store()/update()'s philosophy above.
        }

        $package->delete();

        return back()->with('message', 'PACKAGE_REMOVED');
    }
}
