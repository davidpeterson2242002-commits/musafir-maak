<?php

namespace App\Http\Resources\V1;

use App\Enums\BookingStatus;
use App\Enums\TripStatus;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A booking from the passenger's side (P7/P8). Driver phone and plate appear only once accepted.
 *
 * @mixin Booking
 */
class BookingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $trip = $this->trip;
        $driverUser = $trip->driverProfile->user;
        $reveal = $this->revealsContacts();
        $disputeHours = settings()->int('booking.no_show_dispute_hours');

        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'seats' => $this->seats,
            'extra_bags' => $this->extra_bags,
            'includes_family' => $this->includes_family,
            'price_total' => $this->price_total,
            'pickup_point' => $this->pickupPoint ? new TripPointResource($this->pickupPoint) : null,
            'message' => $this->message,
            'created_at' => local_iso($this->created_at),
            'expires_at' => $this->status === BookingStatus::Pending ? local_iso($this->expires_at) : null,
            'accepted_at' => local_iso($this->accepted_at),
            'cancelled_at' => local_iso($this->cancelled_at),
            'cancel_reason' => $this->cancel_reason,
            'rejection_reason' => $this->rejection_reason,
            'late_cancellation' => $this->late_cancellation,
            'no_show' => $this->status === BookingStatus::NoShow ? [
                'marked_at' => local_iso($this->no_show_at),
                'disputed_at' => local_iso($this->no_show_disputed_at),
                'resolution' => $this->no_show_resolution,
                'can_dispute' => $this->no_show_disputed_at === null && $this->no_show_confirmed_at === null
                    && $this->no_show_at->gt(now()->subHours($disputeHours)),
            ] : null,
            'can_cancel' => $this->status->isActive() && $trip->status === TripStatus::Scheduled && $trip->departs_at->isFuture(),
            'trip' => (new TripResource($trip))->withDetails(),
            'contact' => $reveal ? [
                'driver_phone' => $driverUser->phone,
                'driver_whatsapp' => $driverUser->whatsappNumber(),
                'plate_number' => $trip->vehicle->plate_number,
            ] : null,
            // Ready-made text for "share trip with family" (P7).
            'share_text' => $reveal ? sprintf(
                "رحلتي مع مسافر معاك\n%s — %s\nالسائق: %s (%s)\nالعربية: %s %s %s — لوحة %s",
                $trip->route->label(), local_time($trip->departs_at)->format('Y-m-d H:i'), $driverUser->name, $driverUser->phone,
                $trip->vehicle->make, $trip->vehicle->model, $trip->vehicle->color, $trip->vehicle->plate_number,
            ) : null,
        ];
    }
}
