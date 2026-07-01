<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Olt;
use App\Models\SmsGateway;
use App\Services\Sms\SmsGatewayDriverFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class IntegrationController extends Controller
{
    private const PROVIDERS = ['ssl_wireless', 'alpha_sms', 'twilio', 'custom'];

    public function index(): Response
    {
        return Inertia::render('Integrations/Index', [
            'smsGateways' => SmsGateway::orderBy('display_name')->get(),
            'olts' => Olt::orderBy('name')->get(['id', 'name', 'vendor', 'is_active']),
        ]);
    }

    public function storeSmsGateway(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'provider' => 'required|in:'.implode(',', self::PROVIDERS),
            'display_name' => 'required|string|max:255',
            'credentials' => 'nullable|array',
        ]);

        SmsGateway::create([...$data, 'is_active' => false]);

        return back()->with('message', 'SMS_GATEWAY_ADDED');
    }

    public function updateSmsGateway(Request $request, SmsGateway $smsGateway): RedirectResponse
    {
        $data = $request->validate([
            'display_name' => 'required|string|max:255',
            'credentials' => 'nullable|array',
        ]);

        $smsGateway->update($data);

        return back()->with('message', 'SMS_GATEWAY_UPDATED');
    }

    public function destroySmsGateway(SmsGateway $smsGateway): RedirectResponse
    {
        $smsGateway->delete();

        return back()->with('message', 'SMS_GATEWAY_REMOVED');
    }

    public function activateSmsGateway(SmsGateway $smsGateway): RedirectResponse
    {
        SmsGateway::where('id', '!=', $smsGateway->id)->update(['is_active' => false]);
        $smsGateway->update(['is_active' => true]);

        return back()->with('message', 'SMS_GATEWAY_ACTIVATED');
    }

    public function testSmsGateway(Request $request, SmsGateway $smsGateway, SmsGatewayDriverFactory $factory): JsonResponse
    {
        $data = $request->validate(['phone' => 'required|string']);

        $result = $factory->make($smsGateway)->send($smsGateway->credentials, $data['phone'], 'This is a test message from your ISP portal Integrations panel.');

        return response()->json($result, $result['ok'] ? 200 : 422);
    }
}
