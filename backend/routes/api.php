<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BalanceController;
use App\Http\Controllers\Api\V1\BookingController;
use App\Http\Controllers\Api\V1\ConfigController;
use App\Http\Controllers\Api\V1\ConversationController;
use App\Http\Controllers\Api\V1\DriverController;
use App\Http\Controllers\Api\V1\DriverParcelController;
use App\Http\Controllers\Api\V1\DriverTripController;
use App\Http\Controllers\Api\V1\GeoController;
use App\Http\Controllers\Api\V1\IdentityController;
use App\Http\Controllers\Api\V1\ParcelController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\RatingController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\RouteAlertController;
use App\Http\Controllers\Api\V1\TripController;
use App\Http\Controllers\Api\V1\UserProfileController;
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
    Route::post('auth/device-login', [AuthController::class, 'deviceLogin'])->middleware('throttle:otp');

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

        // Driver trips and requests
        Route::get('driver/trips', [DriverTripController::class, 'index']);
        Route::post('driver/trips', [DriverTripController::class, 'store']);
        Route::get('driver/trips/{trip}', [DriverTripController::class, 'show']);
        Route::put('driver/trips/{trip}', [DriverTripController::class, 'update']);
        Route::post('driver/trips/{trip}/cancel', [DriverTripController::class, 'cancel']);
        Route::post('driver/trips/{trip}/depart', [DriverTripController::class, 'depart']);
        Route::post('driver/trips/{trip}/complete', [DriverTripController::class, 'complete']);
        Route::get('driver/requests', [DriverTripController::class, 'requests']);
        Route::post('driver/bookings/{booking}/accept', [DriverTripController::class, 'accept']);
        Route::post('driver/bookings/{booking}/reject', [DriverTripController::class, 'reject']);
        Route::post('driver/bookings/{booking}/no-show', [DriverTripController::class, 'noShow']);

        // Driver money
        Route::get('driver/balance', [BalanceController::class, 'show']);
        Route::get('driver/ledger', [BalanceController::class, 'ledger']);
        Route::get('driver/settlements', [BalanceController::class, 'settlements']);
        Route::post('driver/settlements', [BalanceController::class, 'submit']);
        Route::get('payment-methods', [BalanceController::class, 'paymentMethods']);

        // Passenger: search, trips, bookings
        Route::get('trips/search', [TripController::class, 'search']);
        Route::get('trips/{trip}', [TripController::class, 'show']);
        Route::post('trips/{trip}/bookings', [BookingController::class, 'store']);
        Route::get('bookings', [BookingController::class, 'index']);
        Route::get('bookings/{booking}', [BookingController::class, 'show']);
        Route::post('bookings/{booking}/cancel', [BookingController::class, 'cancel']);
        Route::post('bookings/{booking}/dispute-no-show', [BookingController::class, 'disputeNoShow']);

        // Parcels (sender)
        Route::post('trips/{trip}/parcels', [ParcelController::class, 'store']);
        Route::get('parcels', [ParcelController::class, 'index']);
        Route::get('parcels/{parcel}', [ParcelController::class, 'show']);
        Route::post('parcels/{parcel}/cancel', [ParcelController::class, 'cancel']);
        Route::post('parcels/{parcel}/dispute', [ParcelController::class, 'dispute']);

        // Parcels (driver)
        Route::get('driver/parcels', [DriverParcelController::class, 'index']);
        Route::post('driver/parcels/{parcel}/accept', [DriverParcelController::class, 'accept']);
        Route::post('driver/parcels/{parcel}/reject', [DriverParcelController::class, 'reject']);
        Route::post('driver/parcels/{parcel}/pickup', [DriverParcelController::class, 'pickup']);
        Route::post('driver/parcels/{parcel}/refuse', [DriverParcelController::class, 'refuse']);
        Route::post('driver/parcels/{parcel}/deliver', [DriverParcelController::class, 'deliver'])->middleware('throttle:20,1');

        // Chat
        Route::get('conversations', [ConversationController::class, 'index']);
        Route::get('me/badges', \App\Http\Controllers\Api\V1\BadgesController::class);
        Route::get('conversations/unread-count', [ConversationController::class, 'unreadCount']);
        Route::get('conversations/{conversation}/messages', [ConversationController::class, 'messages']);
        Route::post('conversations/{conversation}/messages', [ConversationController::class, 'send'])->middleware('throttle:30,1');

        // Ratings, profiles, blocks, reports
        Route::post('ratings', [RatingController::class, 'store']);
        Route::get('ratings/pending', [RatingController::class, 'pending']);
        Route::get('users/{user}', [UserProfileController::class, 'show']);
        Route::post('users/{user}/block', [UserProfileController::class, 'block']);
        Route::delete('users/{user}/block', [UserProfileController::class, 'unblock']);
        Route::get('me/blocks', [UserProfileController::class, 'blocks']);
        Route::post('reports', [ReportController::class, 'store'])->middleware('throttle:10,1');

        // Route alerts
        Route::get('route-alerts', [RouteAlertController::class, 'index']);
        Route::post('route-alerts', [RouteAlertController::class, 'store']);
        Route::delete('route-alerts/{alert}', [RouteAlertController::class, 'destroy']);
    });
});
