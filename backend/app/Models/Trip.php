<?php

namespace App\Models;

use App\Enums\BookingStatus;
use App\Enums\DriverStatus;
use App\Enums\PointKind;
use App\Enums\TripStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Trip extends Model
{
    use HasFactory;

    protected $fillable = [
        'driver_profile_id', 'vehicle_id', 'route_id', 'departs_at', 'est_arrival_at', 'price_per_seat',
        'seats_total', 'seats_available', 'luggage_large', 'luggage_small', 'extra_luggage_price',
        'accepts_parcels', 'parcel_price_small', 'parcel_price_medium', 'parcel_price_large', 'max_parcels',
        'women_only', 'notes', 'status',
    ];

    protected function casts(): array
    {
        return [
            'departs_at' => 'datetime',
            'est_arrival_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'departed_at' => 'datetime',
            'completed_at' => 'datetime',
            'reminded_evening_at' => 'datetime',
            'reminded_soon_at' => 'datetime',
            'status' => TripStatus::class,
            'accepts_parcels' => 'boolean',
            'women_only' => 'boolean',
            'hidden_by_admin' => 'boolean',
        ];
    }

    public function driverProfile(): BelongsTo
    {
        return $this->belongsTo(DriverProfile::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class)->withTrashed();
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(TravelRoute::class, 'route_id');
    }

    public function points(): HasMany
    {
        return $this->hasMany(TripPoint::class)->orderBy('sort');
    }

    public function pickupPoints(): HasMany
    {
        return $this->points()->where('kind', PointKind::Pickup);
    }

    public function dropoffPoints(): HasMany
    {
        return $this->points()->where('kind', PointKind::Dropoff);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function acceptedBookings(): HasMany
    {
        return $this->bookings()->where('status', BookingStatus::Accepted);
    }

    /** Trips a passenger can find and book right now. */
    public function scopeBookable(Builder $query): Builder
    {
        return $query->where('trips.status', TripStatus::Scheduled)
            ->where('trips.hidden_by_admin', false)
            ->where('trips.departs_at', '>', now())
            ->whereHas('driverProfile', fn ($q) => $q->where('status', DriverStatus::Active));
    }

    public function isBookable(): bool
    {
        return $this->status === TripStatus::Scheduled
            && ! $this->hidden_by_admin
            && $this->departs_at->isFuture()
            && $this->driverProfile?->isActive();
    }

    /** Has any booking that the driver accepted or still has to answer. */
    public function hasActiveBookings(): bool
    {
        return $this->bookings()->whereIn('status', BookingStatus::active())->exists();
    }
}
