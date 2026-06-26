<?php

namespace App\Http\Controllers;

use App\Models\TenantApplication;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class TenantApplicationController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Tenant/Apply');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'organization_name' => ['required', 'string', 'max:255'],
            'contact_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
        ]);

        TenantApplication::create([
            ...$validated,
            'slug' => Str::slug($validated['organization_name']).'-'.Str::lower(Str::random(4)),
            'status' => 'pending',
        ]);

        return redirect()->back()->with('success', 'Application submitted. Landlord will review your request.');
    }
}
