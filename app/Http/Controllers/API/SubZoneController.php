<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\SubZone;
use App\Models\Zone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SubZoneController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Area/SubZones', ['zones'=> Zone::get(), 'subZones' => SubZone::with('zone')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => 'required|unique:sub_zones', 'zone_id' => 'required|exists:zones,id','manager_contact' => 'nullable']);
        SubZone::create($data);
        return back();
    }

    public function update(Request $request, SubZone $subZone): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|unique:sub_zones,name,' . $subZone->id,
            'zone_id' => 'required|exists:zones,id',
            'manager_contact' => 'nullable',
        ]);
        $subZone->update($data);
        return back();
    }

    public function destroy(SubZone $subZone): RedirectResponse
    {
        $subZone->delete();
        return back();
    }
}
