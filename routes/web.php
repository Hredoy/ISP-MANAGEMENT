<?php

use App\Http\Controllers\API\BillingController;
use App\Http\Controllers\API\ResellerController;
use App\Http\Controllers\API\ChatbotController;
use App\Http\Controllers\API\ClientController;
use App\Http\Controllers\API\ClientImportController;
use App\Http\Controllers\API\ClientProvisioningController;
use App\Http\Controllers\API\DashboardController;
use App\Http\Controllers\API\IntegrationController;
use App\Http\Controllers\API\Mikrotik\MikrotikController;
use App\Http\Controllers\API\Mikrotik\MikrotikFirewallController;
use App\Http\Controllers\API\Mikrotik\MikrotikPppoeSessionController;
use App\Http\Controllers\API\Mikrotik\MikrotikQueueTreeController;
use App\Http\Controllers\API\OltController;
use App\Http\Controllers\API\PackageController;
use App\Http\Controllers\API\PaymentSmsMatchController;
use App\Http\Controllers\API\SettingsController;
use App\Http\Controllers\API\SubscriberController;
use App\Http\Controllers\API\SubZoneController;
use App\Http\Controllers\API\TicketController;
use App\Http\Controllers\API\ZoneController;
use App\Http\Controllers\LandlordTenantController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TenantApplicationController;
use App\Http\Controllers\TenantFrontendController;
use App\Http\Controllers\TenantHrmController;
use App\Http\Controllers\TenantRolePermissionController;
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

// SMS reader companion app: device-token auth, no browser session.
Route::post('/api/payments/sms-match', PaymentSmsMatchController::class)
    ->middleware(['tenant.module:sms', 'auth.sms_device'])
    ->name('api.payments.sms-match');

Route::middleware(['auth', 'verified'])->group(function () {

    Route::get('/dashboard', [DashboardController::class, 'index'])->middleware(EnsureTenantContext::class)->name('dashboard');
    Route::get('/dashboard/widgets/devices-status', [DashboardController::class, 'devicesStatus'])
        ->middleware(EnsureTenantContext::class)->name('dashboard.widgets.devices-status');
    Route::get('/dashboard/widgets/recent-payments', [DashboardController::class, 'recentPayments'])
        ->middleware(EnsureTenantContext::class)->name('dashboard.widgets.recent-payments');

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
        Route::prefix('clients-import')->name('clients.import.')->middleware('tenant.module:customers')->group(function () {
            Route::get('/', [ClientImportController::class, 'create'])->name('create');
            Route::get('/template', [ClientImportController::class, 'template'])->name('template');
            Route::post('/preview', [ClientImportController::class, 'preview'])->name('preview');
            Route::post('/', [ClientImportController::class, 'import'])->name('store');
        });
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
            Route::post('/{mikrotik}/sync', [MikrotikController::class, 'sync'])->name('sync');
            Route::post('/{mikrotik}/enable', [MikrotikController::class, 'enable'])->name('enable');
            Route::post('/{mikrotik}/disable', [MikrotikController::class, 'disable'])->name('disable');
            Route::post('/{mikrotik}/set-default', [MikrotikController::class, 'setDefault'])->name('set-default');
            Route::patch('/{mikrotik}/mode', [MikrotikController::class, 'updateMode'])->name('update-mode');

            // Real-time monitoring of a specific router
            Route::get('/{mikrotik}/stats', [MikrotikController::class, 'getLiveStats'])->name('mikrotik.stats');

            // Queue Tree visual editor (hierarchical parent/child bandwidth queues)
            Route::get('/{mikrotik}/queue-tree', [MikrotikQueueTreeController::class, 'index'])->name('queue-tree.index');
            Route::post('/{mikrotik}/queue-tree', [MikrotikQueueTreeController::class, 'store'])->name('queue-tree.store');
            Route::put('/{mikrotik}/queue-tree/{name}', [MikrotikQueueTreeController::class, 'update'])->name('queue-tree.update');
            Route::delete('/{mikrotik}/queue-tree/{name}', [MikrotikQueueTreeController::class, 'destroy'])->name('queue-tree.destroy');

            // Firewall rule management (no Winbox needed)
            Route::get('/{mikrotik}/firewall', [MikrotikFirewallController::class, 'index'])->name('firewall.index');
            Route::post('/{mikrotik}/firewall', [MikrotikFirewallController::class, 'store'])->name('firewall.store');
            Route::put('/{mikrotik}/firewall/{rule}', [MikrotikFirewallController::class, 'update'])->name('firewall.update');
            Route::delete('/{mikrotik}/firewall/{rule}', [MikrotikFirewallController::class, 'destroy'])->name('firewall.destroy');
            Route::post('/{mikrotik}/firewall/{rule}/move', [MikrotikFirewallController::class, 'move'])->name('firewall.move');

            // PPPoE mass session management
            Route::get('/{mikrotik}/pppoe-sessions', [MikrotikPppoeSessionController::class, 'index'])->name('pppoe-sessions.index');
            Route::delete('/{mikrotik}/pppoe-sessions/{username}', [MikrotikPppoeSessionController::class, 'destroy'])->name('pppoe-sessions.destroy');
            Route::post('/{mikrotik}/pppoe-sessions/bulk-kill', [MikrotikPppoeSessionController::class, 'bulkDestroy'])->name('pppoe-sessions.bulk-kill');
        });

        // --- OLT DEVICE MANAGEMENT ---
        Route::resource('olts', OltController::class)->except('show')->middleware('tenant.module:olt');
        Route::post('olts/{olt}/check-connection', [OltController::class, 'checkConnection'])
            ->middleware('tenant.module:olt')->name('olts.check-connection');
        Route::post('olts/{olt}/detect-vendor', [OltController::class, 'detectVendor'])
            ->middleware('tenant.module:olt')->name('olts.detect-vendor');
        Route::post('olts/{olt}/sync-onus', [OltController::class, 'syncOnus'])
            ->middleware('tenant.module:olt')->name('olts.sync-onus');
        Route::get('olts/{olt}/onus', [OltController::class, 'onus'])
            ->middleware('tenant.module:olt')->name('olts.onus');
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

        // --- AI CHATBOT ---
        Route::prefix('chatbot')->name('chatbot.')->group(function () {
            Route::get('/', [ChatbotController::class, 'index'])->name('index');
            Route::post('ask', [ChatbotController::class, 'ask'])->name('ask');
        });
        // --- AI CHATBOT ---

        Route::get('subscribers', [SubscriberController::class, 'index'])
            ->middleware('tenant.module:customers')->name('subscribers.index');

        Route::prefix('billing')->name('billing.')->middleware('tenant.module:billing')->group(function () {
            Route::get('/', [BillingController::class, 'index'])->name('index');
            Route::post('payments', [BillingController::class, 'storePayment'])->name('payments.store');
        });

        // --- RESELLER MANAGEMENT ---
        Route::prefix('resellers')->name('resellers.')->group(function () {
            Route::get('/', [ResellerController::class, 'index'])->name('index');
            Route::get('/create', [ResellerController::class, 'create'])->name('create');
            Route::post('/', [ResellerController::class, 'store'])->name('store');
            Route::get('/my-dashboard', [ResellerController::class, 'dashboard'])->name('dashboard');
            Route::get('/{reseller}', [ResellerController::class, 'show'])->name('show');
            Route::get('/{reseller}/edit', [ResellerController::class, 'edit'])->name('edit');
            Route::patch('/{reseller}', [ResellerController::class, 'update'])->name('update');
            Route::delete('/{reseller}', [ResellerController::class, 'destroy'])->name('destroy');
            Route::post('/{reseller}/commissions/{commission}/pay', [ResellerController::class, 'markCommissionPaid'])->name('commissions.pay');
        });
        // --- RESELLER MANAGEMENT ---

        // --- SUPPORT TICKETS ---
        Route::prefix('tickets')->name('tickets.')->group(function () {
            Route::get('/', [TicketController::class, 'index'])->name('index');
            Route::post('/', [TicketController::class, 'store'])->name('store');
        });
        // --- SUPPORT TICKETS ---

        // --- SETTINGS ---
        Route::prefix('settings')->name('settings.')->group(function () {
            Route::get('/', [SettingsController::class, 'index'])->middleware('tenant.module:settings')->name('index');
            Route::patch('/', [SettingsController::class, 'update'])->middleware('tenant.module:settings')->name('update');
            Route::get('mikrotik-mode', [SettingsController::class, 'editMikrotikMode'])->middleware('tenant.module:mikrotik')->name('mikrotik-mode.edit');
            Route::post('mikrotik-mode', [SettingsController::class, 'updateMikrotikMode'])->middleware('tenant.module:mikrotik')->name('mikrotik-mode.update');
        });
        // --- SETTINGS ---

        // --- HRM + TENANT ROLE PERMISSIONS ---
        Route::prefix('hrm')->name('hrm.')->middleware('tenant.module:hrm')->group(function () {
            Route::get('/', [TenantHrmController::class, 'index'])
                ->middleware('tenant.permission:hrm.view')->name('index');
            Route::post('employees', [TenantHrmController::class, 'storeEmployee'])
                ->middleware('tenant.permission:employees.create')->name('employees.store');
            Route::patch('employees/{employee}', [TenantHrmController::class, 'updateEmployee'])
                ->middleware('tenant.permission:employees.edit')->name('employees.update');
            Route::delete('employees/{employee}', [TenantHrmController::class, 'archiveEmployee'])
                ->middleware('tenant.permission:employees.delete')->name('employees.archive');
            Route::post('employees/{employee}/documents', [TenantHrmController::class, 'storeDocument'])
                ->middleware('tenant.permission:employee_documents.create')->name('employees.documents.store');
            Route::post('setup/{type}', [TenantHrmController::class, 'storeSetup'])
                ->middleware('tenant.permission:hrm.create')->name('setup.store');
        });

        Route::prefix('roles-permissions')->name('roles-permissions.')->middleware(['tenant.module:hrm', 'tenant.permission:role_permissions.view'])->group(function () {
            Route::get('/', [TenantRolePermissionController::class, 'index'])->name('index');
            Route::patch('roles/{role}', [TenantRolePermissionController::class, 'update'])
                ->middleware('tenant.permission:role_permissions.edit')->name('roles.update');
        });
        // --- HRM + TENANT ROLE PERMISSIONS ---

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
