<?php

namespace Tests\Feature\Admin;

use App\Enums\AdminRole;
use App\Filament\Resources\NotificationChannels\Pages\ManageNotificationChannels;
use App\Models\Admin;
use App\Models\AppSecret;
use App\Services\MailSetup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class MailSetupTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_saves_smtp_and_it_is_applied_with_the_password_encrypted(): void
    {
        $this->actingAs(Admin::factory()->create(['role' => AdminRole::SuperAdmin]), 'admin');

        Livewire::test(ManageNotificationChannels::class)
            ->callAction('mail', [
                'host' => 'smtp.hostinger.com', 'port' => 465, 'encryption' => 'ssl',
                'username' => 'noreply@statement-ds.com', 'password' => 'S3cret!pass', 'from_name' => 'مسافر معاك',
            ])
            ->assertHasNoActionErrors();

        $raw = DB::table('app_secrets')->where('key', MailSetup::KEY)->value('value');
        $this->assertStringNotContainsString('S3cret', $raw);
        $this->assertSame('S3cret!pass', app(MailSetup::class)->current()['password']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'mail.smtp_set']);
        $this->assertStringNotContainsString('S3cret', (string) DB::table('audit_logs')->where('action', 'mail.smtp_set')->value('changes'));

        // Saving again without a password keeps the old one.
        Livewire::test(ManageNotificationChannels::class)
            ->callAction('mail', ['host' => 'smtp.hostinger.com', 'port' => 587, 'encryption' => 'tls', 'username' => 'noreply@statement-ds.com', 'password' => ''])
            ->assertHasNoActionErrors();
        $this->assertSame('S3cret!pass', app(MailSetup::class)->current()['password']);
        $this->assertSame(587, app(MailSetup::class)->current()['port']);

        // Outside tests (array mailer) the saved mailbox becomes the default mailer.
        config(['mail.default' => 'log']);
        app(MailSetup::class)->apply();
        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('smtp.hostinger.com', config('mail.mailers.smtp.host'));
        $this->assertSame('noreply@statement-ds.com', config('mail.from.address'));
    }

    public function test_first_save_needs_a_password(): void
    {
        $this->actingAs(Admin::factory()->create(['role' => AdminRole::SuperAdmin]), 'admin');

        Livewire::test(ManageNotificationChannels::class)
            ->callAction('mail', ['host' => 'smtp.hostinger.com', 'port' => 465, 'encryption' => 'ssl', 'username' => 'noreply@statement-ds.com'])
            ->assertHasActionErrors(['password']);
        $this->assertSame(0, AppSecret::count());
    }
}
