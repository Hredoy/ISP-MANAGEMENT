<?php

use App\Http\Controllers\API\ClientController;
use App\Http\Controllers\API\ClientProvisioningController;
use App\Http\Controllers\API\DashboardController;
use App\Http\Controllers\API\IntegrationController;
use App\Http\Controllers\API\Mikrotik\MikrotikController;
use App\Http\Controllers\API\OltController;
use App\Http\Controllers\API\PackageController;
use App\Http\Controllers\API\SubZoneController;
use App\Http\Controllers\API\ZoneController;
use App\Http\Controllers\LandlordTenantController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TenantApplicationController;
use App\Http\Controllers\TenantFrontendController;
use App\Http\Controllers\TenantWebsiteController;
use App\Http\Middleware\EnsureTenantContext;
use App\Models\Module;
use App\Services\TenantProvisioningService;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function (TenantProvisioningService $provisioningService) {
    if (tenancy()->initialized) {
        return app(TenantWebsiteController::class)->home(request());
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

Route::middleware('throttle:10,1')->name('tenant.website.')->group(function () {
    Route::get('/packages', [TenantWebsiteController::class, 'packages'])->name('packages');
    Route::get('/new-connection', [TenantWebsiteController::class, 'newConnection'])->name('new-connection');
    Route::get('/contact', [TenantWebsiteController::class, 'contact'])->name('contact');
    Route::get('/blogs', [TenantWebsiteController::class, 'blogs'])->name('blogs');
    Route::get('/blogs/{slug}', [TenantWebsiteController::class, 'blog'])->name('blogs.show');
    Route::post('/connection-requests', [TenantWebsiteController::class, 'storeConnection'])->name('connection.store');
    Route::post('/complaints', [TenantWebsiteController::class, 'storeComplaint'])->name('complaints.store');
    Route::post('/referrals', [TenantWebsiteController::class, 'storeReferral'])->name('referrals.store');
    Route::post('/manual-payments', [TenantWebsiteController::class, 'storeManualPayment'])->name('manual-payments.store');
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
        Route::resource('packages', PackageController::class)->except('show')->middleware('tenant.module:packages');
        Route::get('frontend', [TenantFrontendController::class, 'index'])->name('frontend.index');
        Route::patch('frontend', [TenantFrontendController::class, 'update'])->name('frontend.update');
        // --- ZONE MANAGEMENT ---

        // --- CLIENT MANAGEMENT ---
        Route::resource('clients', ClientController::class)->except('show')->middleware('tenant.module:customers');
        Route::post('clients/{client}/suspend', [ClientController::class, 'suspend'])
            ->middleware('tenant.module:customers')->name('clients.suspend');
        Route::post('clients/{client}/unsuspend', [ClientController::class, 'unsuspend'])
            ->middleware('tenant.module:customers')->name('clients.unsuspend');

        // One-click provisioning wizard: 6 independently retryable JSON steps.
        Route::prefix('clients/provision')->name('clients.provision.')->middleware('tenant.module:customers')->group(function () {
            Route::post('client', [ClientProvisioningController::class, 'createClient'])->name('client');
            Route::post('{client}/pppoe', [ClientProvisioningController::class, 'pppoe'])->name('pppoe');
            Route::post('{client}/onu', [ClientProvisioningController::class, 'onu'])->name('onu');
            Route::post('{client}/queue', [ClientProvisioningController::class, 'queue'])->name('queue');
            Route::post('{client}/expiry', [ClientProvisioningController::class, 'expiry'])->name('expiry');
            Route::post('{client}/sms', [ClientProvisioningController::class, 'sms'])->name('sms');
        });
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
        });

        // --- OLT DEVICE MANAGEMENT ---
        Route::resource('olts', OltController::class)->except('show')->middleware('tenant.module:olt');
        Route::post('olts/{olt}/check-connection', [OltController::class, 'checkConnection'])
            ->middleware('tenant.module:olt')->name('olts.check-connection');
        // --- OLT DEVICE MANAGEMENT ---

        // --- INTEGRATIONS (SMS GATEWAYS) ---
        Route::prefix('integrations')->name('integrations.')->middleware('tenant.module:sms')->group(function () {
            Route::get('/', [IntegrationController::class, 'index'])->name('index');
            Route::post('sms-gateways', [IntegrationController::class, 'storeSmsGateway'])->name('sms-gateways.store');
            Route::patch('sms-gateways/{smsGateway}', [IntegrationController::class, 'updateSmsGateway'])->name('sms-gateways.update');
            Route::delete('sms-gateways/{smsGateway}', [IntegrationController::class, 'destroySmsGateway'])->name('sms-gateways.destroy');
            Route::post('sms-gateways/{smsGateway}/activate', [IntegrationController::class, 'activateSmsGateway'])->name('sms-gateways.activate');
            Route::post('sms-gateways/{smsGateway}/test', [IntegrationController::class, 'testSmsGateway'])->name('sms-gateways.test');
        });
        // --- INTEGRATIONS (SMS GATEWAYS) ---

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
