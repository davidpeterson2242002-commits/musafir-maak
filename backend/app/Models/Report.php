<?php

namespace App\Models;

use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Report extends Model
{
    protected $fillable = ['reporter_id', 'reported_user_id', 'subject_type', 'subject_id', 'reason', 'details', 'priority', 'status'];

    protected function casts(): array
    {
        return ['reason' => ReportReason::class, 'status' => ReportStatus::class, 'handled_at' => 'datetime'];
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id')->withTrashed();
    }

    public function reportedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_user_id')->withTrashed();
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'handled_by');
    }

    public function enforcementActions(): HasMany
    {
        return $this->hasMany(EnforcementAction::class);
    }

    /** Conversation behind the report (the booking/parcel chat), for the admin chat log. */
    public function relatedConversation(): ?Conversation
    {
        return match (true) {
            $this->subject instanceof Conversation => $this->subject,
            $this->subject instanceof Booking, $this->subject instanceof Parcel => $this->subject->conversation,
            default => null,
        };
    }
}
