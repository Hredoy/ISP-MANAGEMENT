<?php

use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\OnboardingController;
use Illuminate\Support\Facades\Route;

Route::post('/onboard', OnboardingController::class)->name('api.onboard');

Route::prefix('auth')->name('api.auth.')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->name('login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me'])->name('me');
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    });
});

Route::middleware(['auth:sanctum', 'role:Super Admin|Network Engineer|Billing Manager|Technician|Reseller|Support Agent|Client'])->group(function () {
    Route::get('/foundation/ping', fn () => response()->json(['ok' => true]))->name('api.foundation.ping');
});
