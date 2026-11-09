<?php

namespace App\Models;

use App\Enums\BookingStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'trip_id', 'passenger_id', 'seats', 'pickup_point_id', 'extra_bags', 'includes_family', 'message',
        'status', 'price_total', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => BookingStatus::class,
            'includes_family' => 'boolean',
            'fee_free' => 'boolean',
            'fee_amount' => 'integer',
            'fee_reversed_at' => 'datetime',
            'late_cancellation' => 'boolean',
            'expires_at' => 'datetime',
            'last_reminded_at' => 'datetime',
            'responded_at' => 'datetime',
            'accepted_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'no_show_at' => 'datetime',
            'no_show_disputed_at' => 'datetime',
            'no_show_confirmed_at' => 'datetime',
            'completed_at' => 'datetime',
            'reminded_evening_at' => 'datetime',
            'reminded_soon_at' => 'datetime',
        ];
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function passenger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'passenger_id')->withTrashed();
    }

    public function pickupPoint(): BelongsTo
    {
        return $this->belongsTo(TripPoint::class, 'pickup_point_id');
    }

    /** Contact details (phones, plate) are shared once the driver accepts. */
    public function revealsContacts(): bool
    {
        return in_array($this->status, [BookingStatus::Accepted, BookingStatus::Completed, BookingStatus::NoShow], true);
    }

    public function noShowDisputeOpen(): bool
    {
        return $this->status === BookingStatus::NoShow && $this->no_show_disputed_at !== null && $this->no_show_resolution === null;
    }
}
