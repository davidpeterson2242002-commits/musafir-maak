<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ConfigController;
use App\Http\Controllers\Api\V1\GeoController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Middleware\EnsureUserNotBanned;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('throttle:api')->group(function () {
    // Public
    Route::get('config', ConfigController::class);
    Route::get('cities', [GeoController::class, 'cities']);
    Route::get('routes', [GeoController::class, 'routes']);

    Route::post('auth/otp', [AuthController::class, 'requestOtp'])->middleware('throttle:otp');
    Route::post('auth/verify', [AuthController::class, 'verifyOtp'])->middleware('throttle:otp');

    // Authenticated
    Route::middleware(['auth:sanctum', EnsureUserNotBanned::class])->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);

        Route::get('me', [ProfileController::class, 'show']);
        Route::put('me', [ProfileController::class, 'update']);
        Route::post('me/photo', [ProfileController::class, 'updatePhoto']);
        Route::delete('me', [ProfileController::class, 'destroy']);

        Route::post('devices', [ProfileController::class, 'registerDevice']);
        Route::delete('devices', [ProfileController::class, 'unregisterDevice']);
    });
});
