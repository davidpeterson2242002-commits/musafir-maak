<?php

namespace Database\Factories;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Booking> */
class BookingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'trip_id' => Trip::factory(),
            'passenger_id' => fn () => User::factory()->create(['id_verification_status' => 'verified'])->id,
            'seats' => 1,
            'pickup_point_id' => fn (array $a) => Trip::find($a['trip_id'])->pickupPoints()->value('id'),
            'status' => BookingStatus::Pending,
            'price_total' => 15000,
            'expires_at' => now()->addHours(12),
        ];
    }

    public function accepted(): static
    {
        return $this->state(fn () => ['status' => BookingStatus::Accepted])
            ->afterCreating(function (Booking $b) {
                $b->forceFill(['accepted_at' => now()])->save();
                $b->trip->decrement('seats_available', $b->seats);
            });
    }
}
