<?php

namespace Database\Factories;

use App\Enums\DriverStatus;
use App\Models\DriverProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DriverProfile> */
class DriverProfileFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'status' => DriverStatus::Draft,
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => ['status' => DriverStatus::Active, 'approved_at' => now(), 'free_bookings_remaining' => 3]);
    }
}
