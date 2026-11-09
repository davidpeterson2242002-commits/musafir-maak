<?php

namespace App\Models;

use App\Enums\ParcelStatus;
use App\Support\SettingsRegistry;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Facades\Storage;

class Parcel extends Model
{
    use HasFactory;

    public const SIZES = ['small', 'medium', 'large'];

    protected $fillable = [
        'trip_id', 'sender_id', 'description', 'size', 'photo_disk', 'photo_path', 'receiver_name', 'receiver_phone',
        'payer', 'message', 'price', 'status', 'prohibited_ack_at', 'expires_at',
    ];

    protected $hidden = ['delivery_code'];

    protected function casts(): array
    {
        return [
            'status' => ParcelStatus::class,
            'delivery_code' => 'encrypted',
            'fee_free' => 'boolean',
            'fee_amount' => 'integer',
            'fee_reversed_at' => 'datetime',
            'prohibited_ack_at' => 'datetime',
            'expires_at' => 'datetime',
            'last_reminded_at' => 'datetime',
            'accepted_at' => 'datetime',
            'picked_up_at' => 'datetime',
            'delivered_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'receiver_notified_at' => 'datetime',
            'disputed_at' => 'datetime',
        ];
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id')->withTrashed();
    }

    public function conversation(): MorphOne
    {
        return $this->morphOne(Conversation::class, 'subject');
    }

    public function photoUrl(): string
    {
        return Storage::disk($this->photo_disk)->temporaryUrl($this->photo_path, now()->addMinutes(config('filesystems.signed_url_minutes')));
    }

    public function pickupPhotoUrl(): ?string
    {
        return $this->pickup_photo_path
            ? Storage::disk($this->pickup_photo_disk)->temporaryUrl($this->pickup_photo_path, now()->addMinutes(config('filesystems.signed_url_minutes')))
            : null;
    }

    /** Driver contact details are shared once accepted. */
    public function revealsContacts(): bool
    {
        return in_array($this->status, [ParcelStatus::Accepted, ParcelStatus::PickedUp, ParcelStatus::Delivered, ParcelStatus::Disputed], true);
    }

    public function sizeLabel(): string
    {
        return SettingsRegistry::PARCEL_SIZES[$this->size] ?? $this->size;
    }
}
