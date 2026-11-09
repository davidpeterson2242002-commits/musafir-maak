<?php

namespace Database\Factories;

use App\Enums\PointKind;
use App\Enums\TripStatus;
use App\Models\City;
use App\Models\DriverProfile;
use App\Models\TravelRoute;
use App\Models\Trip;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/** @extends Factory<Trip> */
class TripFactory extends Factory
{
    public function definition(): array
    {
        return [
            'driver_profile_id' => fn () => DriverProfile::factory()->active(),
            'vehicle_id' => fn (array $attrs) => Vehicle::factory()->approved()->create(['driver_profile_id' => $attrs['driver_profile_id'], 'seats' => 4])->id,
            'route_id' => fn () => self::launchRoute()->id,
            // Tomorrow 08:00 Sudan time.
            'departs_at' => Carbon::tomorrow(config('app.display_timezone'))->setTime(8, 0)->utc(),
            'price_per_seat' => 15000,
            'seats_total' => 4,
            'seats_available' => 4,
            'luggage_large' => 1,
            'luggage_small' => 1,
            'status' => TripStatus::Scheduled,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Trip $trip) {
            if ($trip->points()->doesntExist()) {
                $trip->points()->createMany([
                    ['kind' => PointKind::Pickup, 'label' => 'موقف شروني', 'sort' => 0],
                    ['kind' => PointKind::Pickup, 'label' => 'السوق العربي', 'sort' => 1],
                    ['kind' => PointKind::Dropoff, 'label' => 'موقف مدني', 'sort' => 0],
                ]);
            }
        });
    }

    /** Khartoum → Wad Madani (seeded). */
    public static function launchRoute(): TravelRoute
    {
        return TravelRoute::between(
            City::where('name_en', 'Khartoum')->value('id'),
            City::where('name_en', 'Wad Madani')->value('id'),
        )->firstOrFail();
    }

    /** Departure at a given Sudan-local date/time, e.g. "2026-11-12 07:30". */
    public function departingLocal(string $local): static
    {
        return $this->state(fn () => ['departs_at' => Carbon::parse($local, config('app.display_timezone'))->utc()]);
    }
}
