<?php

namespace App\Services;

use App\Enums\NotificationChannelType;
use App\Enums\NotificationEvent;
use App\Jobs\DeliverNotification;
use App\Models\User;
use App\Models\UserNotification;
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
            $this->keepInInbox($user, $event, $title, $body, $data);

            foreach ($channels ?? $this->enabledChannels($event) as $channel) {
                // Email is opt-in by having an address; don't queue (or log) anything for users without one.
                if ($channel === NotificationChannelType::Email && blank($user->email)) {
                    continue;
                }

                try {
                    $job = new DeliverNotification($user->id, $event->value, $channel->value, $title, $body, $data, $broadcastId);
                    $this->sendsRightAway($channel) ? $this->afterResponse($job) : dispatch($job);
                } catch (Throwable $e) {
                    // On the sync queue a provider failure surfaces here; the job has already logged it.
                    Log::warning('Notification failed', [
                        'user' => $user->id, 'event' => $event->value, 'channel' => $channel->value, 'error' => $e->getMessage(),
                    ]);
                }
            }
        });
    }

    /**
     * Push and email go out as soon as the HTTP response has been sent (the queue is only drained
     * once a minute by cron on shared hosting, which made notifications up to a minute late).
     * WhatsApp (paid, rate-limited) and everything started from cron stay on the queue.
     */
    private function sendsRightAway(NotificationChannelType $channel): bool
    {
        return in_array($channel, [NotificationChannelType::Push, NotificationChannelType::Email], true)
            && (bool) config('notifications.send_after_response', ! app()->runningInConsole());
    }

    /** Runs the job after the response is flushed to the phone; a failure falls back to the queue (with retries). */
    private function afterResponse(DeliverNotification $job): void
    {
        app()->terminating(function () use ($job) {
            try {
                app()->call([$job, 'handle']);
            } catch (Throwable $e) {
                Log::info('Immediate notification failed, queued for retry', ['event' => $job->event, 'error' => $e->getMessage()]);
                dispatch($job);
            }
        });
    }

    /**
     * Every notification also lands in the in-app list (the bell), so a push that was swiped
     * away or never arrived can still be opened. Chat messages have their own unread list.
     */
    private function keepInInbox(User $user, NotificationEvent $event, string $title, string $body, array $data): void
    {
        if ($event === NotificationEvent::NewChatMessage) {
            return;
        }
        try {
            UserNotification::create([
                'user_id' => $user->id, 'event' => $event->value, 'title' => mb_substr($title, 0, 200),
                'body' => $body, 'data' => $data ?: null,
            ]);
        } catch (Throwable $e) {
            Log::warning('Inbox write failed', ['user' => $user->id, 'event' => $event->value, 'error' => $e->getMessage()]);
        }
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
