<?php

namespace App\Http\Resources\V1;

use App\Enums\DocumentKind;
use App\Enums\PointKind;
use App\Models\Trip;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A trip as passengers see it. Search lists use the card fields; `details` (trip page)
 * adds points, luggage, notes and vehicle photos. Plate number is never shown here.
 *
 * @mixin Trip
 */
class TripResource extends JsonResource
{
    public bool $details = false;

    public function withDetails(): static
    {
        $this->details = true;

        return $this;
    }

    public function toArray(Request $request): array
    {
        $driver = $this->driverProfile;

        $data = [
            'id' => $this->id,
            'status' => $this->status->value,
            'departs_at' => local_iso($this->departs_at),
            'est_arrival_at' => local_iso($this->est_arrival_at),
            'route' => [
                'id' => $this->route_id,
                'origin' => ['id' => $this->route->origin->id, 'name' => $this->route->origin->name_ar],
                'destination' => ['id' => $this->route->destination->id, 'name' => $this->route->destination->name_ar],
            ],
            'price_per_seat' => $this->price_per_seat,
            'seats_available' => $this->seats_available,
            'seats_total' => $this->seats_total,
            'women_only' => $this->women_only,
            'accepts_parcels' => $this->accepts_parcels,
            'parcel_prices' => $this->accepts_parcels ? [
                'small' => $this->parcel_price_small, 'medium' => $this->parcel_price_medium, 'large' => $this->parcel_price_large,
            ] : null,
            'driver' => new PublicUserResource($driver->user, (float) $driver->rating_avg, $driver->rating_count, $driver->completed_trips_count ?? null),
            'vehicle' => [
                'type' => $this->vehicle->type->value,
                'type_label' => $this->vehicle->type->label(),
                'make' => $this->vehicle->make,
                'model' => $this->vehicle->model,
                'color' => $this->vehicle->color,
            ],
        ];

        if ($this->details) {
            $data += [
                'pickup_points' => TripPointResource::collection($this->points->where('kind', PointKind::Pickup)->values()),
                'dropoff_points' => TripPointResource::collection($this->points->where('kind', PointKind::Dropoff)->values()),
                'luggage' => [
                    'large_per_passenger' => $this->luggage_large,
                    'small_per_passenger' => $this->luggage_small,
                    'extra_bag_price' => $this->extra_luggage_price,
                ],
                'notes' => $this->notes,
                // Photos that don't show the plate (plate is revealed after acceptance).
                'vehicle_photos' => $this->vehicle->documents
                    ->whereIn('kind', [DocumentKind::VehicleSide, DocumentKind::VehicleInterior])
                    ->map(fn ($d) => ['kind' => $d->kind->value, 'url' => $d->temporaryUrl()])->values(),
            ];
        }

        return $data;
    }
}
