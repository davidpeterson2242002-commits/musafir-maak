<?php

namespace Tests\Feature\Api;

use App\Contracts\PushGateway;
use App\Models\Booking;
use App\Models\Message;
use App\Models\UserBlock;
use App\Services\BookingService;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsTrips;
use Tests\TestCase;

/** In-app chat (spec 5.8, P10). */
class ChatTest extends TestCase
{
    use BuildsTrips;

    private function requested(): Booking
    {
        $trip = $this->trip();
        Sanctum::actingAs($p = $this->verifiedPassenger());
        $id = $this->postJson("/api/v1/trips/{$trip->id}/bookings", ['seats' => 1, 'pickup_point_id' => $this->pickupId($trip)])->json('data.id');

        return Booking::with(['trip.driverProfile.user', 'passenger', 'conversation'])->find($id);
    }

    public function test_conversation_opens_with_the_booking_request(): void
    {
        $booking = $this->requested();

        $this->getJson("/api/v1/bookings/{$booking->id}")->assertJsonPath('data.conversation_id', $booking->conversation->id);
        $this->getJson('/api/v1/conversations')->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.my_role', 'passenger')
            ->assertJsonPath('data.0.other.id', $booking->trip->driverProfile->user_id)
            ->assertJsonPath('data.0.open', true);
    }

    public function test_numbers_are_masked_and_flagged_before_acceptance(): void
    {
        $booking = $this->requested();
        $c = $booking->conversation;

        $this->postJson("/api/v1/conversations/{$c->id}/messages", ['body' => 'كلمني على 0912345678 أحسن'])->assertCreated()
            ->assertJsonPath('data.was_masked', true)
            ->assertJsonPath('data.body', fn ($b) => ! str_contains($b, '0912345678'));

        $message = Message::sole();
        $this->assertTrue($message->flagged);
        $this->assertSame('number_before_acceptance', $message->flag_reason);
        $this->assertSame('كلمني على 0912345678 أحسن', $message->original_body);
        $this->assertTrue($c->fresh()->has_flagged);

        Sanctum::actingAs($booking->trip->driverProfile->user);
        $this->getJson("/api/v1/conversations/{$c->id}/messages")->assertJsonPath('data.0.body', fn ($b) => str_contains($b, 'رقم مخفي'))
            ->assertJsonMissingPath('data.0.original_body');
    }

    public function test_numbers_are_visible_after_acceptance(): void
    {
        $booking = $this->requested();
        app(BookingService::class)->accept($booking);

        $this->postJson("/api/v1/conversations/{$booking->conversation->id}/messages", ['body' => 'رقمي 0912345678'])
            ->assertJsonPath('data.was_masked', false)->assertJsonPath('data.body', 'رقمي 0912345678');
        $this->assertFalse(Message::sole()->flagged);
    }

    public function test_keywords_flag_without_blocking(): void
    {
        $booking = $this->requested();
        $this->postJson("/api/v1/conversations/{$booking->conversation->id}/messages", ['body' => 'خلينا نتفق كاش برا التطبيق'])
            ->assertCreated()->assertJsonPath('data.body', 'خلينا نتفق كاش برا التطبيق');

        $this->assertSame('keyword', Message::sole()->flag_reason);
    }

    public function test_unread_counts_push_and_read_marking(): void
    {
        $booking = $this->requested();
        $driver = $booking->trip->driverProfile->user;
        $driver->deviceTokens()->create(['token' => 'drv', 'platform' => 'android']);
        $c = $booking->conversation;

        $this->postJson("/api/v1/conversations/{$c->id}/messages", ['body' => 'السلام عليكم'])->assertCreated();
        $this->postJson("/api/v1/conversations/{$c->id}/messages", ['body' => 'متى التحرك؟'])->assertCreated();

        $this->assertSame('new_chat_message', collect(app(PushGateway::class)->sent)->last()['data']['event']);

        Sanctum::actingAs($driver);
        $this->getJson('/api/v1/conversations/unread-count')->assertJsonPath('unread', 2);
        $this->getJson('/api/v1/conversations')->assertJsonPath('data.0.unread', 2)->assertJsonPath('data.0.last_message.body', 'متى التحرك؟');
        $this->getJson("/api/v1/conversations/{$c->id}/messages")->assertJsonCount(2, 'data')->assertJsonPath('data.0.mine', false);
        $this->getJson('/api/v1/conversations/unread-count')->assertJsonPath('unread', 0);
    }

    public function test_only_participants_can_read_or_write(): void
    {
        $booking = $this->requested();
        Sanctum::actingAs($this->verifiedPassenger());

        $this->getJson("/api/v1/conversations/{$booking->conversation->id}/messages")->assertNotFound();
        $this->postJson("/api/v1/conversations/{$booking->conversation->id}/messages", ['body' => 'hi'])->assertNotFound();
    }

    public function test_blocked_users_cannot_message(): void
    {
        $booking = $this->requested();
        UserBlock::create(['blocker_id' => $booking->trip->driverProfile->user_id, 'blocked_id' => $booking->passenger_id]);

        $this->postJson("/api/v1/conversations/{$booking->conversation->id}/messages", ['body' => 'hello'])->assertJsonPath('code', 'blocked');
    }

    public function test_chat_closes_after_rejection(): void
    {
        $booking = $this->requested();
        app(BookingService::class)->reject($booking);

        $this->postJson("/api/v1/conversations/{$booking->conversation->id}/messages", ['body' => 'لماذا؟'])->assertJsonPath('code', 'conversation_closed');
        $this->getJson("/api/v1/conversations/{$booking->conversation->id}/messages")->assertOk()->assertJsonPath('conversation.open', false);
    }
}
