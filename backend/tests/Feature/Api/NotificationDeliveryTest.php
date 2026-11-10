<?php

namespace Tests\Feature\Api;

use App\Contracts\PushGateway;
use App\Enums\NotificationEvent;
use App\Jobs\DeliverNotification;
use App\Mail\NotificationMail;
use App\Models\NotificationChannel;
use App\Models\NotificationLog;
use App\Models\Parcel;
use App\Models\User;
use App\Services\NotificationLogger;
use App\Services\Notifier;
use App\Services\ParcelService;
use App\Support\ChannelMatrix;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class NotificationDeliveryTest extends TestCase
{
    private function userWithDevice(array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->deviceTokens()->create(['token' => 'tok-'.$user->id, 'platform' => 'android']);

        return $user;
    }

    private function setChannels(NotificationEvent $event, bool $push, bool $whatsapp, bool $email): void
    {
        NotificationChannel::updateOrCreate(['event' => $event->value], compact('push', 'whatsapp', 'email'));
    }

    public function test_matrix_is_seeded_with_the_spec_defaults(): void
    {
        $this->assertSame(count(NotificationEvent::configurable()), NotificationChannel::count());
        $this->assertDatabaseMissing('notification_channels', ['event' => 'broadcast']);

        $matrix = app(ChannelMatrix::class);
        $this->assertSame(['push' => true, 'whatsapp' => true, 'email' => false], $matrix->for(NotificationEvent::BookingAccepted));
        $this->assertSame(['push' => true, 'whatsapp' => false, 'email' => false], $matrix->for(NotificationEvent::BookingRejected));
        $this->assertSame(['push' => true, 'whatsapp' => false, 'email' => true], $matrix->for(NotificationEvent::SettlementDueSoon));
        $this->assertSame(['push' => false, 'whatsapp' => false, 'email' => true], $matrix->for(NotificationEvent::WeeklyStatement));
    }

    public function test_event_without_a_row_falls_back_to_defaults_and_sync_restores_it(): void
    {
        NotificationChannel::where('event', 'trip_cancelled')->delete();

        $this->assertSame(['push' => true, 'whatsapp' => true, 'email' => false], app(ChannelMatrix::class)->for(NotificationEvent::TripCancelled));

        $this->setChannels(NotificationEvent::BookingAccepted, true, false, false);
        $this->assertSame(1, app(ChannelMatrix::class)->sync());
        $this->assertFalse(app(ChannelMatrix::class)->for(NotificationEvent::BookingAccepted)['whatsapp'], 'sync must keep admin changes');
    }

    public function test_notification_uses_the_channels_enabled_in_admin(): void
    {
        Mail::fake();
        $user = $this->userWithDevice(['email' => 'driver@example.com']);

        // Default for "booking rejected" is push only.
        app(Notifier::class)->send($user, NotificationEvent::BookingRejected, 'عنوان', 'نص');
        $this->assertCount(1, app(PushGateway::class)->sent);
        $this->whatsapp()->assertNothingSent();
        Mail::assertNothingSent();

        // Admin turns push off and WhatsApp + email on: applied immediately.
        $this->setChannels(NotificationEvent::BookingRejected, false, true, true);
        app(Notifier::class)->send($user, NotificationEvent::BookingRejected, 'عنوان', 'نص', ['booking_id' => 5]);

        $this->assertCount(1, app(PushGateway::class)->sent);
        $this->whatsapp()->assertSentTo($user->phone, 'booking_rejected');
        $this->assertSame(['title' => 'عنوان', 'body' => 'نص', 'booking_id' => 5], $this->whatsapp()->last()['params']);
        Mail::assertSent(NotificationMail::class, fn ($mail) => $mail->hasTo('driver@example.com'));
    }

    public function test_every_delivery_is_logged_per_channel(): void
    {
        $user = $this->userWithDevice();

        app(Notifier::class)->send($user, NotificationEvent::BookingAccepted, 'تم قبول حجزك', 'تفاصيل');

        $this->assertDatabaseHas('notification_logs', [
            'user_id' => $user->id, 'event' => 'booking_accepted', 'channel' => 'push', 'status' => 'sent', 'title' => 'تم قبول حجزك',
        ]);
        $this->assertDatabaseHas('notification_logs', [
            'user_id' => $user->id, 'event' => 'booking_accepted', 'channel' => 'whatsapp', 'status' => 'sent', 'phone' => $user->phone,
        ]);
        $this->assertSame(2, NotificationLog::count());
    }

    public function test_push_without_a_device_is_logged_as_skipped_and_email_without_address_is_not_queued(): void
    {
        $user = User::factory()->create(['email' => null]);

        app(Notifier::class)->send($user, NotificationEvent::SettlementDueSoon, 'موعد السداد', 'نص');

        $log = NotificationLog::sole();
        $this->assertSame(['push', 'skipped'], [$log->channel, $log->status]);
        $this->assertNotNull($log->error);
    }

    public function test_provider_failure_is_logged_and_never_breaks_the_caller(): void
    {
        $user = $this->userWithDevice();
        $this->whatsapp()->shouldFail = true;

        app(Notifier::class)->send($user, NotificationEvent::BookingAccepted, 'عنوان', 'نص');

        $this->assertDatabaseHas('notification_logs', ['user_id' => $user->id, 'channel' => 'push', 'status' => 'sent']);
        $failed = NotificationLog::where('channel', 'whatsapp')->sole();
        $this->assertSame('failed', $failed->status);
        $this->assertStringContainsString('Fake WhatsApp failure', $failed->error);
    }

    public function test_dead_device_tokens_are_pruned_and_logged(): void
    {
        $user = $this->userWithDevice();
        app(PushGateway::class)->invalidTokens = ['tok-'.$user->id];

        app(Notifier::class)->send($user, NotificationEvent::BookingRejected, 'عنوان', 'نص');

        $this->assertSame(0, $user->deviceTokens()->count());
        $this->assertSame('skipped', NotificationLog::sole()->status);
    }

    public function test_notifications_are_queued_one_job_per_channel_with_retries(): void
    {
        Queue::fake();
        $user = $this->userWithDevice();

        app(Notifier::class)->send($user, NotificationEvent::BookingAccepted, 'عنوان', 'نص', ['booking_id' => 9]);

        Queue::assertPushed(DeliverNotification::class, 2);
        Queue::assertPushed(DeliverNotification::class, fn (DeliverNotification $job) => $job->channel === 'whatsapp'
            && $job->userId === $user->id && $job->event === 'booking_accepted' && $job->data === ['booking_id' => 9]
            && $job->tries === 3);
    }

    public function test_job_for_a_deleted_user_does_nothing(): void
    {
        $user = $this->userWithDevice();
        $user->delete();

        (new DeliverNotification($user->id, 'booking_accepted', 'push', 'عنوان', 'نص'))
            ->handle(app(PushGateway::class), $this->whatsapp(), app(NotificationLogger::class));

        $this->assertSame([], app(PushGateway::class)->sent);
        $this->assertSame(0, NotificationLog::count());
    }

    public function test_otp_is_logged_without_the_code(): void
    {
        $this->postJson('/api/v1/auth/otp', ['phone' => '0912345678'])->assertOk();

        $log = NotificationLog::sole();
        $this->assertSame(['otp_code', 'whatsapp', 'sent', '+249912345678'], [$log->event, $log->channel, $log->status, $log->phone]);
        $this->assertNull($log->title);

        $this->whatsapp()->shouldFail = true;
        $this->travel(2)->minutes();
        $this->postJson('/api/v1/auth/otp', ['phone' => '0912345678'])->assertStatus(503);
        $this->assertDatabaseHas('notification_logs', ['event' => 'otp_code', 'status' => 'failed']);
    }

    public function test_parcel_receiver_message_is_logged_with_template_values(): void
    {
        $parcel = Parcel::factory()->create();
        $driver = $parcel->trip->driverProfile->user;

        app(ParcelService::class)->accept($parcel, $driver);

        $message = $this->whatsapp()->sentTo($parcel->receiver_phone, 'parcel_delivery_code')[0];
        $this->assertSame($parcel->fresh()->delivery_code, $message['params']['code']);
        $this->assertSame($parcel->sender->name, $message['params']['sender']);
        $this->assertStringContainsString($driver->name, $message['params']['details']);
        $this->assertStringContainsString($message['params']['details'], $message['text']);

        $this->assertDatabaseHas('notification_logs', [
            'event' => 'parcel_delivery_code', 'channel' => 'whatsapp', 'status' => 'sent', 'phone' => $parcel->receiver_phone, 'user_id' => null,
        ]);
    }

    public function test_old_logs_are_pruned_by_the_retention_setting(): void
    {
        settings()->set('notifications.log_retention_days', 30);
        NotificationLog::create(['event' => 'x', 'channel' => 'push', 'status' => 'sent'])->forceFill(['created_at' => now()->subDays(31)])->save();
        NotificationLog::create(['event' => 'y', 'channel' => 'push', 'status' => 'sent'])->forceFill(['created_at' => now()->subDays(29)])->save();

        $this->artisan('notifications:prune')->assertSuccessful();

        $this->assertSame(['y'], NotificationLog::pluck('event')->all());
    }

    public function test_test_command_reports_each_channel(): void
    {
        $user = $this->userWithDevice(['phone' => '+249912345678', 'email' => null]);

        $this->artisan('notifications:test', ['phone' => '0912345678'])
            ->expectsOutputToContain('sent ✔')
            ->expectsOutputToContain('skipped')
            ->assertSuccessful();

        $this->whatsapp()->assertSentTo($user->phone);
        $this->artisan('notifications:test', ['phone' => '0999999999'])->assertFailed();
    }
}
