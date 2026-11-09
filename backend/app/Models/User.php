<?php

namespace App\Models;

use App\Enums\Gender;
use App\Enums\IdVerificationStatus;
use App\Enums\UserStatus;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use Auditable, HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name', 'phone', 'whatsapp_phone', 'email', 'gender', 'city_id', 'photo_path',
        'status', 'suspended_until', 'id_verification_status', 'phone_verified_at',
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
}
