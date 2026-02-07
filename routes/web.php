<?php

use App\Http\Controllers\API\ClientController;
use App\Http\Controllers\API\DashboardController;
use App\Http\Controllers\API\Mikrotik\MikrotikController;
use App\Http\Controllers\API\PackageController;
use App\Http\Controllers\API\SubZoneController;
use App\Http\Controllers\API\ZoneController;
use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::middleware(['auth', 'verified'])->group(function () {

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::group(['prefix' => 'dashboard', 'as' => 'dashboard.'], function () {
        // --- ZONE MANAGEMENT ---
        Route::resource('zones', ZoneController::class);
        Route::resource('sub-zones', SubZoneController::class);
        Route::resource('packages', PackageController::class);
        // --- ZONE MANAGEMENT ---

        // --- CLIENT MANAGEMENT ---
        Route::resource('clients', ClientController::class);
        // --- CLIENT MANAGEMENT ---

        // --- MIKROTIK NODE MANAGEMENT ---
        Route::prefix('mikrotik')->name('mikrotik.')->group(function () {
            // List all connected routers
            Route::get('/', [MikrotikController::class, 'index'])->name('index');

            // Add new node
            Route::get('/create', [MikrotikController::class, 'create'])->name('create');
            Route::post('/', [MikrotikController::class, 'store'])->name('store');

            // Manage specific node
            Route::get('/{mikrotik}/edit', [MikrotikController::class, 'edit'])->name('edit');
            Route::put('/{mikrotik}', [MikrotikController::class, 'update'])->name('update');
            Route::delete('/{mikrotik}', [MikrotikController::class, 'destroy'])->name('destroy');

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
