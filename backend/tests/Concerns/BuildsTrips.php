<?php

namespace Tests\Concerns;

use App\Models\Trip;
use App\Models\User;

trait BuildsTrips
{
    protected function verifiedPassenger(array $attrs = []): User
    {
        return User::factory()->create($attrs + ['id_verification_status' => 'verified', 'gender' => 'male']);
    }

    protected function trip(array $attrs = []): Trip
    {
        return Trip::factory()->create($attrs)->fresh(['driverProfile.user', 'points', 'route.origin', 'route.destination', 'vehicle']);
    }

    protected function pickupId(Trip $trip): int
    {
        return $trip->pickupPoints()->value('id');
    }
}
