<?php

namespace Tests\Feature\Api;

use App\Models\Admin;
use App\Models\Booking;
use App\Models\DriverProfile;
use App\Models\LedgerEntry;
use App\Models\Trip;
use App\Services\BookingService;
use App\Services\LedgerService;
use App\Services\TripService;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsTrips;
use Tests\TestCase;

/** Commission charging and reversals (spec 5.3–5.5). */
class CommissionTest extends TestCase
{
    use BuildsTrips;

    private function paidDriverTrip(array $driverAttrs = [], array $tripAttrs = []): Trip
    {
        $driver = DriverProfile::factory()->active()->create($driverAttrs + ['free_bookings_remaining' => 0]);

        return $this->trip($tripAttrs + ['driver_profile_id' => $driver->id]);
    }

    private function accept(Booking $booking): Booking
    {
        return app(BookingService::class)->accept($booking->fresh());
    }

    private function driver(Trip $trip): DriverProfile
    {
        return $trip->driverProfile->fresh();
    }

    public function test_first_three_accepted_bookings_are_free(): void
    {
        $trip = $this->trip(['seats_total' => 4, 'seats_available' => 4]);
        $this->assertSame(3, $this->driver($trip)->free_bookings_remaining);

        foreach (range(1, 4) as $i) {
            $this->accept(Booking::factory()->create(['trip_id' => $trip->id]));
        }

        $bookings = $trip->bookings()->orderBy('id')->get();
        $this->assertSame([true, true, true, false], $bookings->pluck('fee_free')->all());
        $this->assertSame([0, 0, 0, 1500], $bookings->pluck('fee_amount')->all());
        $this->assertSame(0, $this->driver($trip)->free_bookings_remaining);
        $this->assertSame(1500, $this->driver($trip)->balance_owed);
    }

    public function test_fee_goes_to_balance_and_starts_the_weekly_cycle(): void
    {
        $trip = $this->paidDriverTrip();
        $this->accept(Booking::factory()->create(['trip_id' => $trip->id, 'seats' => 2]));

        $driver = $this->driver($trip);
        $this->assertSame(3000, $driver->balance_owed);
        $this->assertEqualsWithDelta(now()->addDays(7)->timestamp, $driver->settlement_due_at->timestamp, 5);

        $entry = LedgerEntry::sole();
        $this->assertSame(['fee', 'balance', 3000, 3000, 0], [$entry->type->value, $entry->bucket, $entry->amount, $entry->balance_after, $entry->wallet_after]);

        // A second fee in the same cycle doesn't move the due date.
        $this->travel(2)->days();
        $this->accept(Booking::factory()->create(['trip_id' => $trip->id]));
        $this->assertEqualsWithDelta($driver->settlement_due_at->timestamp, $this->driver($trip)->settlement_due_at->timestamp, 1);
    }

    public function test_fee_is_snapshotted_at_acceptance(): void
    {
        $trip = $this->paidDriverTrip();
        $booking = Booking::factory()->create(['trip_id' => $trip->id]);

        settings()->set('fees.percent.car', 20); // change before acceptance → applies
        $this->accept($booking);
        settings()->set('fees.percent.car', 5);  // change after → no effect

        $this->assertSame(3000, $booking->fresh()->fee_amount);
        $this->assertSame('percentage', $booking->fresh()->fee_model);
        $this->assertSame(3000, $this->driver($trip)->balance_owed);
    }

    public function test_wallet_is_used_first(): void
    {
        $trip = $this->paidDriverTrip(['wallet_balance' => 1000]);
        $this->accept(Booking::factory()->create(['trip_id' => $trip->id]));

        $driver = $this->driver($trip);
        $this->assertSame(0, $driver->wallet_balance);
        $this->assertSame(500, $driver->balance_owed);
        $this->assertSame([['wallet', -1000], ['balance', 500]], LedgerEntry::orderBy('id')->get()->map(fn ($e) => [$e->bucket, $e->amount])->all());
    }

    public function test_passenger_cancellation_reverses_fee(): void
    {
        $trip = $this->paidDriverTrip([], ['departs_at' => now()->addDays(2)]);
        $booking = $this->accept(Booking::factory()->create(['trip_id' => $trip->id]));
        $this->assertSame(1500, $this->driver($trip)->balance_owed);

        app(BookingService::class)->cancelByPassenger($booking->fresh());

        $driver = $this->driver($trip);
        $this->assertSame(0, $driver->balance_owed);
        $this->assertNull($driver->settlement_due_at, 'cycle closes when nothing is owed');
        $this->assertNotNull($booking->fresh()->fee_reversed_at);
        $this->assertSame(['fee', 'fee_reversal'], LedgerEntry::orderBy('id')->pluck('type')->map->value->all());
    }

    public function test_reversal_returns_wallet_part_to_wallet(): void
    {
        $trip = $this->paidDriverTrip(['wallet_balance' => 1000], ['departs_at' => now()->addDays(2)]);
        $booking = $this->accept(Booking::factory()->create(['trip_id' => $trip->id]));

        app(LedgerService::class)->reverseBooking($booking->fresh(), 'test');

        $driver = $this->driver($trip);
        $this->assertSame([1000, 0], [$driver->wallet_balance, $driver->balance_owed]);
    }

    public function test_reversing_an_already_paid_fee_becomes_wallet_credit(): void
    {
        $trip = $this->paidDriverTrip();
        $booking = $this->accept(Booking::factory()->create(['trip_id' => $trip->id]));
        app(LedgerService::class)->adjust($this->driver($trip), 'balance', -1500, 'دفع نقداً للمكتب', Admin::factory()->create());

        app(LedgerService::class)->reverseBooking($booking->fresh(), 'no-show');

        $driver = $this->driver($trip);
        $this->assertSame([0, 1500], [$driver->balance_owed, $driver->wallet_balance]);
    }

    public function test_reversal_is_idempotent_and_restores_free_bookings(): void
    {
        $trip = $this->trip();
        $booking = $this->accept(Booking::factory()->create(['trip_id' => $trip->id]));
        $this->assertSame(2, $this->driver($trip)->free_bookings_remaining);

        app(LedgerService::class)->reverseBooking($booking->fresh(), 'x');
        app(LedgerService::class)->reverseBooking($booking->fresh(), 'x');

        $this->assertSame(3, $this->driver($trip)->free_bookings_remaining);
        $this->assertSame(0, LedgerEntry::count());
    }

    public function test_trip_cancellation_reverses_all_fees(): void
    {
        $trip = $this->paidDriverTrip();
        $this->accept(Booking::factory()->create(['trip_id' => $trip->id]));
        $this->accept(Booking::factory()->create(['trip_id' => $trip->id, 'seats' => 2]));
        Booking::factory()->create(['trip_id' => $trip->id]); // pending, never charged
        $this->assertSame(4500, $this->driver($trip)->balance_owed);

        app(TripService::class)->cancel($trip->fresh(), 'عطل');

        $this->assertSame(0, $this->driver($trip)->balance_owed);
        $this->assertSame(2, LedgerEntry::where('type', 'fee_reversal')->count());
    }

    public function test_confirmed_no_show_reverses_fee(): void
    {
        $trip = $this->paidDriverTrip([], ['departs_at' => now()->addHour()]);
        $booking = $this->accept(Booking::factory()->create(['trip_id' => $trip->id]));

        $this->travel(2)->hours();
        app(BookingService::class)->markNoShow($booking->fresh());
        $this->assertSame(1500, $this->driver($trip)->balance_owed, 'not reversed until confirmed');

        $this->travel(25)->hours();
        $this->artisan('musafir:tick');
        $this->assertSame(0, $this->driver($trip)->balance_owed);
    }

    public function test_per_trip_fee_is_charged_once_and_moves_if_reversed(): void
    {
        settings()->set('fees.model', 'fixed_per_trip');
        settings()->set('fees.fixed.car', 4000);
        $trip = $this->paidDriverTrip([], ['departs_at' => now()->addDays(2)]);

        $first = $this->accept(Booking::factory()->create(['trip_id' => $trip->id]));
        $second = $this->accept(Booking::factory()->create(['trip_id' => $trip->id]));
        $this->assertSame([4000, 0], [$first->fresh()->fee_amount, $second->fresh()->fee_amount]);
        $this->assertSame(4000, $this->driver($trip)->balance_owed);

        app(BookingService::class)->cancelByPassenger($first->fresh());

        $this->assertSame(4000, $second->fresh()->fee_amount, 'remaining booking carries the trip fee');
        $this->assertSame(4000, $this->driver($trip)->balance_owed);
    }

    public function test_balance_limit_blocks_accepting(): void
    {
        settings()->set('settlement.balance_limit', 3000);
        $trip = $this->paidDriverTrip(['balance_owed' => 3000]);
        $booking = Booking::factory()->create(['trip_id' => $trip->id]);
        Sanctum::actingAs($trip->driverProfile->user);

        $this->getJson('/api/v1/driver/balance')->assertJsonPath('data.balance_limit_reached', true)->assertJsonPath('data.can_accept_bookings', false);
        $this->postJson("/api/v1/driver/bookings/{$booking->id}/accept")->assertJsonPath('code', 'balance_limit_reached');

        $this->assertSame('pending', $booking->fresh()->status->value);
        $this->assertSame(4, $trip->fresh()->seats_available, 'acceptance rolled back');
    }

    public function test_mandatory_wallet_requires_enough_credit(): void
    {
        settings()->set('wallet.mode', 'mandatory');
        $trip = $this->paidDriverTrip(['wallet_balance' => 1000]);
        $booking = Booking::factory()->create(['trip_id' => $trip->id]);
        Sanctum::actingAs($trip->driverProfile->user);

        $this->postJson("/api/v1/driver/bookings/{$booking->id}/accept")->assertJsonPath('code', 'wallet_insufficient')->assertJsonPath('fee', 1500);

        $trip->driverProfile->forceFill(['wallet_balance' => 1500])->save();
        $this->postJson("/api/v1/driver/bookings/{$booking->id}/accept")->assertOk()->assertJsonPath('data.fee.amount', 1500);
        $this->assertSame([0, 0], [$this->driver($trip)->wallet_balance, $this->driver($trip)->balance_owed]);
    }

    public function test_driver_sees_fee_preview_on_requests(): void
    {
        $trip = $this->paidDriverTrip();
        Booking::factory()->create(['trip_id' => $trip->id, 'seats' => 2]);
        Booking::factory()->create(['trip_id' => $trip->id]);
        Sanctum::actingAs($trip->driverProfile->user);

        $this->getJson('/api/v1/driver/requests')->assertJsonPath('data.0.fee', ['amount' => 3000, 'free' => false, 'free_bookings_remaining' => 0]);

        $trip->driverProfile->forceFill(['free_bookings_remaining' => 2])->save();
        $this->getJson('/api/v1/driver/requests')->assertJsonPath('data.0.fee.amount', 0)->assertJsonPath('data.0.fee.free_bookings_remaining', 2);
    }

    public function test_driver_with_balance_cannot_delete_account(): void
    {
        $trip = $this->paidDriverTrip(['balance_owed' => 500]);
        Sanctum::actingAs($trip->driverProfile->user);
        $trip->update(['status' => 'completed']);

        $this->deleteJson('/api/v1/me')->assertJsonPath('code', 'has_balance_owed');
    }
}
