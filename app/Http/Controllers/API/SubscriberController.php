<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Package;
use App\Models\Zone;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SubscriberController extends Controller
{
    public function index(Request $request): Response
    {
        $subscribers = Client::with(['zone:id,name', 'sub_zone:id,name'])
            ->search($request->string('search')->toString() ?: null)
            ->filter($request->only(['zone_id', 'status', 'package_name']))
            ->latest()
            ->paginate((int) $request->integer('per_page', 25))
            ->withQueryString();

        return Inertia::render('Subscribers/Index', [
            'subscribers' => $subscribers,
            'filters' => $request->only(['search', 'zone_id', 'status', 'package_name']),
            'zones' => Zone::orderBy('name')->get(['id', 'name']),
            'packages' => Package::orderBy('name')->get(['name']),
            'stats' => [
                'total' => Client::count(),
                'active' => Client::where('status', 'Active')->where('expiry_date', '>=', now()->toDateString())->count(),
                'expired' => Client::where('status', '!=', 'Suspended')->where('expiry_date', '<', now()->toDateString())->count(),
                'suspended' => Client::where('status', 'Suspended')->count(),
            ],
        ]);
    }
}
