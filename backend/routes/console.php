<?php

use App\Enums\AdminRole;
use App\Enums\NotificationChannelType;
use App\Enums\NotificationEvent;
use App\Jobs\DeliverNotification;
use App\Models\Admin;
use App\Models\NotificationLog;
use App\Models\User;
use App\Services\SettlementCycle;
use App\Services\TripTimers;
use App\Support\ChannelMatrix;
use App\Support\Phone;
use Database\Seeders\SettingsSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
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

Artisan::command('musafir:tick', function (TripTimers $timers) {
    $result = $timers->tick();
    if (array_sum($result) > 0) {
        $this->info(json_encode($result));
    }
})->purpose('Booking expiry, reminders, no-show confirmation, auto-complete (runs every minute)');

// Production: one cron entry `* * * * * php artisan schedule:run` drives everything below.
Schedule::command('musafir:tick')->everyMinute()->withoutOverlapping(5)->onOneServer();

Artisan::command('musafir:weekly-statements', function (SettlementCycle $cycle) {
    $this->info('Sent '.$cycle->sendWeeklyStatements().' statements.');
})->purpose('Email the weekly balance statement to drivers');

Schedule::command('musafir:weekly-statements')
    ->weeklyOn(6, '9:00')->timezone(config('app.display_timezone'))->onOneServer(); // Saturday morning, Sudan time

Artisan::command('notifications:sync', function (ChannelMatrix $matrix) {
    $this->info('Added '.$matrix->sync().' notification event(s) to the channel matrix (existing rows kept).');
})->purpose('Add new notification events to the admin channel matrix with their default channels');

Artisan::command('notifications:prune', function () {
    $days = settings()->int('notifications.log_retention_days');
    $deleted = NotificationLog::where('created_at', '<', now()->subDays($days))->delete();
    $this->info("Deleted $deleted notification log rows older than $days days.");
})->purpose('Delete old notification log rows (retention is an admin setting)');

Schedule::command('notifications:prune')->dailyAt('01:30')->onOneServer();

Artisan::command('notifications:test {phone : Phone of a registered user, e.g. 0912345678} {--channel=all : push, whatsapp, email or all}', function () {
    $phone = Phone::normalize($this->argument('phone'));
    $user = $phone ? User::where('phone', $phone)->first() : null;
    if ($user === null) {
        $this->error('No user with this phone. Log in to the app with it first.');

        return 1;
    }

    $this->line('Drivers: whatsapp='.config('services.whatsapp.driver').', push='.config('services.push.driver').', mail='.config('mail.default'));
    $wanted = $this->option('channel');
    $failed = false;

    foreach (NotificationChannelType::cases() as $channel) {
        if ($wanted !== 'all' && $wanted !== $channel->value) {
            continue;
        }

        $job = new DeliverNotification($user->id, NotificationEvent::Broadcast->value, $channel->value,
            'رسالة تجريبية', 'هذه رسالة تجريبية من مسافر معاك للتأكد من إعدادات الإشعارات.', ['test' => true]);

        try {
            app()->call([$job, 'handle']);
            $log = NotificationLog::where('user_id', $user->id)->where('channel', $channel->value)->latest('id')->first();
            $this->line(sprintf('  %-9s %s', $channel->value, $log?->status === NotificationLog::SKIPPED ? 'skipped — '.$log->error : 'sent ✔'));
        } catch (Throwable $e) {
            $failed = true;
            $this->line(sprintf('  %-9s FAILED — %s', $channel->value, $e->getMessage()));
        }
    }

    return $failed ? 1 : 0;
})->purpose('Send a test notification to one user through the configured providers');

Artisan::command('admin:bootstrap', function () {
    $email = env('ADMIN_EMAIL');
    $pass = env('ADMIN_PASSWORD');
    if (blank($email) || blank($pass)) {
        $this->line('ADMIN_EMAIL / ADMIN_PASSWORD not set — skipped.');

        return 0;
    }
    if (Admin::where('email', $email)->exists()) {
        $this->line("Admin $email already exists — skipped.");

        return 0;
    }
    Admin::create(['name' => 'المدير', 'email' => $email, 'role' => AdminRole::SuperAdmin, 'password' => $pass]);
    $this->info("Super admin $email created.");
})->purpose('Create the first super admin from ADMIN_EMAIL / ADMIN_PASSWORD (non-interactive, for deploys)');

// Shared hosting has no long-running worker: when QUEUE_VIA_SCHEDULER=true the cron-driven
// scheduler drains the queue every minute instead (notifications go out within ~1 minute).
if (env('QUEUE_VIA_SCHEDULER', false)) {
    Schedule::command('queue:work --stop-when-empty --tries=3 --max-time=50')
        ->everyMinute()->withoutOverlapping(2);
}
