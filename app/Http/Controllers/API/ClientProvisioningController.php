<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreClientRequest;
use App\Models\Client;
use App\Models\Mikrotik;
use App\Models\Onu;
use App\Models\Olt;
use App\Models\Package;
use App\Services\MikroTik\MikroTikServiceFactory;
use App\Services\OltService;
use App\Services\SmsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * One-click client provisioning wizard: each method is a single, independently
 * retryable step called from Clients/Create.vue via sequential axios requests.
 * Kept separate from ClientController so the existing atomic store() endpoint
 * (and its tests) stay untouched.
 */
class ClientProvisioningController extends Controller
{
    public function createClient(StoreClientRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['expiry_date'] = now()->addDays(30)->toDateString();

        $client = Client::create([...$data, 'status' => 'Active']);

        return response()->json(['success' => true, 'message' => 'CLIENT_RECORD_CREATED', 'client_id' => $client->id]);
    }

    public function pppoe(Client $client, MikroTikServiceFactory $factory): JsonResponse
    {
        $router = Mikrotik::findOrFail($client->mikrotik_id);

        try {
            $service = $factory->make($router);
            $existing = $service->getPPPoEUsers();

            $alreadyExists = collect($existing)->contains('name', $client->pppoe_username);

            if (! $alreadyExists) {
                $service->addPPPoEUser([
                    'name' => $client->pppoe_username,
                    'password' => $client->pppoe_password,
                    'profile' => $client->package_name,
                    'comment' => $client->full_name,
                ]);
            }

            return response()->json(['success' => true, 'message' => 'PPPOE_USER_PROVISIONED']);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => 'ROUTER_UNREACHABLE: '.$e->getMessage()], 422);
        }
    }

    public function onu(Request $request, Client $client, OltService $service): JsonResponse
    {
        $data = $request->validate([
            'olt_id' => 'nullable|exists:olts,id',
            'onu_mac' => 'nullable|string',
            'onu_serial' => 'nullable|string',
            'pon_port' => 'nullable|string',
        ]);

        if (empty($data['olt_id'])) {
            return response()->json(['success' => true, 'skipped' => true, 'message' => 'No OLT selected — step skipped.']);
        }

        $olt = Olt::findOrFail($data['olt_id']);

        $result = $service->bindOnu($olt, [
            'ponPort' => $data['pon_port'] ?? '',
            'serial' => $data['onu_serial'] ?? null,
            'mac' => $data['onu_mac'] ?? null,
            'description' => $client->full_name,
        ]);

        if (! $result['ok']) {
            return response()->json(['success' => false, 'message' => 'OLT_BIND_FAILED: '.$result['message']], 422);
        }

        $client->update([
            'olt_id' => $data['olt_id'],
            'onu_mac' => $data['onu_mac'] ?? null,
            'onu_serial' => $data['onu_serial'] ?? null,
            'pon_port' => $data['pon_port'] ?? null,
        ]);

        if (! empty($data['onu_serial'])) {
            Onu::updateOrCreate(
                ['serial_number' => $data['onu_serial']],
                [
                    'olt_id' => $data['olt_id'],
                    'client_id' => $client->id,
                    'mac_address' => $data['onu_mac'] ?? null,
                    'pon_port' => $data['pon_port'] ?? null,
                    'status' => 'bound',
                    'last_seen_at' => now(),
                ]
            );
        }

        return response()->json(['success' => true, 'message' => 'ONU_BOUND']);
    }

    public function queue(Client $client, MikroTikServiceFactory $factory): JsonResponse
    {
        $package = Package::where('name', $client->package_name)
            ->where('mikrotik_id', $client->mikrotik_id)
            ->first();

        $isStaticIp = $package && preg_match('/^\d{1,3}(\.\d{1,3}){3}$/', (string) $package->remote_address);

        if (! $isStaticIp) {
            return response()->json([
                'success' => true,
                'skipped' => true,
                'message' => 'Dynamic PPPoE IP — rate limiting already enforced via PPP profile.',
            ]);
        }

        try {
            $router = Mikrotik::findOrFail($client->mikrotik_id);
            $factory->make($router)->addSimpleQueue($client->pppoe_username, $package->remote_address, $package->rate_limit);

            return response()->json(['success' => true, 'message' => 'BANDWIDTH_QUEUE_APPLIED']);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => 'ROUTER_UNREACHABLE: '.$e->getMessage()], 422);
        }
    }

    public function expiry(Client $client): JsonResponse
    {
        if (! $client->expiry_date) {
            $client->update(['expiry_date' => now()->addDays(30)->toDateString()]);
        }

        return response()->json(['success' => true, 'message' => 'EXPIRY_SET: '.$client->expiry_date]);
    }

    public function sms(Client $client, SmsService $service): JsonResponse
    {
        $result = $service->sendWelcomeMessage($client);

        if (! $result['ok']) {
            return response()->json(['success' => false, 'message' => $result['message']], 422);
        }

        return response()->json(['success' => true, 'message' => 'WELCOME_SMS_SENT']);
    }
}
