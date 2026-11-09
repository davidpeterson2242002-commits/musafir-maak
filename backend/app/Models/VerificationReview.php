<?php

namespace App\Models;

use App\Enums\IdDocumentType;
use App\Enums\ReviewStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One submission of a user's ID, a driver application, or a vehicle.
 * Created as pending; an admin approves, rejects or requests more info.
 */
class VerificationReview extends Model
{
    /** Minutes a reviewer keeps an item after opening it. */
    public const CLAIM_MINUTES = 15;

    protected $fillable = [
        'subject_type', 'subject_id', 'status', 'id_document_type', 'priority', 'reasons', 'note',
        'assigned_to', 'assigned_at', 'reviewed_by', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ReviewStatus::class,
            'id_document_type' => IdDocumentType::class,
            'reasons' => 'array',
            'assigned_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'reviewed_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'assigned_to');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', ReviewStatus::Pending);
    }

    public function isPending(): bool
    {
        return $this->status === ReviewStatus::Pending;
    }

    /** Is another reviewer currently working on this item? */
    public function isClaimedByOther(Admin $admin): bool
    {
        return $this->assigned_to !== null
            && $this->assigned_to !== $admin->id
            && $this->assigned_at?->gt(now()->subMinutes(self::CLAIM_MINUTES));
    }

    public function kindLabel(): string
    {
        return match ($this->subject_type) {
            'user' => 'هوية راكب',
            'driver_profile' => 'طلب سائق',
            'vehicle' => 'عربية',
            default => $this->subject_type,
        };
    }
}
