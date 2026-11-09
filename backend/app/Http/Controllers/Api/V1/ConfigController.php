<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\SettingsRegistry;
use Illuminate\Http\JsonResponse;

class ConfigController extends Controller
{
    /** GET /api/v1/config — public app configuration and texts (no auth). */
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'settings' => settings()->publicValues(),
            'vehicle_types' => SettingsRegistry::VEHICLE_TYPES,
            'parcel_sizes' => SettingsRegistry::PARCEL_SIZES,
            'country_code' => '+249',
        ]);
    }
}
