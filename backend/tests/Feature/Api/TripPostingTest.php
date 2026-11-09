<?php

namespace Tests\Feature\Api;

use App\Contracts\PushGateway;
use App\Enums\BookingStatus;
use App\Events\TripCancelled;
use App\Models\Booking;
use App\Models\City;
use App\Models\DriverProfile;
use App\Models\RouteAlert;
use App\Models\TravelRoute;
use App\Models\Trip;
use App\Models\Vehicle;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsTrips;
use Tests\TestCase;

class TripPostingTest extends TestCase
{
    use BuildsTrips;

    private DriverProfile $driver;

    private Vehicle $vehicle;

    protected function setUp(): void
    {
        parent::setUp();
        $this->driver = DriverProfile::factory()->active()->create();
        $this->vehicle = Vehicle::factory()->approved()->for($this->driver)->create(['seats' => 4]);
        Sanctum::actingAs($this->driver->user);
    }

    private function city(string $en): int
    {
        return City::where('name_en', $en)->value('id');
    }

    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'origin_city_id' => $this->city('Khartoum'),
            'destination_city_id' => $this->city('Wad Madani'),
            'vehicle_id' => $this->vehicle->id,
            'departs_at' => now(config('app.display_timezone'))->addDay()->setTime(7, 30)->format('Y-m-d H:i'),
            'seats_total' => 3,
            'price_per_seat' => 15000,
            'pickup_points' => [['label' => 'موقف شروني'], ['label' => 'الكلاكلة اللفة', 'lat' => 15.52, 'lng' => 32.53]],
            'dropoff_points' => [['label' => 'موقف مدني']],
            'luggage_large' => 1, 'luggage_small' => 1, 'extra_luggage_price' => 2000,
            'women_only' => false, 'notes' => 'تكييف',
        ];
    }

    public function test_driver_posts_a_trip(): void
    {
        $res = $this->postJson('/api/v1/driver/trips', $this->payload())->assertCreated()
            ->assertJsonPath('data.status', 'scheduled')
            ->assertJsonPath('data.seats_available', 3)
            ->assertJsonPath('data.route.destination.name', 'ود مدني')
            ->assertJsonCount(2, 'data.pickup_points')
            ->assertJsonPath('data.pickup_points.1.lat', 15.52);

        // Sent as Sudan local time, stored UTC, returned with +02:00.
        $this->assertStringEndsWith('07:30:00+02:00', $res->json('data.departs_at'));
        $this->assertSame('05:30', Trip::first()->departs_at->utc()->format('H:i'));
    }

    public function test_new_city_pair_creates_a_route_when_allowed(): void
    {
        $payload = $this->payload(['origin_city_id' => $this->city('Kosti'), 'destination_city_id' => $this->city('El Obeid')]);
        $this->postJson('/api/v1/driver/trips', $payload)->assertCreated();
        $this->assertTrue(TravelRoute::between($this->city('Kosti'), $this->city('El Obeid'))->exists());

        settings()->set('routes.auto_create', false);
        $payload = $this->payload(['origin_city_id' => $this->city('Dongola'), 'destination_city_id' => $this->city('Kassala')]);
        $this->postJson('/api/v1/driver/trips', $payload)->assertUnprocessable()->assertJsonPath('code', 'route_unavailable');
    }

    public function test_closed_route_blocks_posting_with_its_message(): void
    {
        TravelRoute::between($this->city('Khartoum'), $this->city('Wad Madani'))->update(['enabled' => false, 'closure_message' => 'الطريق مقفول']);

        $this->postJson('/api/v1/driver/trips', $this->payload())
            ->assertUnprocessable()->assertJsonPath('code', 'route_closed')->assertJsonPath('message', 'الطريق مقفول');
    }

    public function test_posting_rules(): void
    {
        $this->postJson('/api/v1/driver/trips', $this->payload(['seats_total' => 5]))->assertJsonPath('code', 'too_many_seats');

        TravelRoute::between($this->city('Khartoum'), $this->city('Wad Madani'))->update(['max_price_per_seat' => 10000]);
        $this->postJson('/api/v1/driver/trips', $this->payload())->assertJsonPath('code', 'price_above_max')->assertJsonPath('max_price_per_seat', 10000);
        TravelRoute::query()->update(['max_price_per_seat' => null]);

        $this->postJson('/api/v1/driver/trips', $this->payload(['departs_at' => now()->addMinutes(10)->toIso8601String()]))->assertJsonPath('code', 'departure_too_soon');
        $this->postJson('/api/v1/driver/trips', $this->payload(['departs_at' => now()->addDays(90)->toIso8601String()]))->assertJsonPath('code', 'departure_too_far');
        $this->postJson('/api/v1/driver/trips', $this->payload(['destination_city_id' => $this->city('Khartoum')]))->assertJsonPath('code', 'same_city');
        $this->postJson('/api/v1/driver/trips', $this->payload(['accepts_parcels' => true]))->assertJsonValidationErrors(['parcel_price_small', 'max_parcels']);
        $this->postJson('/api/v1/driver/trips', $this->payload(['pickup_points' => []]))->assertJsonValidationErrors('pickup_points');

        $other = Vehicle::factory()->approved()->create();
        $this->postJson('/api/v1/driver/trips', $this->payload(['vehicle_id' => $other->id]))->assertJsonPath('code', 'vehicle_not_approved');

        $this->assertSame(0, Trip::count());
    }

    public function test_inactive_driver_cannot_post(): void
    {
        $this->driver->forceFill(['status' => 'frozen'])->save();
        $this->postJson('/api/v1/driver/trips', $this->payload())->assertJsonPath('code', 'driver_not_active');
    }

    public function test_route_alert_subscribers_are_notified(): void
    {
        $any = RouteAlert::create(['user_id' => $this->verifiedPassenger()->id, 'origin_city_id' => $this->city('Khartoum'), 'destination_city_id' => $this->city('Wad Madani')]);
        $dated = RouteAlert::create(['user_id' => $this->verifiedPassenger()->id, 'origin_city_id' => $this->city('Khartoum'), 'destination_city_id' => $this->city('Wad Madani'),
            'date' => now(config('app.display_timezone'))->addDay()->toDateString()]);
        $otherDay = RouteAlert::create(['user_id' => $this->verifiedPassenger()->id, 'origin_city_id' => $this->city('Khartoum'), 'destination_city_id' => $this->city('Wad Madani'),
            'date' => now(config('app.display_timezone'))->addDays(5)->toDateString()]);
        $device = $any->user->deviceTokens()->create(['token' => 'tok', 'platform' => 'android']);

        $this->postJson('/api/v1/driver/trips', $this->payload())->assertCreated();

        $this->assertSame(1, $any->fresh()->notified_count);
        $this->assertSame(1, $dated->fresh()->notified_count);
        $this->assertSame(0, $otherDay->fresh()->notified_count);
        $push = app(PushGateway::class);
        $this->assertSame('route_alert_match', collect($push->sent)->firstWhere('tokens', ['tok'])['data']['event']);
    }

    public function test_full_edit_before_bookings_limited_after(): void
    {
        $id = $this->postJson('/api/v1/driver/trips', $this->payload())->json('data.id');

        $this->putJson("/api/v1/driver/trips/$id", ['price_per_seat' => 14000, 'pickup_points' => [['label' => 'موقف جديد']]])
            ->assertOk()->assertJsonPath('data.price_per_seat', 14000)->assertJsonCount(1, 'data.pickup_points');

        Booking::factory()->accepted()->create(['trip_id' => $id, 'seats' => 2]);

        $this->putJson("/api/v1/driver/trips/$id", ['price_per_seat' => 20000])->assertJsonPath('code', 'trip_has_bookings');
        $this->putJson("/api/v1/driver/trips/$id", ['seats_total' => 2])->assertJsonPath('code', 'trip_has_bookings');
        $this->putJson("/api/v1/driver/trips/$id", ['seats_total' => 4, 'notes' => 'جديد'])
            ->assertOk()->assertJsonPath('data.seats_total', 4)->assertJsonPath('data.seats_available', 2)->assertJsonPath('data.seats_booked', 2);
    }

    public function test_cancel_trip_cancels_bookings_and_notifies(): void
    {
        Event::fake([TripCancelled::class]);
        $trip = $this->trip(['driver_profile_id' => $this->driver->id, 'vehicle_id' => $this->vehicle->id]);
        $accepted = Booking::factory()->accepted()->create(['trip_id' => $trip->id]);
        $pending = Booking::factory()->create(['trip_id' => $trip->id]);
        $old = Booking::factory()->create(['trip_id' => $trip->id, 'status' => BookingStatus::Rejected]);

        $this->postJson("/api/v1/driver/trips/{$trip->id}/cancel", [])->assertJsonValidationErrors('reason');
        $this->postJson("/api/v1/driver/trips/{$trip->id}/cancel", ['reason' => 'العربية تعطلت'])
            ->assertOk()->assertJsonPath('data.status', 'cancelled');

        $this->assertSame('cancelled_by_driver', $accepted->fresh()->status->value);
        $this->assertSame('cancelled_by_driver', $pending->fresh()->status->value);
        $this->assertSame('rejected', $old->fresh()->status->value);
        $this->whatsapp()->assertSentTo($accepted->passenger->phone, 'trip_cancelled');
        Event::assertDispatched(TripCancelled::class, fn ($e) => $e->bookingIds === [$accepted->id, $pending->id]);
    }

    public function test_depart_and_complete(): void
    {
        $trip = $this->trip(['driver_profile_id' => $this->driver->id, 'vehicle_id' => $this->vehicle->id]);
        $accepted = Booking::factory()->accepted()->create(['trip_id' => $trip->id]);
        $pending = Booking::factory()->create(['trip_id' => $trip->id]);

        $this->postJson("/api/v1/driver/trips/{$trip->id}/complete")->assertJsonPath('code', 'not_departed');
        $this->postJson("/api/v1/driver/trips/{$trip->id}/depart")->assertJsonPath('code', 'too_early');

        $this->travelTo($trip->departs_at->copy()->subMinutes(30));
        $this->postJson("/api/v1/driver/trips/{$trip->id}/depart")->assertOk()->assertJsonPath('data.status', 'departed');
        $this->assertSame('expired', $pending->fresh()->status->value);

        $this->postJson("/api/v1/driver/trips/{$trip->id}/complete")->assertOk()->assertJsonPath('data.status', 'completed');
        $this->assertSame('completed', $accepted->fresh()->status->value);
    }

    public function test_departed_trip_auto_completes(): void
    {
        $trip = $this->trip(['driver_profile_id' => $this->driver->id, 'vehicle_id' => $this->vehicle->id]);
        $trip->forceFill(['status' => 'departed', 'departed_at' => now()])->save();

        $this->travel(47)->hours();
        $this->artisan('musafir:tick');
        $this->assertSame('departed', $trip->fresh()->status->value);

        $this->travel(2)->hours();
        $this->artisan('musafir:tick');
        $this->assertSame('completed', $trip->fresh()->status->value);
    }

    public function test_driver_sees_only_own_trips(): void
    {
        $mine = $this->trip(['driver_profile_id' => $this->driver->id, 'vehicle_id' => $this->vehicle->id]);
        $other = $this->trip();

        $this->getJson('/api/v1/driver/trips')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $mine->id);
        $this->getJson("/api/v1/driver/trips/{$other->id}")->assertNotFound();
        $this->postJson("/api/v1/driver/trips/{$other->id}/cancel", ['reason' => 'x x x'])->assertNotFound();
    }
}
