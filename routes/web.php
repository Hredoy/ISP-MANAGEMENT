<?php

use App\Http\Controllers\API\ClientController;
use App\Http\Controllers\API\DashboardController;
use App\Http\Controllers\API\Mikrotik\MikrotikController;
use App\Http\Controllers\API\PackageController;
use App\Http\Controllers\API\SubZoneController;
use App\Http\Controllers\API\ZoneController;
use App\Http\Controllers\LandlordTenantController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TenantApplicationController;
use App\Http\Middleware\EnsureTenantContext;
use App\Models\Module;
use App\Models\Package;
use App\Models\Setting;
use App\Services\TenantProvisioningService;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function (TenantProvisioningService $provisioningService) {
    if (tenancy()->initialized) {
        $organization = Setting::on('tenant')->where('key', 'organization')->value('value') ?? [];
        $network = Setting::on('tenant')->where('key', 'network')->value('value') ?? [];

        return Inertia::render('Tenant/Website', [
            'tenant' => [
                'id' => tenant()->getTenantKey(),
                'name' => $organization['name'] ?? tenant('organization_name') ?? 'Cloud Fiber ISP',
                'phone' => $organization['phone'] ?? '09611-000-000',
                'email' => $organization['email'] ?? 'support@example.com',
                'address' => $organization['address'] ?? 'Service center address',
                'business_type' => $organization['business_type'] ?? 'Broadband Internet Service Provider',
                'district' => $organization['district'] ?? null,
                'mikrotik_ip' => $network['mikrotik_ip'] ?? null,
            ],
            'packages' => Package::on('tenant')
                ->orderBy('price')
                ->get(['name', 'rate_limit', 'price', 'description'])
                ->values(),
        ]);
    }

    $provisioningService->ensureModulesSeeded();

    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
        'modules' => Module::where('is_active', true)->orderBy('sort_order')->get(['name', 'slug']),
    ]);
});

Route::get('/apply-organization', [TenantApplicationController::class, 'create'])->name('tenant.apply.create');
Route::post('/apply-organization', [TenantApplicationController::class, 'store'])->name('tenant.apply.store');

Route::middleware(['auth', 'verified'])->group(function () {

    Route::get('/dashboard', [DashboardController::class, 'index'])->middleware(EnsureTenantContext::class)->name('dashboard');

    Route::prefix('landlord')->name('landlord.')->group(function () {
        Route::get('/', [LandlordTenantController::class, 'index'])->name('index');
        Route::get('/dashboard', [LandlordTenantController::class, 'dashboard'])->name('dashboard');
        Route::get('/applications', [LandlordTenantController::class, 'applications'])->name('applications.index');
        Route::get('/tenants', [LandlordTenantController::class, 'tenants'])->name('tenants.index');
        Route::post('/applications', [LandlordTenantController::class, 'store'])->name('applications.store');
        Route::post('/tenants/{application}/approve', [LandlordTenantController::class, 'approve'])->name('tenants.approve');
        Route::post('/tenants/{application}/reject', [LandlordTenantController::class, 'reject'])->name('tenants.reject');
        Route::post('/tenants/{application}/convert', [LandlordTenantController::class, 'convert'])->name('tenants.convert');
        Route::patch('/tenants/{tenant}/modules', [LandlordTenantController::class, 'updateModules'])->name('tenants.modules.update');
        Route::patch('/tenants/{tenant}/status', [LandlordTenantController::class, 'updateStatus'])->name('tenants.status.update');
    });

    Route::group(['prefix' => 'dashboard', 'as' => 'dashboard.', 'middleware' => EnsureTenantContext::class], function () {
        // --- ZONE MANAGEMENT ---
        Route::resource('zones', ZoneController::class);
        Route::resource('sub-zones', SubZoneController::class);
        Route::resource('packages', PackageController::class)->middleware('tenant.module:packages');
        // --- ZONE MANAGEMENT ---

        // --- CLIENT MANAGEMENT ---
        Route::resource('clients', ClientController::class)->middleware('tenant.module:customers');
        // --- CLIENT MANAGEMENT ---

        // --- MIKROTIK NODE MANAGEMENT ---
        Route::prefix('mikrotik')->name('mikrotik.')->middleware('tenant.module:mikrotik')->group(function () {
            // List all connected routers
            Route::get('/', [MikrotikController::class, 'index'])->name('index');

            // Add new node
            Route::get('/create', [MikrotikController::class, 'create'])->name('create');
            Route::post('/', [MikrotikController::class, 'store'])->name('store');

            // Manage specific node
            Route::get('/{mikrotik}/edit', [MikrotikController::class, 'edit'])->name('edit');
            Route::put('/{mikrotik}', [MikrotikController::class, 'update'])->name('update');
            Route::delete('/{mikrotik}', [MikrotikController::class, 'destroy'])->name('destroy');
            Route::post('/{mikrotik}/check-connection', [MikrotikController::class, 'checkConnection'])->name('check-connection');

            // Real-time monitoring of a specific router
            Route::get('/{mikrotik}/stats', [MikrotikController::class, 'getLiveStats'])->name('mikrotik.stats');
            Route::get('/{mikrotik}/monitor', [MikrotikController::class, 'monitor'])->name('monitor');
        });

        // --- FUTURE: SUBSCRIBERS & BILLING ---
        // Route::resource('subscribers', SubscriberController::class);
    });
});
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
