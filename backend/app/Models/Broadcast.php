<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A message sent by an admin to a segment of users (spec 9.10). */
class Broadcast extends Model
{
    public const SEGMENTS = [
        'all_users' => 'كل المستخدمين',
        'users_in_city' => 'المستخدمون في مدينة',
        'all_drivers' => 'كل السائقين',
        'drivers_in_city' => 'السائقون في مدينة',
        'drivers_owing' => 'السائقون الذين عليهم رصيد مستحق',
        'searched_route' => 'ركاب بحثوا عن طريق',
    ];

    public const STATUSES = [
        'queued' => 'في الانتظار',
        'sending' => 'جارٍ الإرسال',
        'sent' => 'تم الإرسال',
        'failed' => 'فشل',
    ];

    protected $fillable = ['admin_id', 'segment', 'filters', 'channels', 'title', 'body', 'recipients_count', 'status', 'sent_at'];

    protected function casts(): array
    {
        return ['filters' => 'array', 'channels' => 'array', 'sent_at' => 'datetime'];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(NotificationLog::class);
    }
}
