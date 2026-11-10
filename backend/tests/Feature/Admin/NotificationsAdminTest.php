<?php

namespace Tests\Feature\Admin;

use App\Contracts\PushGateway;
use App\Enums\AdminRole;
use App\Enums\NotificationEvent;
use App\Filament\Resources\Broadcasts\Pages\ListBroadcasts;
use App\Filament\Resources\NotificationChannels\Pages\ManageNotificationChannels;
use App\Filament\Resources\NotificationLogs\NotificationLogResource;
use App\Filament\Resources\NotificationLogs\Pages\ListNotificationLogs;
use App\Jobs\DeliverNotification;
use App\Jobs\SendBroadcast;
use App\Mail\NotificationMail;
use App\Models\Admin;
use App\Models\Broadcast;
use App\Models\City;
use App\Models\DriverProfile;
use App\Models\NotificationChannel;
use App\Models\NotificationLog;
use App\Models\SearchLog;
use App\Models\User;
use App\Services\BroadcastService;
use App\Services\Notifier;
use App\Support\ChannelMatrix;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class NotificationsAdminTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('admin');
    }

    private function superAdmin(): Admin
    {
        $admin = Admin::factory()->role(AdminRole::SuperAdmin)->create();
        $this->actingAs($admin, 'admin');

        return $admin;
    }

    private function userWithDevice(array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->deviceTokens()->create(['token' => 'tok-'.$user->id, 'platform' => 'android']);

        return $user;
    }

    // ---------------- Access ----------------

    public function test_pages_render_and_are_restricted_by_role(): void
    {
        NotificationLog::create(['event' => 'booking_accepted', 'channel' => 'whatsapp', 'status' => 'failed', 'phone' => '+249912345678', 'error' => 'boom']);
        NotificationLog::create(['user_id' => User::factory()->create()->id, 'event' => 'otp_code', 'channel' => 'push', 'status' => 'sent']);

        $this->superAdmin();
        foreach (['/admin/notification-channels', '/admin/notification-logs', '/admin/broadcasts', '/admin/settings'] as $url) {
            $this->get($url)->assertOk();
        }
        $this->get('/admin/notification-channels')->assertSee('قبول الحجز (للراكب)');
        $this->get('/admin/notification-logs')->assertSee('boom')->assertSee('رمز التحقق');

    }

    public function test_support_sees_only_the_log(): void
    {
        $this->actingAs(Admin::factory()->role(AdminRole::Support)->create(), 'admin');

        $this->get('/admin/notification-logs')->assertOk();
        $this->get('/admin/notification-channels')->assertForbidden();
        $this->get('/admin/broadcasts')->assertForbidden();
    }

    public function test_finance_cannot_open_notification_pages(): void
    {
        $this->actingAs(Admin::factory()->role(AdminRole::Finance)->create(), 'admin');

        $this->get('/admin/notification-logs')->assertForbidden();
        $this->get('/admin/broadcasts')->assertForbidden();
    }

    // ---------------- Channel matrix ----------------

    public function test_toggling_a_channel_applies_immediately_and_is_audited(): void
    {
        $admin = $this->superAdmin();
        $row = NotificationChannel::where('event', 'booking_rejected')->sole();
        $this->assertFalse($row->whatsapp);

        Livewire::test(ManageNotificationChannels::class)
            ->call('updateTableColumnState', 'whatsapp', (string) $row->id, true);

        $this->assertTrue(app(ChannelMatrix::class)->for(NotificationEvent::BookingRejected)['whatsapp']);
        $this->assertDatabaseHas('audit_logs', ['admin_id' => $admin->id, 'action' => 'updated', 'subject_type' => 'notification_channel', 'subject_id' => $row->id]);

        $user = User::factory()->create();
        app(Notifier::class)->send($user, NotificationEvent::BookingRejected, 'عنوان', 'نص');
        $this->whatsapp()->assertSentTo($user->phone, 'booking_rejected');
    }

    public function test_reset_restores_default_channels(): void
    {
        $this->superAdmin();
        NotificationChannel::where('event', 'booking_accepted')->update(['whatsapp' => false, 'email' => true]);
        app(ChannelMatrix::class)->flush();

        Livewire::test(ManageNotificationChannels::class)->callAction('reset');

        $this->assertSame(['push' => true, 'whatsapp' => true, 'email' => false], app(ChannelMatrix::class)->for(NotificationEvent::BookingAccepted));
        $this->assertDatabaseHas('audit_logs', ['action' => 'notification_channels.reset']);
    }

    // ---------------- Log ----------------

    public function test_log_tabs_and_failure_badge(): void
    {
        $this->superAdmin();
        $failed = NotificationLog::create(['event' => 'booking_accepted', 'channel' => 'whatsapp', 'status' => 'failed', 'phone' => '+249912345678']);
        $sent = NotificationLog::create(['event' => 'booking_accepted', 'channel' => 'push', 'status' => 'sent']);
        $old = NotificationLog::create(['event' => 'trip_cancelled', 'channel' => 'push', 'status' => 'failed']);
        $old->forceFill(['created_at' => now()->subDays(2)])->save();

        $this->assertSame('1', NotificationLogResource::getNavigationBadge());

        Livewire::test(ListNotificationLogs::class)
            ->assertCanSeeTableRecords([$failed, $sent, $old])
            ->set('activeTab', 'failed')
            ->assertCanSeeTableRecords([$failed, $old])
            ->assertCanNotSeeTableRecords([$sent])
            ->set('activeTab', 'whatsapp')
            ->assertCanSeeTableRecords([$failed])
            ->assertCanNotSeeTableRecords([$sent, $old]);
    }

    // ---------------- Broadcast ----------------

    public function test_audience_segments(): void
    {
        [$khartoum, $madani] = City::orderBy('id')->take(2)->get()->all();
        $service = app(BroadcastService::class);

        $passenger = User::factory()->create(['city_id' => $khartoum->id]);
        $other = User::factory()->create(['city_id' => $madani->id]);
        $banned = User::factory()->banned()->create(['city_id' => $khartoum->id]);
        $driver = DriverProfile::factory()->active()->create();
        $driver->user->update(['city_id' => $khartoum->id]);
        $owing = DriverProfile::factory()->active()->create(['balance_owed' => 4000]);
        $owing->user->update(['city_id' => $madani->id]);
        $applicant = DriverProfile::factory()->create(); // not approved yet
        $applicant->user->update(['city_id' => $khartoum->id]);

        SearchLog::create(['origin_city_id' => $khartoum->id, 'destination_city_id' => $madani->id, 'results_count' => 0, 'user_id' => $passenger->id]);
        SearchLog::create(['origin_city_id' => $madani->id, 'destination_city_id' => $khartoum->id, 'results_count' => 2, 'user_id' => $other->id]);
        SearchLog::create(['origin_city_id' => $khartoum->id, 'destination_city_id' => $madani->id, 'results_count' => 0, 'user_id' => $banned->id]);
        $stale = SearchLog::create(['origin_city_id' => $khartoum->id, 'destination_city_id' => $madani->id, 'results_count' => 0, 'user_id' => $other->id]);
        $stale->forceFill(['created_at' => now()->subDays(45)])->save();

        $ids = fn (string $segment, array $filters = []) => $service->audience($segment, $filters)->orderBy('id')->pluck('id')->all();
        $sorted = fn (array $users) => collect($users)->map->id->sort()->values()->all();

        $this->assertSame($sorted([$passenger, $other, $driver->user, $owing->user, $applicant->user]), $ids('all_users'));
        $this->assertSame($sorted([$passenger, $driver->user, $applicant->user]), $ids('users_in_city', ['city_id' => $khartoum->id]));
        $this->assertSame($sorted([$driver->user, $owing->user]), $ids('all_drivers'));
        $this->assertSame([$driver->user->id], $ids('drivers_in_city', ['city_id' => $khartoum->id]));
        $this->assertSame([$owing->user->id], $ids('drivers_owing'));
        $this->assertSame([$passenger->id], $ids('searched_route', ['origin_city_id' => $khartoum->id, 'destination_city_id' => $madani->id]));
        $this->assertSame([], $ids('users_in_city')); // a city segment without a city reaches nobody
    }

    public function test_send_broadcast_from_admin_delivers_on_chosen_channels_and_logs(): void
    {
        Mail::fake();
        $admin = $this->superAdmin();
        $city = City::orderBy('id')->first();
        $withDevice = $this->userWithDevice(['city_id' => $city->id, 'email' => 'a@example.com']);
        $noDevice = User::factory()->create(['city_id' => $city->id, 'email' => null]);
        $elsewhere = $this->userWithDevice(['city_id' => City::orderBy('id')->skip(1)->first()->id]);

        Livewire::test(ListBroadcasts::class)
            ->callAction('send', [
                'segment' => 'users_in_city', 'city_id' => $city->id, 'channels' => ['push', 'email'],
                'title' => 'الطريق مفتوح', 'body' => 'طريق الخرطوم — مدني عاد للعمل.',
            ])
            ->assertHasNoActionErrors();

        $broadcast = Broadcast::sole();
        $this->assertSame(['sent', 2, $admin->id], [$broadcast->status, $broadcast->recipients_count, $broadcast->admin_id]);
        $this->assertSame(['city_id' => $city->id], $broadcast->filters);
        $this->assertNotNull($broadcast->sent_at);

        $push = app(PushGateway::class)->sent;
        $this->assertCount(1, $push);
        $this->assertSame(['tok-'.$withDevice->id], $push[0]['tokens']);
        $this->assertSame('broadcast', $push[0]['data']['event']);
        $this->assertSame('الطريق مفتوح', $push[0]['title']);
        Mail::assertSent(NotificationMail::class, 1);
        $this->whatsapp()->assertNothingSent(); // not chosen, whatever the matrix says

        $this->assertSame(3, $broadcast->logs()->count()); // push sent, email sent, push skipped (no device)
        $this->assertDatabaseHas('notification_logs', ['broadcast_id' => $broadcast->id, 'user_id' => $noDevice->id, 'channel' => 'push', 'status' => 'skipped']);
        $this->assertDatabaseMissing('notification_logs', ['user_id' => $elsewhere->id]);
        $this->assertDatabaseHas('audit_logs', ['admin_id' => $admin->id, 'action' => 'broadcast.sent', 'subject_type' => 'broadcast']);

        $this->get('/admin/broadcasts')->assertOk()->assertSee('الطريق مفتوح');
    }

    public function test_broadcast_form_validation(): void
    {
        $this->superAdmin();

        Livewire::test(ListBroadcasts::class)
            ->callAction('send', ['segment' => 'drivers_in_city', 'channels' => [], 'title' => '', 'body' => 'نص'])
            ->assertHasActionErrors(['city_id', 'channels', 'title']);

        $this->assertSame(0, Broadcast::count());
    }

    public function test_broadcast_is_queued_and_never_sent_twice(): void
    {
        Queue::fake();
        $service = app(BroadcastService::class);
        $this->userWithDevice();

        $broadcast = $service->create(null, 'all_users', [], ['whatsapp', 'nonsense'], 'عنوان', 'نص');

        Queue::assertPushed(SendBroadcast::class, fn (SendBroadcast $job) => $job->broadcastId === $broadcast->id && $job->tries === 1);
        $this->assertSame(['queued', ['whatsapp']], [$broadcast->status, $broadcast->channels]);

        $this->assertSame(1, $service->deliver($broadcast));
        (new SendBroadcast($broadcast->id))->handle($service); // already sent: ignored
        $this->assertSame('sent', $broadcast->fresh()->status);
        Queue::assertPushed(DeliverNotification::class, 1);
    }
}
