<?php

namespace App\Support;

use App\Enums\NotificationEvent;
use App\Models\NotificationChannel;
use Illuminate\Support\Facades\Cache;

/**
 * Which channels each notification event uses (spec 5.14 / 9.9).
 * Admin-editable rows in `notification_channels`; an event without a row uses its defaults (spec 10).
 */
class ChannelMatrix
{
    public const CACHE_KEY = 'notification_channels.v1';

    /** @var array<string, array{push: bool, whatsapp: bool, email: bool}>|null */
    private ?array $rows = null;

    /** @return array{push: bool, whatsapp: bool, email: bool} */
    public function for(NotificationEvent $event): array
    {
        return $this->all()[$event->value] ?? $event->defaultChannels();
    }

    public function flush(): void
    {
        $this->rows = null;
        Cache::forget(self::CACHE_KEY);
    }

    /** Create the missing rows with the defaults; never touches rows the admin changed. */
    public function sync(): int
    {
        $existing = NotificationChannel::pluck('event')->all();
        $created = 0;

        foreach (NotificationEvent::configurable() as $event) {
            if (! in_array($event->value, $existing, true)) {
                NotificationChannel::create(['event' => $event->value] + $event->defaultChannels());
                $created++;
            }
        }

        return $created;
    }

    private function all(): array
    {
        return $this->rows ??= Cache::rememberForever(self::CACHE_KEY, fn () => NotificationChannel::all()
            ->mapWithKeys(fn (NotificationChannel $row) => [$row->event => [
                'push' => $row->push, 'whatsapp' => $row->whatsapp, 'email' => $row->email,
            ]])->all());
    }
}
