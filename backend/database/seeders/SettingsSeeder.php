<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Support\SettingsRegistry;
use Illuminate\Database\Seeder;

/**
 * Inserts any registry setting that doesn't exist yet with its default.
 * Never overwrites values the admin has changed; refreshes type/group/label.
 */
class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        foreach (SettingsRegistry::definitions() as $key => $def) {
            $setting = Setting::firstOrNew(['key' => $key]);

            if (! $setting->exists) {
                $setting->value = $def['type']->cast($def['default']);
            }

            $setting->fill(['type' => $def['type'], 'group' => $def['group'], 'label' => $def['label']]);
            $setting->save();
        }
    }
}
