<?php

namespace App\Models;

use App\Enums\Gender;
use App\Enums\IdVerificationStatus;
use App\Enums\UserStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasDocuments;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use Auditable, HasApiTokens, HasDocuments, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name', 'phone', 'whatsapp_phone', 'email', 'gender', 'city_id', 'photo_path',
        'status', 'suspended_until', 'booking_restricted_until', 'id_verification_status', 'phone_verified_at',
        'terms_accepted_at', 'last_seen_at',
    ];

    protected $attributes = [
        'status' => 'active',
        'id_verification_status' => 'none',
    ];

    protected function casts(): array
    {
        return [
            'gender' => Gender::class,
            'status' => UserStatus::class,
            'id_verification_status' => IdVerificationStatus::class,
            'suspended_until' => 'datetime',
            'booking_restricted_until' => 'datetime',
            'phone_verified_at' => 'datetime',
            'terms_accepted_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'rating_avg' => 'decimal:2',
        ];
    }

    public function auditExclude(): array
    {
        return ['updated_at', 'created_at', 'last_seen_at', 'remember_token'];
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function driverProfile(): HasOne
    {
        return $this->hasOne(DriverProfile::class);
    }

    /**
     * May this user submit a booking or parcel request? (spec 5.12)
     * Verified ID always; pending ID only if the admin setting allows it.
     */
    public function idAllowsBooking(): bool
    {
        return match ($this->id_verification_status) {
            IdVerificationStatus::Verified => true,
            IdVerificationStatus::Pending => settings()->bool('verification.allow_first_booking_while_pending'),
            default => false,
        };
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'passenger_id');
    }

    public function parcels(): HasMany
    {
        return $this->hasMany(Parcel::class, 'sender_id');
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(UserBlock::class, 'blocker_id');
    }

    public function enforcementActions(): HasMany
    {
        return $this->hasMany(EnforcementAction::class)->latest('id');
    }

    /** Either user blocked the other (spec P13). */
    public function isBlockedWith(int $otherUserId): bool
    {
        return UserBlock::where(fn ($q) => $q->where('blocker_id', $this->id)->where('blocked_id', $otherUserId))
            ->orWhere(fn ($q) => $q->where('blocker_id', $otherUserId)->where('blocked_id', $this->id))
            ->exists();
    }

    /** Ids of users this user blocked or was blocked by. @return array<int, int> */
    public function blockedUserIds(): array
    {
        return UserBlock::where('blocker_id', $this->id)->pluck('blocked_id')
            ->merge(UserBlock::where('blocked_id', $this->id)->pluck('blocker_id'))
            ->unique()->values()->all();
    }

    public function routeAlerts(): HasMany
    {
        return $this->hasMany(RouteAlert::class);
    }

    /** Temporary booking block after repeated late cancellations (spec 5.4). */
    public function isBookingRestricted(): bool
    {
        return $this->booking_restricted_until !== null && $this->booking_restricted_until->isFuture();
    }

    public function deviceTokens(): HasMany
    {
        return $this->hasMany(DeviceToken::class);
    }

    /** Profile setup (name, gender, city, terms) is done after the first OTP login. */
    public function profileCompleted(): bool
    {
        return filled($this->name) && $this->gender !== null && $this->city_id !== null && $this->terms_accepted_at !== null;
    }

    public function whatsappNumber(): string
    {
        return $this->whatsapp_phone ?: $this->phone;
    }

    public function isBanned(): bool
    {
        return $this->status === UserStatus::Banned;
    }

    /** Suspended now? Auto-lifts once suspended_until has passed. */
    public function isSuspended(): bool
    {
        if ($this->status !== UserStatus::Suspended) {
            return false;
        }

        if ($this->suspended_until !== null && $this->suspended_until->isPast()) {
            $this->forceFill(['status' => UserStatus::Active, 'suspended_until' => null])->save();

            return false;
        }

        return true;
    }

    /** Marks the user as online now (at most every 20 seconds, no model events). */
    public function touchSeen(): void
    {
        if (! $this->last_seen_at || $this->last_seen_at->lt(now()->subSeconds(20))) {
            $this->forceFill(['last_seen_at' => now()])->saveQuietly();
        }
    }
}
