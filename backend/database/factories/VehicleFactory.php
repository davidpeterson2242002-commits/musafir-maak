<?php

namespace Database\Factories;

use App\Enums\VehicleStatus;
use App\Enums\VehicleType;
use App\Models\DriverProfile;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Vehicle> */
class VehicleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'driver_profile_id' => DriverProfile::factory(),
            'type' => VehicleType::Car,
            'make' => fake()->randomElement(['تويوتا', 'هيونداي', 'نيسان', 'كيا']),
            'model' => fake()->randomElement(['كورولا', 'أكسنت', 'صني', 'هايلكس']),
            'color' => fake()->randomElement(['أبيض', 'فضي', 'أسود', 'أزرق']),
            'year' => fake()->numberBetween(2005, 2024),
            'plate_number' => 'خ '.fake()->unique()->numerify('#####'),
            'seats' => 4,
            'status' => VehicleStatus::Draft,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => ['status' => VehicleStatus::Approved, 'approved_at' => now()]);
    }
}
