<?php

namespace Database\Seeders;

use App\Enums\AdminRole;
use App\Models\Admin;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([SettingsSeeder::class, GeoSeeder::class]);

        if (app()->environment('local')) {
            Admin::firstOrCreate(
                ['email' => 'admin@musafir.test'],
                ['name' => 'المدير', 'password' => 'password', 'role' => AdminRole::SuperAdmin],
            );
        }
    }
}
