<?php

namespace Tests\Feature\Api;

use App\Contracts\PushGateway;
use App\Enums\NotificationEvent;
use App\Jobs\DeliverNotification;
use App\Models\User;
use App\Services\Notifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ImmediatePushTest extends TestCase
{
    use RefreshDatabase;

    public function test_push_is_sent_right_after_the_response_not_through_the_queue(): void
    {
        config(['notifications.send_after_response' => true]);
        Queue::fake();
        $user = User::factory()->create();
        $user->deviceTokens()->create(['token' => 'tok', 'platform' => 'android']);

        app(Notifier::class)->send($user, NotificationEvent::BookingAccepted, 'السائق قبل حجزك', 'تفاصيل', ['booking_id' => 1]);

        $this->assertCount(0, app(PushGateway::class)->sent, 'not before the response is sent');
        app()->terminate();

        $this->assertCount(1, app(PushGateway::class)->sent);
        Queue::assertNotPushed(DeliverNotification::class, fn ($job) => $job->channel === 'push');
    }

    public function test_a_failed_immediate_push_goes_to_the_queue(): void
    {
        config(['notifications.send_after_response' => true]);
        Queue::fake();
        $user = User::factory()->create();
        $user->deviceTokens()->create(['token' => 'tok', 'platform' => 'android']);
        app(PushGateway::class)->fail = true;

        app(Notifier::class)->send($user, NotificationEvent::BookingAccepted, 'title', 'body');
        app()->terminate();

        Queue::assertPushed(DeliverNotification::class, fn ($job) => $job->channel === 'push');
    }
}
