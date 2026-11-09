<?php

use App\Support\Settings;
use Illuminate\Support\Carbon;

if (! function_exists('settings')) {
    /** Business settings accessor. settings('key') or settings()->int('key'). */
    function settings(?string $key = null): mixed
    {
        $settings = app(Settings::class);

        return $key === null ? $settings : $settings->get($key);
    }
}

if (! function_exists('local_time')) {
    /** A UTC timestamp converted to Sudan time (config app.display_timezone). */
    function local_time(?DateTimeInterface $time): ?Carbon
    {
        return $time === null ? null : Carbon::instance($time)->setTimezone(config('app.display_timezone'));
    }
}

if (! function_exists('local_iso')) {
    /** ISO-8601 in Sudan time, e.g. 2026-11-12T07:30:00+02:00. */
    function local_iso(?DateTimeInterface $time): ?string
    {
        return local_time($time)?->toIso8601String();
    }
}
