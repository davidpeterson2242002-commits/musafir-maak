<?php

namespace App\Services;

use App\Enums\NotificationChannelType;
use App\Enums\NotificationEvent;
use App\Jobs\DeliverNotification;
use App\Models\User;
use App\Support\ChannelMatrix;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sends a user-facing notification on the channels the admin enabled for the event
 * (ChannelMatrix; defaults from spec section 10).
 *
 * Runs after the surrounding DB transaction commits, and never throws: a failed
 * notification must not undo a business action. Each channel is a queued job
 * (DeliverNotification) so slow providers never hold up an API request.
 */
class Notifier
{
    public function __construct(private ChannelMatrix $matrix) {}

    /**
     * @param  array<int, NotificationChannelType>|null  $channels  force these channels (broadcasts); null = use the matrix
     */
    public function send(
        User $user,
        NotificationEvent $event,
        string $title,
        string $body,
        array $data = [],
        ?array $channels = null,
        ?int $broadcastId = null,
    ): void {
        DB::afterCommit(function () use ($user, $event, $title, $body, $data, $channels, $broadcastId) {
            foreach ($channels ?? $this->enabledChannels($event) as $channel) {
                // Email is opt-in by having an address; don't queue (or log) anything for users without one.
                if ($channel === NotificationChannelType::Email && blank($user->email)) {
                    continue;
                }

                try {
                    DeliverNotification::dispatch($user->id, $event->value, $channel->value, $title, $body, $data, $broadcastId);
                } catch (Throwable $e) {
                    // On the sync queue a provider failure surfaces here; the job has already logged it.
                    Log::warning('Notification failed', [
                        'user' => $user->id, 'event' => $event->value, 'channel' => $channel->value, 'error' => $e->getMessage(),
                    ]);
                }
            }
        });
    }

    /** @return array<int, NotificationChannelType> */
    private function enabledChannels(NotificationEvent $event): array
    {
        $enabled = $this->matrix->for($event);

        return array_values(array_filter(
            NotificationChannelType::cases(),
            fn (NotificationChannelType $c) => $enabled[$c->value] ?? false,
        ));
    }
}
