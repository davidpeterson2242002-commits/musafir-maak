<?php

namespace Tests\Feature\Api;

use App\Models\Admin;
use App\Models\Booking;
use App\Models\EnforcementAction;
use App\Models\Report;
use App\Services\EnforcementService;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsTrips;
use Tests\TestCase;

/** Reports, enforcement and blocking (spec 5.10, P13). */
class ReportsAndBlocksTest extends TestCase
{
    use BuildsTrips;

    public function test_report_from_a_booking(): void
    {
        $booking = Booking::factory()->accepted()->create();
        Sanctum::actingAs($booking->passenger);

        $this->postJson('/api/v1/reports', [
            'reported_user_id' => $booking->trip->driverProfile->user_id, 'reason' => 'harassment',
            'details' => 'كلام غير لائق', 'subject_type' => 'booking', 'subject_id' => $booking->id,
        ])->assertCreated()->assertJsonPath('data.status', 'open');

        $this->assertSame(1, Report::sole()->priority, 'harassment is safety-critical');
    }

    public function test_report_rules(): void
    {
        $booking = Booking::factory()->create();
        $stranger = $this->verifiedPassenger();
        Sanctum::actingAs($stranger);

        $this->postJson('/api/v1/reports', ['reported_user_id' => $stranger->id, 'reason' => 'fraud'])->assertJsonPath('code', 'self_report');
        $this->postJson('/api/v1/reports', ['reported_user_id' => $booking->passenger_id, 'reason' => 'fraud', 'subject_type' => 'booking', 'subject_id' => $booking->id])
            ->assertJsonPath('code', 'unrelated_subject');
        $this->postJson('/api/v1/reports', ['reported_user_id' => $booking->passenger_id, 'reason' => 'not-a-reason'])->assertJsonValidationErrors('reason');

        settings()->set('reports.max_per_day', 1);
        $this->postJson('/api/v1/reports', ['reported_user_id' => $booking->passenger_id, 'reason' => 'other'])->assertCreated();
        $this->postJson('/api/v1/reports', ['reported_user_id' => $booking->passenger_id, 'reason' => 'other'])->assertJsonPath('code', 'too_many_reports');
    }

    public function test_blocking_hides_trips_and_prevents_booking_both_ways(): void
    {
        $trip = $this->trip();
        $passenger = $this->verifiedPassenger();
        $driverUser = $trip->driverProfile->user;
        $search = fn () => $this->getJson('/api/v1/trips/search?'.http_build_query([
            'origin_city_id' => $trip->route->origin_city_id, 'destination_city_id' => $trip->route->destination_city_id,
            'date' => local_time($trip->departs_at)->toDateString(),
        ]));

        Sanctum::actingAs($driverUser);
        $this->postJson("/api/v1/users/{$passenger->id}/block")->assertOk();
        $this->getJson('/api/v1/me/blocks')->assertJsonPath('data.0.id', $passenger->id);

        Sanctum::actingAs($passenger);
        $search()->assertJsonCount(0, 'data');
        $this->postJson("/api/v1/trips/{$trip->id}/bookings", ['seats' => 1, 'pickup_point_id' => $this->pickupId($trip)])->assertJsonPath('code', 'blocked');
        $this->getJson("/api/v1/trips/{$trip->id}")->assertJsonPath('bookable', false);

        Sanctum::actingAs($driverUser);
        $this->deleteJson("/api/v1/users/{$passenger->id}/block")->assertOk();
        Sanctum::actingAs($passenger);
        $search()->assertJsonCount(1, 'data');
    }

    public function test_suspended_user_cannot_book_or_drive(): void
    {
        $trip = $this->trip();
        $passenger = $this->verifiedPassenger();
        app(EnforcementService::class)->suspend($passenger, now()->addDays(3), 'إساءة', Admin::factory()->create());
        $this->whatsapp()->assertSentTo($passenger->phone, 'account_warning');

        Sanctum::actingAs($passenger->fresh());
        $this->postJson("/api/v1/trips/{$trip->id}/bookings", ['seats' => 1, 'pickup_point_id' => $this->pickupId($trip)])->assertJsonPath('code', 'account_suspended');

        $booking = Booking::factory()->create(['trip_id' => $trip->id]);
        app(EnforcementService::class)->suspend($trip->driverProfile->user, now()->addDay(), 'تأخير متكرر', Admin::factory()->create());
        Sanctum::actingAs($trip->driverProfile->user->fresh());
        $this->postJson("/api/v1/driver/bookings/{$booking->id}/accept")->assertJsonPath('code', 'driver_not_active');
    }

    public function test_ban_cancels_drivers_trips_and_notifies_passengers(): void
    {
        $trip = $this->trip();
        $booking = Booking::factory()->accepted()->create(['trip_id' => $trip->id]);
        $driverUser = $trip->driverProfile->user;
        $driverUser->createToken('t');
        $admin = Admin::factory()->create();

        app(EnforcementService::class)->ban($driverUser, 'احتيال', $admin);

        $this->assertTrue($driverUser->fresh()->isBanned());
        $this->assertSame(0, $driverUser->tokens()->count());
        $this->assertSame('cancelled', $trip->fresh()->status->value);
        $this->assertSame('cancelled_by_driver', $booking->fresh()->status->value);
        $this->whatsapp()->assertSentTo($booking->passenger->phone, 'trip_cancelled');
        $this->assertSame(['ban'], EnforcementAction::pluck('action')->all());
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.banned', 'admin_id' => $admin->id]);
    }

    public function test_ban_cancels_passengers_bookings_without_penalty(): void
    {
        $booking = Booking::factory()->accepted()->create();
        $trip = $booking->trip;

        app(EnforcementService::class)->ban($booking->passenger, 'سلوك مسيء', Admin::factory()->create());

        $this->assertSame('cancelled_by_passenger', $booking->fresh()->status->value);
        $this->assertFalse($booking->fresh()->late_cancellation);
        $this->assertSame(4, $trip->fresh()->seats_available);
    }
}
