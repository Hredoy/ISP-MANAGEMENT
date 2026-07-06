<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Mikrotik;
use App\Models\Payment;
use App\Services\MikroTik\MikroTikServiceFactory;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $today = now()->toDateString();

        return Inertia::render('Dashboard', [
            'routers' => Mikrotik::all(['id', 'name']),
            'billing' => [
                'revenue_today' => (float) Payment::where('status', 'completed')->whereDate('paid_at', $today)->sum('amount'),
                'revenue_month' => (float) Payment::where('status', 'completed')
                    ->whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()])->sum('amount'),
                'active_clients' => Client::where('status', 'Active')->where('expiry_date', '>=', $today)->count(),
                'suspended_clients' => Client::where('status', 'Suspended')->count(),
                'expired_clients' => Client::where('status', '!=', 'Suspended')->where('expiry_date', '<', $today)->count(),
            ],
            'recentPayments' => $this->recentPaymentsQuery(),
        ]);
    }

    /**
     * Async widget data (see resources/js/Components/Dashboard/DevicesWidget.vue): kept out of
     * index()'s Inertia props so the page renders instantly behind a skeleton loader instead of
     * blocking on a live testConnection() round trip per router.
     */
    public function devicesStatus(MikroTikServiceFactory $factory): JsonResponse
    {
        $routers = Mikrotik::all();
        $online = 0;

        foreach ($routers as $router) {
            try {
                if ($factory->make($router)->testConnection()['ok'] ?? false) {
                    $online++;
                }
            } catch (Throwable) {
                // Counted as offline below.
            }
        }

        return response()->json([
            'total' => $routers->count(),
            'online' => $online,
            'offline' => $routers->count() - $online,
        ]);
    }

    public function recentPayments(): JsonResponse
    {
        return response()->json(['payments' => $this->recentPaymentsQuery()]);
    }

    private function recentPaymentsQuery()
    {
        return Payment::with('client:id,full_name')
            ->latest()
            ->limit(10)
            ->get(['id', 'client_id', 'amount', 'status', 'method', 'paid_at', 'created_at']);
    }
}
