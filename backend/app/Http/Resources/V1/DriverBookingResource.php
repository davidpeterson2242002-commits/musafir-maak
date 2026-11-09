<?php

namespace App\Http\Resources\V1;

use App\Enums\BookingStatus;
use App\Enums\IdVerificationStatus;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A booking as the driver sees it (D9 passenger list, D10 request detail).
 * Passenger phone only after acceptance.
 *
 * @mixin Booking
 */
class DriverBookingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $passenger = $this->passenger;

        return [
            'id' => $this->id,
            'trip_id' => $this->trip_id,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'passenger' => new PublicUserResource($passenger, trips: $this->passenger_trips_count ?? null),
            'passenger_id_status' => $passenger->id_verification_status === IdVerificationStatus::Verified ? 'verified' : 'under_review',
            'seats' => $this->seats,
            'extra_bags' => $this->extra_bags,
            'includes_family' => $this->includes_family,
            'pickup_point' => $this->pickupPoint ? new TripPointResource($this->pickupPoint) : null,
            'message' => $this->message,
            'price_total' => $this->price_total,
            'created_at' => local_iso($this->created_at),
            'expires_at' => $this->status === BookingStatus::Pending ? local_iso($this->expires_at) : null,
            'phone' => $this->revealsContacts() ? $passenger->phone : null,
            'whatsapp' => $this->revealsContacts() ? $passenger->whatsappNumber() : null,
            'can_mark_no_show' => $this->status === BookingStatus::Accepted && $this->trip->departs_at->isPast(),
            'trip' => $this->whenLoaded('trip', fn () => [
                'id' => $this->trip->id,
                'departs_at' => local_iso($this->trip->departs_at),
                'route' => $this->trip->route->label(),
                'seats_available' => $this->trip->seats_available,
            ]),
        ];
    }
}
