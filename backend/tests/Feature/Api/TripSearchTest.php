<?php

namespace Tests\Feature\Api;

use App\Enums\VehicleType;
use App\Models\City;
use App\Models\DriverProfile;
use App\Models\SearchLog;
use App\Models\TravelRoute;
use App\Models\Trip;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsTrips;
use Tests\TestCase;

class TripSearchTest extends TestCase
{
    use BuildsTrips;

    private string $day;

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs($this->verifiedPassenger());
        $this->day = now(config('app.display_timezone'))->addDays(2)->toDateString();
    }

    private function search(array $params = []): TestResponse
    {
        return $this->getJson('/api/v1/trips/search?'.http_build_query($params + [
            'origin_city_id' => City::where('name_en', 'Khartoum')->value('id'),
            'destination_city_id' => City::where('name_en', 'Wad Madani')->value('id'),
            'date' => $this->day,
        ]));
    }

    private function tripAt(string $time, array $attrs = []): Trip
    {
        return Trip::factory()->departingLocal("{$this->day} $time")->create($attrs);
    }

    public function test_finds_trips_on_the_local_date_including_after_midnight(): void
    {
        $early = $this->tripAt('01:30'); // previous UTC day
        $late = $this->tripAt('23:30');
        Trip::factory()->departingLocal(Carbon::parse($this->day)->addDay()->toDateString().' 00:30')->create();

        $this->search()->assertOk()->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $early->id)->assertJsonPath('data.1.id', $late->id)
            ->assertJsonPath('data.0.driver.verified', false)
            ->assertJsonMissingPath('data.0.vehicle.plate_number');
    }

    public function test_hides_trips_that_cannot_be_booked(): void
    {
        $ok = $this->tripAt('08:00');
        $this->tripAt('09:00', ['seats_available' => 0]);
        $this->tripAt('10:00', ['status' => 'cancelled']);
        $hidden = $this->tripAt('11:00');
        $hidden->forceFill(['hidden_by_admin' => true])->save();
        $frozen = $this->tripAt('12:00');
        $frozen->driverProfile->forceFill(['status' => 'frozen'])->save();

        $this->search()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $ok->id);
    }

    public function test_own_trips_are_excluded(): void
    {
        $driver = DriverProfile::factory()->active()->create();
        $this->tripAt('08:00', ['driver_profile_id' => $driver->id]);
        Sanctum::actingAs($driver->user);

        $this->search()->assertJsonCount(0, 'data');
    }

    public function test_filters_and_sorting(): void
    {
        $morning = $this->tripAt('07:00', ['price_per_seat' => 20000]);
        $evening = $this->tripAt('19:00', ['price_per_seat' => 12000, 'women_only' => true]);
        $bus = $this->tripAt('13:00', ['price_per_seat' => 9000, 'seats_total' => 2, 'seats_available' => 2]);
        $bus->vehicle->update(['type' => VehicleType::Bus]);
        $morning->driverProfile->forceFill(['rating_avg' => 4.8])->save();

        $ids = fn ($res) => collect($res->json('data'))->pluck('id')->all();

        $this->assertSame([$morning->id], $ids($this->search(['time_of_day' => ['morning']])));
        $this->assertSame([$morning->id, $evening->id], $ids($this->search(['time_of_day' => ['morning', 'evening']])));
        $this->assertSame([$bus->id, $evening->id], $ids($this->search(['max_price' => 15000])));
        $this->assertSame([$bus->id], $ids($this->search(['vehicle_types' => ['bus']])));
        $this->assertSame([$evening->id], $ids($this->search(['women_only' => 1])));
        $this->assertSame([$morning->id], $ids($this->search(['min_rating' => 4.5])));
        $this->assertSame([$morning->id, $evening->id], $ids($this->search(['seats' => 3])));
        $this->assertSame([$bus->id, $evening->id, $morning->id], $ids($this->search(['sort' => 'cheapest'])));
        $this->assertSame($morning->id, $ids($this->search(['sort' => 'rating']))[0]);
    }

    public function test_empty_day_suggests_nearby_dates(): void
    {
        Trip::factory()->departingLocal(Carbon::parse($this->day)->addDay()->toDateString().' 08:00')->count(2)->create();
        Trip::factory()->departingLocal(Carbon::parse($this->day)->addDays(10)->toDateString().' 08:00')->create();

        $this->search()->assertJsonCount(0, 'data')
            ->assertJsonPath('nearby_dates', [['date' => Carbon::parse($this->day)->addDay()->toDateString(), 'trips' => 2]]);
    }

    public function test_closed_route_returns_closure_message(): void
    {
        $this->tripAt('08:00');
        TravelRoute::query()->update(['enabled' => false, 'closure_message' => 'الطريق موقوف للصيانة']);

        $this->search()->assertJsonCount(0, 'data')
            ->assertJsonPath('closed', true)->assertJsonPath('closure_message', 'الطريق موقوف للصيانة');
    }

    public function test_every_search_is_logged(): void
    {
        $this->tripAt('08:00');
        $this->search();
        $this->search(['destination_city_id' => City::where('name_en', 'Nyala')->value('id')]);

        $this->assertSame([1, 0], SearchLog::orderBy('id')->pluck('results_count')->all());
        $this->assertNull(SearchLog::latest('id')->first()->route_id);
    }

    public function test_parcel_search_only_returns_trips_taking_parcels(): void
    {
        $this->tripAt('08:00');
        $parcels = $this->tripAt('09:00', ['accepts_parcels' => true, 'max_parcels' => 3, 'parcel_price_small' => 3000, 'parcel_price_medium' => 5000, 'parcel_price_large' => 8000]);

        $this->search(['kind' => 'parcel'])->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $parcels->id)->assertJsonPath('data.0.parcel_prices.medium', 5000);
    }

    public function test_full_trip_still_shows_in_parcel_search(): void
    {
        $full = $this->tripAt('09:00', ['accepts_parcels' => true, 'max_parcels' => 2, 'seats_available' => 0,
            'parcel_price_small' => 3000, 'parcel_price_medium' => 5000, 'parcel_price_large' => 8000]);

        $this->search([])->assertJsonCount(0, 'data');
        $this->search(['kind' => 'parcel'])->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $full->id);
    }

    public function test_trip_details_show_points_and_safe_vehicle_photos(): void
    {
        $trip = $this->tripAt('08:00');

        $this->getJson("/api/v1/trips/{$trip->id}")->assertOk()
            ->assertJsonPath('bookable', true)
            ->assertJsonCount(2, 'data.pickup_points')
            ->assertJsonPath('data.luggage.large_per_passenger', 1)
            ->assertJsonMissingPath('data.vehicle.plate_number');
    }

    public function test_validation(): void
    {
        $this->getJson('/api/v1/trips/search')->assertJsonValidationErrors(['origin_city_id', 'destination_city_id', 'date']);
        $this->search(['date' => '12/11/2026'])->assertJsonValidationErrors('date');
        $this->search(['sort' => 'random'])->assertJsonValidationErrors('sort');
    }
}
