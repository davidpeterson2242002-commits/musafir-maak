<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

/**
 * Capacity test of 2026-11-15/16 (GitHub → Actions → Capacity test, after persistent MySQL
 * connections): 0 errors and ~26 ms per request up to 48 req/s; the server saturated at ~53 req/s.
 * One person with the app open sends ~0.1 req/s (badge poll every 12 s + screens).
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['ops.capacity_rps' => 50, 'ops.requests_per_user_per_second' => 0.1] as $key => $value) {
            $row = Setting::where('key', $key)->first();
            if ($row && (float) $row->value <= 0.0 || ($row && $key === 'ops.requests_per_user_per_second' && (float) $row->value === 0.12)) {
                settings()->set($key, $value);
            }
        }
    }

    public function down(): void {}
};
