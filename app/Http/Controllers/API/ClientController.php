<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreClientRequest;
use App\Models\Client;
use App\Models\Mikrotik;
use App\Models\Olt;
use App\Models\Package;
use App\Models\Reseller;
use App\Models\SubZone;
use App\Models\Zone;
use App\Services\MikroTik\MikroTikServiceFactory;
use App\Support\TenantCache;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class ClientController extends Controller
{
    private const SORTABLE = ['full_name', 'pppoe_username', 'package_name', 'monthly_bill', 'expiry_date', 'status', 'created_at'];

    public function index(Request $request): Response
    {
        $sort = in_array($request->string('sort')->toString(), self::SORTABLE, true)
            ? $request->string('sort')->toString()
            : 'created_at';
        $direction = $request->string('direction')->toString() === 'asc' ? 'asc' : 'desc';

        // Only the plain, unparameterized listing (first page, no search/filter/sort) is safe to
        // cache under one static key - every other combination of search/filter/sort/page is
        // its own distinct result set and must always hit the database.
        $isDefaultView = ! $request->anyFilled(['search', 'zone_id', 'status', 'package_name', 'sort', 'direction', 'page', 'per_page']);

        $buildClients = fn () => Client::with(['zone:id,name', 'sub_zone:id,name'])
            ->search($request->string('search')->toString() ?: null)
            ->filter($request->only(['zone_id', 'status', 'package_name']))
            ->orderBy($sort, $direction)
            ->paginate((int) $request->integer('per_page', 25))
            ->withQueryString();

        $clients = $isDefaultView ? TenantCache::rememberClients($buildClients) : $buildClients();

        return Inertia::render('Clients/Index', [
            'clients' => $clients,
            'filters' => $request->only(['search', 'zone_id', 'status', 'package_name', 'sort', 'direction']),
            'zones' => Zone::all(['id', 'name']),
            'packages' => Package::orderBy('name')->get(['name']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Clients/Create', [
            'routers'   => Mikrotik::all(['id', 'name']),
            'zones'     => Zone::all(['id', 'name']),
            'subZones'  => SubZone::all(['id', 'name', 'zone_id']),
            'packages'  => Package::orderBy('name')->get(['name', 'price', 'mikrotik_id']),
            'olts'      => Olt::where('is_active', true)->get(['id', 'name', 'vendor']),
            'resellers' => Reseller::where('status', 'active')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StoreClientRequest $request, MikroTikServiceFactory $factory): RedirectResponse
    {
        $data = $request->validated();

        $router = Mikrotik::findOrFail($data['mikrotik_id']);

        try {
            $factory->make($router)->addPPPoEUser([
                'name' => $data['pppoe_username'],
                'password' => $data['pppoe_password'],
                'profile' => $data['package_name'],
                'comment' => $data['full_name'],
            ]);
        } catch (Throwable) {
            return back()->withErrors(['mikrotik_id' => 'ROUTER_UNREACHABLE: Provisioning Failed'])->withInput();
        }

        Client::create([...$data, 'status' => 'Active']);
        TenantCache::forgetDashboardAndClients();

        return redirect()->route('dashboard.clients.index')->with('message', 'CLIENT_PROVISIONED_SUCCESSFULLY');
    }

    public function edit(Client $client): Response
    {
        return Inertia::render('Clients/Edit', [
            'client'    => $client,
            'routers'   => Mikrotik::all(['id', 'name']),
            'zones'     => Zone::all(['id', 'name']),
            'subZones'  => SubZone::all(['id', 'name', 'zone_id']),
            'packages'  => Package::orderBy('name')->get(['name', 'price', 'mikrotik_id']),
            'resellers' => Reseller::where('status', 'active')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, Client $client, MikroTikServiceFactory $factory): RedirectResponse
    {
        $data = $request->validate([
            'mikrotik_id' => 'required|exists:mikrotiks,id',
            'zone_id' => 'nullable|exists:zones,id',
            'sub_zone_id' => 'nullable|exists:sub_zones,id',
            'reseller_id' => 'nullable|uuid|exists:resellers,id',
            'package_name' => 'required|string',
            'pppoe_username' => 'required|string|unique:clients,pppoe_username,'.$client->id,
            'pppoe_password' => 'required|string|min:6',
            'full_name' => 'required|string',
            'email' => 'nullable|email',
            'phone_number' => 'required|string',
            'telegram_chat_id' => 'nullable|string',
            'monthly_bill' => 'required|numeric',
            'full_address' => 'required|string',
            'status' => 'required|in:Active,Suspended',
            'expiry_date' => 'required|date',
            'additional_notes' => 'nullable|string',
        ]);

        $router = Mikrotik::findOrFail($data['mikrotik_id']);
        $synced = true;

        try {
            $factory->make($router)->updatePPPoEUser($client->pppoe_username, [
                'name' => $data['pppoe_username'],
                'password' => $data['pppoe_password'],
                'profile' => $data['package_name'],
                'comment' => $data['full_name'],
                'disabled' => $data['status'] === 'Suspended',
            ]);
        } catch (Throwable) {
            $synced = false;
        }

        $client->update($data);
        TenantCache::forgetDashboardAndClients();

        return redirect()->route('dashboard.clients.index')->with(
            $synced ? 'message' : 'error',
            $synced ? 'CLIENT_DATABASE_SYNCED' : 'DB_UPDATED_BUT_ROUTER_UNREACHABLE'
        );
    }

    public function destroy(Client $client, MikroTikServiceFactory $factory): RedirectResponse
    {
        try {
            $factory->make($client->mikrotik)->removePPPoEUser($client->pppoe_username);
            $client->delete();
            TenantCache::forgetDashboardAndClients();

            return back()->with('message', 'CLIENT_TERMINATED_AND_ROUTER_CLEANED');
        } catch (Throwable) {
            $client->delete();
            TenantCache::forgetDashboardAndClients();

            return back()->with('error', 'DB_CLEANED_BUT_ROUTER_UNREACHABLE');
        }
    }

    public function suspend(Client $client, MikroTikServiceFactory $factory): RedirectResponse
    {
        try {
            $factory->make($client->mikrotik)->disableUser($client->pppoe_username);
        } catch (Throwable) {
            return back()->withErrors(['mikrotik_id' => 'ROUTER_UNREACHABLE: Could not suspend on MikroTik.']);
        }

        $client->update(['status' => 'Suspended']);
        TenantCache::forgetDashboardAndClients();

        return back()->with('message', 'CLIENT_SUSPENDED');
    }

    public function unsuspend(Client $client, MikroTikServiceFactory $factory): RedirectResponse
    {
        try {
            $factory->make($client->mikrotik)->enableUser($client->pppoe_username);
        } catch (Throwable) {
            return back()->withErrors(['mikrotik_id' => 'ROUTER_UNREACHABLE: Could not unsuspend on MikroTik.']);
        }

        $client->update(['status' => 'Active']);
        TenantCache::forgetDashboardAndClients();

        return back()->with('message', 'CLIENT_UNSUSPENDED');
    }
}
