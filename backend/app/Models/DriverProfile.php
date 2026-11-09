<?php

namespace App\Models;

use App\Enums\DriverStatus;
use App\Enums\VehicleStatus;
use App\Models\Concerns\HasDocuments;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class DriverProfile extends Model
{
    use HasDocuments, HasFactory;

    protected $fillable = [
        'user_id', 'status', 'free_bookings_remaining', 'terms_accepted_at', 'submitted_at', 'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => DriverStatus::class,
            'terms_accepted_at' => 'datetime',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'settlement_due_at' => 'datetime',
            'settlement_reminded_at' => 'datetime',
            'balance_owed' => 'integer',
            'wallet_balance' => 'integer',
            'frozen_at' => 'datetime',
            'rating_avg' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }

    public function approvedVehicles(): HasMany
    {
        return $this->vehicles()->where('status', VehicleStatus::Approved);
    }

    public function trips(): HasMany
    {
        return $this->hasMany(Trip::class);
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }

    public function settlementRequests(): HasMany
    {
        return $this->hasMany(SettlementRequest::class);
    }

    public function isFrozen(): bool
    {
        return $this->status === DriverStatus::Frozen;
    }

    public function balanceLimitReached(): bool
    {
        $limit = settings()->int('settlement.balance_limit');

        return $limit > 0 && $this->balance_owed >= $limit;
    }

    /** End of the grace period after the due date; freezing happens after this. */
    public function freezesAt(): ?Carbon
    {
        return $this->settlement_due_at?->copy()->addHours(settings()->int('settlement.grace_hours'));
    }

    public function isActive(): bool
    {
        return $this->status === DriverStatus::Active;
    }

    /** Active and has at least one approved vehicle: may post trips (M3). */
    public function canOperate(): bool
    {
        return $this->isActive() && $this->approvedVehicles()->exists();
    }
}
