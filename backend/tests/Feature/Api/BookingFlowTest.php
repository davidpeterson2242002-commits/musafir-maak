<?php

namespace Tests\Feature\Api;

use App\Contracts\PushGateway;
use App\Enums\BookingStatus;
use App\Enums\IdDocumentType;
use App\Events\BookingAccepted;
use App\Events\BookingCancelled;
use App\Events\NoShowConfirmed;
use App\Models\Admin;
use App\Models\Booking;
use App\Models\RouteAlert;
use App\Models\Trip;
use App\Services\BookingService;
use App\Services\VerificationService;
use Database\Factories\TripFactory;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsApplications;
use Tests\Concerns\BuildsTrips;
use Tests\TestCase;

class BookingFlowTest extends TestCase
{
    use BuildsApplications, BuildsTrips;

    private function book(Trip $trip, array $data = []): TestResponse
    {
        return $this->postJson("/api/v1/trips/{$trip->id}/bookings", $data + ['seats' => 1, 'pickup_point_id' => $this->pickupId($trip)]);
    }

    private function asDriver(Trip $trip): void
    {
        Sanctum::actingAs($trip->driverProfile->user);
    }

    // ---------------------------------------------------------------- Requesting

    public function test_passenger_requests_seats(): void
    {
        $trip = $this->trip(['extra_luggage_price' => 2000]);
        Sanctum::actingAs($p = $this->verifiedPassenger());

        $this->book($trip, ['seats' => 2, 'extra_bags' => 1, 'message' => 'معاي شنطة كبيرة'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.price_total', 32000)
            ->assertJsonPath('data.contact', null)
            ->assertJsonPath('data.can_cancel', true);

        $this->assertSame(4, $trip->fresh()->seats_available, 'pending requests do not hold seats');
        $this->whatsapp()->assertSentTo($trip->driverProfile->user->phone, 'booking_requested');
    }

    public function test_deadline_is_12_hours_or_departure_whichever_first(): void
    {
        Sanctum::actingAs($this->verifiedPassenger());

        $far = $this->trip(['departs_at' => now()->addDays(2)]);
        $this->book($far);
        $this->assertEqualsWithDelta(now()->addHours(12)->timestamp, Booking::latest('id')->first()->expires_at->timestamp, 5);

        $soon = $this->trip(['departs_at' => now()->addHours(3)]);
        $this->book($soon);
        $this->assertEquals($soon->departs_at->timestamp, Booking::latest('id')->first()->expires_at->timestamp);

        settings()->set('booking.response_timeout_hours', 6);
        $this->book($this->trip(['departs_at' => now()->addDays(3)]));
        $this->assertEqualsWithDelta(now()->addHours(6)->timestamp, Booking::latest('id')->first()->expires_at->timestamp, 5);
    }

    public function test_id_verification_rules_for_first_booking(): void
    {
        $trip = $this->trip();

        Sanctum::actingAs($this->verifiedPassenger(['id_verification_status' => 'none']));
        $this->book($trip)->assertUnprocessable()->assertJsonPath('code', 'id_verification_required')->assertJsonPath('id_verification_status', 'none');

        // Pending ID: allowed by default, and the ID review jumps the queue.
        $this->fakeStorage();
        $pending = $this->verifiedPassenger(['id_verification_status' => 'none']);
        $this->giveIdentityDocs($pending);
        $review = app(VerificationService::class)->submitIdentity($pending, IdDocumentType::NationalId);
        Sanctum::actingAs($pending->fresh());
        $this->book($trip)->assertCreated();
        $this->assertSame(10, $review->fresh()->priority);

        $this->getJson('/api/v1/driver/requests'); // not a driver
        $this->asDriver($trip);
        $this->getJson('/api/v1/driver/requests')->assertJsonPath('data.0.passenger_id_status', 'under_review');

        settings()->set('verification.allow_first_booking_while_pending', false);
        Sanctum::actingAs($this->verifiedPassenger(['id_verification_status' => 'pending']));
        $this->book($trip)->assertJsonPath('code', 'id_verification_required');
    }

    public function test_booking_rules(): void
    {
        $trip = $this->trip();
        $p = $this->verifiedPassenger();
        Sanctum::actingAs($p);

        $this->book($trip, ['seats' => 5])->assertJsonPath('code', 'not_enough_seats')->assertJsonPath('seats_available', 4);
        $this->book($trip, ['pickup_point_id' => 999999])->assertJsonPath('code', 'invalid_pickup_point');
        $this->book($trip, ['extra_bags' => 1])->assertJsonPath('code', 'no_extra_luggage');
        $this->book($trip)->assertCreated();
        $this->book($trip)->assertJsonPath('code', 'already_booked');

        $this->asDriver($trip);
        $this->book($trip)->assertJsonPath('code', 'own_trip');

        Sanctum::actingAs($this->verifiedPassenger(['name' => null]));
        $this->book($trip)->assertJsonPath('code', 'profile_incomplete');

        Sanctum::actingAs($this->verifiedPassenger(['booking_restricted_until' => now()->addDays(3)]));
        $this->book($trip)->assertJsonPath('code', 'booking_restricted');

        Sanctum::actingAs($this->verifiedPassenger());
        $cancelled = $this->trip(['status' => 'cancelled']);
        $this->book($cancelled)->assertJsonPath('code', 'trip_unavailable');
    }

    public function test_single_pickup_point_is_chosen_automatically(): void
    {
        $trip = $this->trip();
        $trip->pickupPoints()->skip(1)->first()->delete();
        Sanctum::actingAs($this->verifiedPassenger());

        $this->postJson("/api/v1/trips/{$trip->id}/bookings", ['seats' => 1])->assertCreated()
            ->assertJsonPath('data.pickup_point.label', 'موقف شروني');
    }

    public function test_women_only_trips(): void
    {
        $trip = $this->trip(['women_only' => true]);

        Sanctum::actingAs($this->verifiedPassenger(['gender' => 'male']));
        $this->book($trip)->assertJsonPath('code', 'women_only');
        $this->book($trip, ['includes_family' => true])->assertCreated();

        Sanctum::actingAs($this->verifiedPassenger(['gender' => 'female']));
        $this->book($trip)->assertCreated();
    }

    // ---------------------------------------------------------------- Driver response

    public function test_accept_reserves_seats_and_reveals_contacts(): void
    {
        Event::fake([BookingAccepted::class]);
        $trip = $this->trip();
        $booking = Booking::factory()->create(['trip_id' => $trip->id, 'seats' => 3]);

        $this->asDriver($trip);
        $this->getJson('/api/v1/driver/requests')->assertJsonCount(1, 'data')->assertJsonPath('data.0.phone', null);
        $this->postJson("/api/v1/driver/bookings/{$booking->id}/accept")->assertOk()
            ->assertJsonPath('data.status', 'accepted')
            ->assertJsonPath('data.phone', $booking->passenger->phone);

        $this->assertSame(1, $trip->fresh()->seats_available);
        Event::assertDispatched(BookingAccepted::class, fn ($e) => $e->booking->id === $booking->id);
        $this->whatsapp()->assertSentTo($booking->passenger->phone, 'booking_accepted');
        $this->assertStringContainsString($trip->vehicle->plate_number, $this->whatsapp()->last()['text']);

        Sanctum::actingAs($booking->passenger);
        $this->getJson("/api/v1/bookings/{$booking->id}")->assertOk()
            ->assertJsonPath('data.contact.driver_phone', $trip->driverProfile->user->phone)
            ->assertJsonPath('data.contact.plate_number', $trip->vehicle->plate_number)
            ->assertJsonPath('data.share_text', fn ($t) => str_contains($t, $trip->vehicle->plate_number));
    }

    public function test_cannot_accept_more_than_available_or_twice(): void
    {
        $trip = $this->trip();
        $a = Booking::factory()->create(['trip_id' => $trip->id, 'seats' => 3]);
        $b = Booking::factory()->create(['trip_id' => $trip->id, 'seats' => 2]);
        $this->asDriver($trip);

        $this->postJson("/api/v1/driver/bookings/{$a->id}/accept")->assertOk();
        $this->postJson("/api/v1/driver/bookings/{$a->id}/accept")->assertJsonPath('code', 'not_pending');
        $this->postJson("/api/v1/driver/bookings/{$b->id}/accept")->assertJsonPath('code', 'not_enough_seats');
    }

    public function test_frozen_driver_cannot_accept_but_can_reject(): void
    {
        $trip = $this->trip();
        $a = Booking::factory()->create(['trip_id' => $trip->id]);
        $b = Booking::factory()->create(['trip_id' => $trip->id]);
        $trip->driverProfile->forceFill(['status' => 'frozen'])->save();
        $this->asDriver($trip);

        $this->postJson("/api/v1/driver/bookings/{$a->id}/accept")->assertJsonPath('code', 'driver_frozen');
        $this->postJson("/api/v1/driver/bookings/{$b->id}/reject", ['reason' => 'ممتلئة'])->assertOk()->assertJsonPath('data.status', 'rejected');
    }

    public function test_rejected_booking_shows_alternatives(): void
    {
        $trip = $this->trip();
        $alt = $this->trip(['departs_at' => $trip->departs_at->copy()->addHours(3)]);
        $this->trip(['departs_at' => $trip->departs_at->copy()->addDays(2)]); // other day
        $booking = Booking::factory()->create(['trip_id' => $trip->id]);

        $this->asDriver($trip);
        $this->postJson("/api/v1/driver/bookings/{$booking->id}/reject")->assertOk();

        Sanctum::actingAs($booking->passenger);
        $this->getJson("/api/v1/bookings/{$booking->id}")
            ->assertJsonPath('data.status', 'rejected')
            ->assertJsonCount(1, 'alternatives')
            ->assertJsonPath('alternatives.0.id', $alt->id);
    }

    public function test_other_drivers_cannot_respond(): void
    {
        $booking = Booking::factory()->create();
        $other = $this->trip();
        $this->asDriver($other);

        $this->postJson("/api/v1/driver/bookings/{$booking->id}/accept")->assertNotFound();
    }

    // ---------------------------------------------------------------- Timers

    public function test_unanswered_request_expires_after_12_hours(): void
    {
        $trip = $this->trip(['departs_at' => now()->addDays(2)]);
        Sanctum::actingAs($p = $this->verifiedPassenger());
        $p->deviceTokens()->create(['token' => 'pas', 'platform' => 'android']);
        $id = $this->book($trip)->json('data.id');

        $this->travel(11)->hours();
        $this->artisan('musafir:tick');
        $this->assertSame('pending', Booking::find($id)->status->value);

        $this->travel(61)->minutes();
        $this->artisan('musafir:tick');
        $this->assertSame('expired', Booking::find($id)->status->value);

        $push = collect(app(PushGateway::class)->sent);
        $this->assertTrue($push->contains(fn ($m) => $m['data']['event'] === 'booking_expired'), 'passenger told the request expired');

        $this->asDriver($trip);
        $this->postJson("/api/v1/driver/bookings/$id/accept")->assertJsonPath('code', 'not_pending');
        $this->artisan('musafir:tick'); // idempotent
        $this->assertSame(1, collect(app(PushGateway::class)->sent)->where('data.event', 'booking_expired')->count());
    }

    public function test_driver_is_reminded_every_hour_until_the_deadline(): void
    {
        $trip = $this->trip(['departs_at' => now()->addDays(2)]);
        $trip->driverProfile->user->deviceTokens()->create(['token' => 'drv', 'platform' => 'android']);
        $booking = Booking::factory()->create(['trip_id' => $trip->id, 'expires_at' => now()->addHours(12)]);
        $reminders = fn () => collect(app(PushGateway::class)->sent)->where('data.event', 'booking_reminder')->count();

        $this->artisan('musafir:tick');
        $this->assertSame(0, $reminders(), 'no reminder in the first hour');

        $this->travel(61)->minutes();
        $this->artisan('musafir:tick');
        $this->artisan('musafir:tick');
        $this->assertSame(1, $reminders(), 'one reminder per interval, not per tick');

        $this->travel(30)->minutes();
        $this->artisan('musafir:tick');
        $this->assertSame(1, $reminders());

        $this->travel(31)->minutes();
        $this->artisan('musafir:tick');
        $this->assertSame(2, $reminders());

        $booking->forceFill(['status' => BookingStatus::Accepted])->save();
        $this->travel(2)->hours();
        $this->artisan('musafir:tick');
        $this->assertSame(2, $reminders(), 'no reminders once answered');
    }

    public function test_trip_reminders_to_driver_and_passengers(): void
    {
        $trip = $this->trip(['departs_at' => now()->addHours(5)]);
        $booking = Booking::factory()->accepted()->create(['trip_id' => $trip->id]);
        $booking->passenger->deviceTokens()->create(['token' => 'pas', 'platform' => 'android']);
        $trip->driverProfile->user->deviceTokens()->create(['token' => 'drv', 'platform' => 'android']);
        $sent = fn () => collect(app(PushGateway::class)->sent)->where('data.event', 'trip_reminder');

        $this->travelTo($trip->departs_at->copy()->subMinutes(110));
        $this->artisan('musafir:tick');
        $this->artisan('musafir:tick');

        $this->assertSame(2, $sent()->count(), 'driver + passenger, once');
        $this->assertNotNull($booking->fresh()->reminded_soon_at);
    }

    // ---------------------------------------------------------------- Cancellation

    public function test_passenger_cancels_pending_without_penalty(): void
    {
        $booking = Booking::factory()->create(['trip_id' => $this->trip(['departs_at' => now()->addHours(1)])->id]);
        Sanctum::actingAs($booking->passenger);

        $this->postJson("/api/v1/bookings/{$booking->id}/cancel")->assertOk()
            ->assertJsonPath('data.status', 'cancelled_by_passenger')
            ->assertJsonPath('data.late_cancellation', false)
            ->assertJsonPath('warning', null);
    }

    public function test_cancelling_accepted_booking_frees_seats(): void
    {
        Event::fake([BookingCancelled::class]);
        $trip = $this->trip(['departs_at' => now()->addDays(2)]);
        $booking = Booking::factory()->accepted()->create(['trip_id' => $trip->id, 'seats' => 2]);
        $this->assertSame(2, $trip->fresh()->seats_available);
        Sanctum::actingAs($booking->passenger);

        $this->postJson("/api/v1/bookings/{$booking->id}/cancel", ['reason' => 'تغيرت الخطة'])->assertOk()
            ->assertJsonPath('data.late_cancellation', false);

        $this->assertSame(4, $trip->fresh()->seats_available);
        Event::assertDispatched(BookingCancelled::class);
        $this->postJson("/api/v1/bookings/{$booking->id}/cancel")->assertJsonPath('code', 'not_cancellable');
    }

    public function test_repeated_late_cancellations_warn_then_restrict(): void
    {
        $passenger = $this->verifiedPassenger();
        Sanctum::actingAs($passenger);
        $cancelLate = function () use ($passenger) {
            $booking = Booking::factory()->accepted()->create(['trip_id' => $this->trip(['departs_at' => now()->addHours(2)])->id, 'passenger_id' => $passenger->id]);

            return $this->postJson("/api/v1/bookings/{$booking->id}/cancel")->assertOk()->assertJsonPath('data.late_cancellation', true);
        };

        $cancelLate()->assertJsonPath('warning', null);
        $cancelLate()->assertJsonPath('warning', fn ($w) => str_contains($w, 'تنبيه'));
        $cancelLate()->assertJsonPath('restricted_until', fn ($d) => filled($d));

        $this->assertTrue($passenger->fresh()->isBookingRestricted());
        $this->whatsapp()->assertSentTo($passenger->phone, 'account_warning');
        Sanctum::actingAs($passenger->fresh());
        $this->book($this->trip())->assertJsonPath('code', 'booking_restricted');

        $this->travel(8)->days();
        $this->assertFalse($passenger->fresh()->isBookingRestricted());
    }

    public function test_cannot_cancel_after_departure(): void
    {
        $trip = $this->trip(['departs_at' => now()->addHour()]);
        $booking = Booking::factory()->accepted()->create(['trip_id' => $trip->id]);
        Sanctum::actingAs($booking->passenger);

        $this->travel(2)->hours();
        $this->postJson("/api/v1/bookings/{$booking->id}/cancel")->assertJsonPath('code', 'trip_departed');
    }

    // ---------------------------------------------------------------- No-show

    public function test_no_show_dispute_window_and_auto_confirmation(): void
    {
        Event::fake([NoShowConfirmed::class]);
        $trip = $this->trip(['departs_at' => now()->addHours(2)]);
        $disputed = Booking::factory()->accepted()->create(['trip_id' => $trip->id]);
        $silent = Booking::factory()->accepted()->create(['trip_id' => $trip->id]);
        $this->asDriver($trip);

        $this->postJson("/api/v1/driver/bookings/{$silent->id}/no-show")->assertJsonPath('code', 'too_early');

        $this->travel(3)->hours();
        $this->postJson("/api/v1/driver/bookings/{$silent->id}/no-show")->assertOk()->assertJsonPath('data.status', 'no_show');
        $this->postJson("/api/v1/driver/bookings/{$disputed->id}/no-show")->assertOk();

        Sanctum::actingAs($disputed->passenger);
        $this->getJson("/api/v1/bookings/{$disputed->id}")->assertJsonPath('data.no_show.can_dispute', true);
        $this->postJson("/api/v1/bookings/{$disputed->id}/dispute-no-show", ['details' => 'كنت في الموقف والسائق ما جا'])->assertOk()
            ->assertJsonPath('data.no_show.disputed_at', fn ($v) => filled($v));
        $this->postJson("/api/v1/bookings/{$disputed->id}/dispute-no-show", ['details' => 'مرة تانية'])->assertJsonPath('code', 'already_disputed');

        $this->travel(25)->hours();
        $this->artisan('musafir:tick');

        $this->assertNotNull($silent->fresh()->no_show_confirmed_at);
        $this->assertNull($disputed->fresh()->no_show_confirmed_at, 'disputed no-shows wait for admin');
        Event::assertDispatchedTimes(NoShowConfirmed::class, 1);

        Sanctum::actingAs($silent->passenger);
        $this->postJson("/api/v1/bookings/{$silent->id}/dispute-no-show", ['details' => 'متأخر جداً'])->assertJsonPath('code', 'not_disputable');
    }

    public function test_admin_resolves_no_show_dispute(): void
    {
        Event::fake([NoShowConfirmed::class]);
        $trip = $this->trip(['departs_at' => now()->subHour(), 'status' => 'departed']);
        $a = Booking::factory()->accepted()->create(['trip_id' => $trip->id]);
        $b = Booking::factory()->accepted()->create(['trip_id' => $trip->id]);
        foreach ([$a, $b] as $booking) {
            app(BookingService::class)->markNoShow($booking->fresh());
            app(BookingService::class)->disputeNoShow($booking->fresh(), 'كنت حاضر');
        }
        $admin = Admin::factory()->create();

        app(BookingService::class)->resolveNoShow($a->fresh(), true, $admin);
        app(BookingService::class)->resolveNoShow($b->fresh(), false, $admin);

        $this->assertSame('confirmed', $a->fresh()->no_show_resolution);
        $this->assertSame('completed', $b->fresh()->status->value);
        Event::assertDispatchedTimes(NoShowConfirmed::class, 1);
        $this->assertDatabaseHas('audit_logs', ['action' => 'no_show.overturned', 'subject_type' => 'booking', 'subject_id' => $b->id]);
    }

    // ---------------------------------------------------------------- Lists, alerts, account

    public function test_my_bookings_tabs(): void
    {
        $p = $this->verifiedPassenger();
        $upcoming = Booking::factory()->accepted()->create(['passenger_id' => $p->id]);
        $cancelled = Booking::factory()->create(['passenger_id' => $p->id, 'status' => BookingStatus::Expired]);
        $past = Booking::factory()->create(['passenger_id' => $p->id, 'status' => BookingStatus::Completed]);
        Booking::factory()->create(); // someone else's
        Sanctum::actingAs($p);

        $this->getJson('/api/v1/bookings')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $upcoming->id);
        $this->getJson('/api/v1/bookings?tab=past')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $past->id);
        $this->getJson('/api/v1/bookings?tab=cancelled')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $cancelled->id);
        $this->getJson('/api/v1/bookings/'.Booking::latest('id')->first()->id)->assertNotFound();
    }

    public function test_route_alerts_crud_and_limit(): void
    {
        Sanctum::actingAs($this->verifiedPassenger());
        settings()->set('route_alerts.max_per_user', 1);
        $route = TripFactory::launchRoute();

        $id = $this->postJson('/api/v1/route-alerts', ['origin_city_id' => $route->origin_city_id, 'destination_city_id' => $route->destination_city_id])
            ->assertCreated()->assertJsonPath('data.date', null)->json('data.id');
        $this->postJson('/api/v1/route-alerts', ['origin_city_id' => $route->destination_city_id, 'destination_city_id' => $route->origin_city_id])
            ->assertJsonPath('code', 'too_many_alerts');
        $this->postJson('/api/v1/route-alerts', ['origin_city_id' => 1, 'destination_city_id' => 2, 'date' => '2020-01-01'])->assertJsonValidationErrors('date');

        $this->getJson('/api/v1/route-alerts')->assertJsonCount(1, 'data');
        $this->deleteJson("/api/v1/route-alerts/$id")->assertOk();
    }

    public function test_dated_alerts_switch_off_after_the_day(): void
    {
        $route = TripFactory::launchRoute();
        $alert = RouteAlert::create(['user_id' => $this->verifiedPassenger()->id, 'origin_city_id' => $route->origin_city_id,
            'destination_city_id' => $route->destination_city_id, 'date' => now(config('app.display_timezone'))->toDateString()]);

        $this->artisan('musafir:tick');
        $this->assertTrue($alert->fresh()->active);
        $this->travel(1)->days();
        $this->artisan('musafir:tick');
        $this->assertFalse($alert->fresh()->active);
    }

    public function test_account_cannot_be_deleted_with_active_booking(): void
    {
        $booking = Booking::factory()->create();
        Sanctum::actingAs($booking->passenger);

        $this->deleteJson('/api/v1/me')->assertJsonPath('code', 'has_active_bookings');
    }
}
