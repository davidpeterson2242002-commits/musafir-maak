<?php

use App\Support\Settings;

if (! function_exists('settings')) {
    /** Business settings accessor. settings('key') or settings()->int('key'). */
    function settings(?string $key = null): mixed
    {
        $settings = app(Settings::class);

        return $key === null ? $settings : $settings->get($key);
    }
}
