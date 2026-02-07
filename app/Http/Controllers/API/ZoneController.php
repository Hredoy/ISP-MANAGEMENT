<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Zone;
use Inertia\Inertia;

class ZoneController extends Controller
{
    public function index() {
        return Inertia::render('Area/Zones', ['zones' => Zone::withCount('subZones')->get()]);
    }

    public function store(Request $request) {
        $data = $request->validate(['name' => 'required|unique:zones', 'code' => 'nullable']);
        Zone::create($data);
        return back();
    }

    public function destroy(Zone $zone) {
        $zone->delete();
        return back();
    }
}
