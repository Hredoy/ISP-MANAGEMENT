<?php

use App\Http\Controllers\API\OnboardingController;
use Illuminate\Support\Facades\Route;

Route::post('/onboard', OnboardingController::class)->name('api.onboard');
