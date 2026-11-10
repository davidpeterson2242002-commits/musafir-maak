<?php

namespace App\Models;

use App\Enums\NotificationEvent;
use App\Models\Concerns\Auditable;
use App\Support\ChannelMatrix;
use Illuminate\Database\Eloquent\Model;

/** One row of the admin-editable channel matrix: which channels an event uses (spec 5.14). */
class NotificationChannel extends Model
{
    use Auditable;

    protected $fillable = ['event', 'push', 'whatsapp', 'email'];

    protected function casts(): array
    {
        return ['push' => 'boolean', 'whatsapp' => 'boolean', 'email' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saved(fn () => app(ChannelMatrix::class)->flush());
        static::deleted(fn () => app(ChannelMatrix::class)->flush());
    }

    public function label(): string
    {
        return NotificationEvent::labelFor($this->event);
    }
}
