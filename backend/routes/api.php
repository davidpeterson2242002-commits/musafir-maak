<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ConfigController;
use App\Http\Controllers\Api\V1\DriverController;
use App\Http\Controllers\Api\V1\GeoController;
use App\Http\Controllers\Api\V1\IdentityController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\VehicleController;
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

        // Passenger ID verification
        Route::get('me/identity', [IdentityController::class, 'show']);
        Route::post('me/identity/documents', [IdentityController::class, 'upload']);
        Route::post('me/identity/submit', [IdentityController::class, 'submit']);

        // Driver registration
        Route::get('driver', [DriverController::class, 'show']);
        Route::post('driver', [DriverController::class, 'store']);
        Route::post('driver/documents', [DriverController::class, 'upload']);
        Route::post('driver/submit', [DriverController::class, 'submit']);

        Route::post('driver/vehicles', [VehicleController::class, 'store']);
        Route::put('driver/vehicles/{vehicle}', [VehicleController::class, 'update']);
        Route::delete('driver/vehicles/{vehicle}', [VehicleController::class, 'destroy']);
        Route::post('driver/vehicles/{vehicle}/documents', [VehicleController::class, 'upload']);
        Route::post('driver/vehicles/{vehicle}/submit', [VehicleController::class, 'submit']);
    });
});
