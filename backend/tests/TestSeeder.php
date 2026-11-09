<?php

namespace Tests;

use Database\Seeders\GeoSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Database\Seeder;

class TestSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([SettingsSeeder::class, GeoSeeder::class]);
    }
}
