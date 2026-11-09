<?php

namespace App\Http\Resources\V1;

use App\Enums\BookingStatus;
use App\Enums\PointKind;
use App\Enums\TripStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** The driver's own trip (D8 list, D9 management). @mixin \App\Models\Trip */
class DriverTripResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $bookings = $this->whenLoaded('bookings');
        $hasBookings = $this->relationLoaded('bookings')
            ? $this->bookings->contains(fn ($b) => $b->status->isActive())
            : null;

        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'departs_at' => local_iso($this->departs_at),
            'est_arrival_at' => local_iso($this->est_arrival_at),
            'route' => [
                'origin' => ['id' => $this->route->origin->id, 'name' => $this->route->origin->name_ar],
                'destination' => ['id' => $this->route->destination->id, 'name' => $this->route->destination->name_ar],
            ],
            'vehicle' => ['id' => $this->vehicle->id, 'label' => $this->vehicle->label()],
            'price_per_seat' => $this->price_per_seat,
            'seats_total' => $this->seats_total,
            'seats_booked' => $this->seats_total - $this->seats_available,
            'seats_available' => $this->seats_available,
            'pending_requests' => $this->pending_requests_count ?? ($this->relationLoaded('bookings') ? $this->bookings->where('status', BookingStatus::Pending)->count() : null),
            'luggage' => ['large_per_passenger' => $this->luggage_large, 'small_per_passenger' => $this->luggage_small, 'extra_bag_price' => $this->extra_luggage_price],
            'accepts_parcels' => $this->accepts_parcels,
            'parcel_prices' => ['small' => $this->parcel_price_small, 'medium' => $this->parcel_price_medium, 'large' => $this->parcel_price_large],
            'max_parcels' => $this->max_parcels,
            'women_only' => $this->women_only,
            'notes' => $this->notes,
            'cancel_reason' => $this->cancel_reason,
            'hidden_by_admin' => $this->hidden_by_admin,
            'pickup_points' => $this->whenLoaded('points', fn () => TripPointResource::collection($this->points->where('kind', PointKind::Pickup)->values())),
            'dropoff_points' => $this->whenLoaded('points', fn () => TripPointResource::collection($this->points->where('kind', PointKind::Dropoff)->values())),
            'actions' => [
                'can_edit_all' => $this->status === TripStatus::Scheduled && $hasBookings === false,
                'can_edit_limited' => $this->status === TripStatus::Scheduled,
                'can_depart' => $this->status === TripStatus::Scheduled && now()->gte($this->departs_at->copy()->subMinutes(settings()->int('trips.depart_early_minutes'))),
                'can_complete' => $this->status === TripStatus::Departed,
                'can_cancel' => $this->status === TripStatus::Scheduled,
            ],
            'bookings' => $this->when(
                $this->relationLoaded('bookings'),
                fn () => DriverBookingResource::collection($this->bookings->sortBy(fn ($b) => [$b->status === BookingStatus::Pending ? 0 : 1, $b->id])->values()),
            ),
        ];
    }
}
