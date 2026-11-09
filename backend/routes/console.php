<?php

use App\Enums\AdminRole;
use App\Models\Admin;
use Database\Seeders\SettingsSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Validator;

use function Laravel\Prompts\password;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

Artisan::command('settings:sync', function () {
    $this->call('db:seed', ['--class' => SettingsSeeder::class, '--force' => true]);
    settings()->flush();
    $this->info('Settings synced with the registry (existing values kept).');
})->purpose('Add new settings from SettingsRegistry with their defaults');

Artisan::command('admin:create', function () {
    $name = text('Name', required: true);
    $email = text('Email', required: true, validate: fn ($v) => Validator::make(['e' => $v], ['e' => 'email|unique:admins,email'])->fails() ? 'Invalid or taken email' : null);
    $role = select('Role', AdminRole::options(), default: AdminRole::SuperAdmin->value);
    $pass = password('Password (min 10 chars)', required: true, validate: fn ($v) => strlen($v) < 10 ? 'Too short' : null);

    Admin::create(['name' => $name, 'email' => $email, 'role' => $role, 'password' => $pass]);
    $this->info("Admin $email created.");
})->purpose('Create an admin panel account');
