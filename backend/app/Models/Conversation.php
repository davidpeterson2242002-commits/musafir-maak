<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Conversation extends Model
{
    protected $fillable = ['subject_type', 'subject_id', 'customer_id', 'driver_user_id', 'last_message_at'];

    protected function casts(): array
    {
        return [
            'customer_last_read_at' => 'datetime',
            'driver_last_read_at' => 'datetime',
            'last_message_at' => 'datetime',
            'has_flagged' => 'boolean',
        ];
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id')->withTrashed();
    }

    public function driverUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_user_id')->withTrashed();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    public function isParticipant(User $user): bool
    {
        return in_array($user->id, [$this->customer_id, $this->driver_user_id], true);
    }

    public function otherParticipantId(User $user): int
    {
        return $user->id === $this->customer_id ? $this->driver_user_id : $this->customer_id;
    }

    public function lastReadColumn(User $user): string
    {
        return $user->id === $this->customer_id ? 'customer_last_read_at' : 'driver_last_read_at';
    }
}
