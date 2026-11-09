<?php

namespace App\Events;

use App\Models\Booking;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Domain event (M3). The M4 fee engine listens to it:
 * BookingAccepted → record fee; BookingCancelled / NoShowConfirmed → reverse fee.
 * Dispatched after the transaction commits.
 */
class NoShowConfirmed
{
    use Dispatchable;

    public function __construct(public readonly Booking $booking) {}
}
