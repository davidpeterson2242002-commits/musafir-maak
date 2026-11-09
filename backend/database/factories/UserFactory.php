<?php

namespace Database\Factories;

use App\Enums\Gender;
use App\Enums\UserStatus;
use App\Models\City;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<User> */
class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'phone' => '+2499'.fake()->unique()->numerify('########'),
            'gender' => fake()->randomElement(Gender::cases()),
            'city_id' => City::query()->inRandomOrder()->value('id'),
            'status' => UserStatus::Active,
            'phone_verified_at' => now(),
            'terms_accepted_at' => now(),
        ];
    }

    /** Just signed up via OTP, profile not set up yet. */
    public function fresh(): static
    {
        return $this->state(fn () => ['name' => null, 'gender' => null, 'city_id' => null, 'terms_accepted_at' => null]);
    }

    public function banned(): static
    {
        return $this->state(fn () => ['status' => UserStatus::Banned]);
    }
}
