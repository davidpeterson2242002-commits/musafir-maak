<?php

namespace App\Events;

use App\Models\Trip;
use Illuminate\Foundation\Events\Dispatchable;

/** Domain event: the driver (or an admin) cancelled a trip. M4 reverses its fees. */
class TripCancelled
{
    use Dispatchable;

    /** @param array<int, int> $bookingIds bookings cancelled with the trip */
    public function __construct(public readonly Trip $trip, public readonly array $bookingIds) {}
}
