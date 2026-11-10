<?php

namespace App\Services;

use App\Enums\NotificationChannelType;
use App\Models\NotificationLog;
use Illuminate\Support\Facades\Log;
use Throwable;

/** Writes the notification log. Never throws: logging must not break a delivery or a business action. */
class NotificationLogger
{
    public function record(
        NotificationChannelType $channel,
        string $event,
        string $status,
        ?int $userId = null,
        ?string $phone = null,
        ?string $title = null,
        ?string $error = null,
        ?int $broadcastId = null,
    ): void {
        try {
            NotificationLog::create([
                'user_id' => $userId,
                'phone' => $phone,
                'event' => $event,
                'channel' => $channel->value,
                'status' => $status,
                'title' => $title === null ? null : mb_substr($title, 0, 200),
                'error' => $error === null ? null : mb_substr($error, 0, 1000),
                'broadcast_id' => $broadcastId,
            ]);
        } catch (Throwable $e) {
            Log::warning('Notification log write failed', ['error' => $e->getMessage()]);
        }
    }
}
