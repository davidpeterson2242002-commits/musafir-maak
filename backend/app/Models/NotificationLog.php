<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Outcome of one notification on one channel (sent / failed / skipped). */
class NotificationLog extends Model
{
    public const UPDATED_AT = null;

    public const SENT = 'sent';

    public const FAILED = 'failed';

    public const SKIPPED = 'skipped';

    protected $fillable = ['user_id', 'phone', 'event', 'channel', 'status', 'title', 'error', 'broadcast_id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function broadcast(): BelongsTo
    {
        return $this->belongsTo(Broadcast::class);
    }
}
