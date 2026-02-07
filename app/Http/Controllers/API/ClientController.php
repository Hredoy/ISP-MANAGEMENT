<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Mikrotik;
use App\Models\SubZone;
use App\Models\Zone;
use App\Services\MikrotikService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RouterOS\Exceptions\ClientException;
use RouterOS\Exceptions\ConfigException;
use RouterOS\Exceptions\QueryException;
use RouterOS\Query;

class ClientController extends Controller
{
    /**
     * @return Response
     */
    public function index(): Response
    {
        // Fetch clients with their relationships for the table
        $clients = Client::with(['zone', 'sub_zone'])->latest()->get();

        return Inertia::render('Clients/Index', [
            'clients' => $clients
        ]);
    }
    public function create(): Response
    {
        return Inertia::render('Clients/Create', [
            'routers'  => Mikrotik::all(['id', 'name']),
            'zones'    => Zone::all(['id', 'name']),
            'subZones' => SubZone::all(['id', 'name', 'zone_id']),
            // Hardcoded for now, or pull from MikrotikService
            'packages' => ['5Mbps', '10Mbps', '20Mbps', 'Starter_Pack']
        ]);
    }

    /**
     * @param Request $request
     * @param MikrotikService $service
     * @return RedirectResponse
     * @throws ClientException
     * @throws ConfigException
     * @throws QueryException
     */
    public function store(Request $request, MikrotikService $service): RedirectResponse
    {
        $request->validate([
            'pppoe_username' => 'required|unique:clients',
            'pppoe_password' => 'required|min:6',
            'mikrotik_id' => 'required',
            'full_name' => 'required',
            'monthly_bill' => 'required|numeric',
        ]);

        // 1. Fetch the Router connection details
        $router = Mikrotik::findOrFail($request->mikrotik_id);

        // 2. Create the PPPoE Secret on MikroTik
        $client = $service->connect($router->host, $router->username, $router->password);

        if ($client) {
            $query = (new Query('/ppp/secret/add'))
                ->equal('name', $request->pppoe_username)
                ->equal('password', $request->pppoe_password)
                ->equal('profile', $request->package_name) // Match MikroTik Profile
                ->equal('comment', "Laravel_ID:" . $request->full_name);

            $client->query($query)->read();
        } else {
            return back()->withErrors(['mikrotik_id' => 'ROUTER_UNREACHABLE: Provisioning Failed']);
        }

        // 3. Save to Database
        Client::create($request->all());

        return redirect()->route('dashboard.clients.index')->with('message', 'CLIENT_PROVISIONED_SUCCESSFULLY');
    }

    public function edit(Client $client): Response
    {
        return Inertia::render('Clients/Edit', [
            'client'   => $client,
            'routers'  => Mikrotik::all(['id', 'name']),
            'zones'    => Zone::all(['id', 'name']),
            'subZones' => SubZone::all(['id', 'name', 'zone_id']),
            'packages' => ['5Mbps', '10Mbps', '20Mbps', 'Starter_Pack']
        ]);
    }

    public function update(Request $request, Client $client, MikrotikService $service): RedirectResponse
    {
        $data = $request->validate([
            'mikrotik_id'    => 'required',
            'package_name'   => 'required',
            'pppoe_username' => 'required|unique:clients,pppoe_username,' . $client->id,
            'pppoe_password' => 'required|min:6',
            'full_name'      => 'required',
            'status'         => 'required',
            'expiry_date'    => 'required|date',
            // Add other validation fields as per your migration...
        ]);

        $router = Mikrotik::findOrFail($request->mikrotik_id);
        $mt = $service->connect($router->host, $router->username, $router->password);

        if ($mt) {
            // Find existing secret on MikroTik to update it
            $find = $mt->query((new Query('/ppp/secret/print'))
                ->equal('name', $client->pppoe_username))->read();

            if (!empty($find)) {
                $mt->query((new Query('/ppp/secret/set'))
                    ->equal('.id', $find[0]['.id'])
                    ->equal('name', $request->pppoe_username)
                    ->equal('password', $request->pppoe_password)
                    ->equal('profile', $request->package_name)
                    ->equal('disabled', $request->status === 'Active' ? 'no' : 'yes')
                    ->equal('comment', "Updated:" . $request->full_name))->read();
            }
        }

        $client->update($request->all());

        return redirect()->route('dashboard.clients.index')->with('message', 'CLIENT_DATABASE_SYNCED');
    }

    /**
     * Remove the specified client from storage and MikroTik.
     */
    public function destroy(Client $client, MikrotikService $service): RedirectResponse
    {
        try {
            $router = $client->mikrotik;

            $mt = $service->connect($router->host, $router->username, $router->password);

            if ($mt) {
                $findQuery = (new Query('/ppp/secret/print'))
                    ->equal('.proplist', '.id')
                    ->equal('name', $client->pppoe_username);

                $response = $mt->query($findQuery)->read();

                if (!empty($response)) {
                    $removeQuery = (new Query('/ppp/secret/remove'))
                        ->equal('.id', $response[0]['.id']);
                    $mt->query($removeQuery)->read();

                    $kickQuery = (new Query('/ppp/active/print'))
                        ->equal('.proplist', '.id')
                        ->equal('name', $client->pppoe_username);

                    $activeRes = $mt->query($kickQuery)->read();
                    if (!empty($activeRes)) {
                        $mt->query((new Query('/ppp/active/remove'))->equal('.id', $activeRes[0]['.id']))->read();
                    }
                }
            }

            // 5. Delete from local Database
            $client->delete();

            return back()->with('message', 'CLIENT_TERMINATED_AND_ROUTER_CLEANED');

        } catch (\Exception $e) {
            // If router is offline, we still delete the DB record but warn the admin
            $client->delete();
            return back()->with('error', 'DB_CLEANED_BUT_ROUTER_UNREACHABLE');
        }
    }
}
