<?php

namespace App\Jobs;

use App\Contracts\PushGateway;
use App\Contracts\WhatsAppGateway;
use App\Enums\NotificationChannelType;
use App\Mail\NotificationMail;
use App\Models\DeviceToken;
use App\Models\NotificationLog;
use App\Models\User;
use App\Services\NotificationLogger;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Delivers one notification on one channel, off the request cycle.
 * Retried on provider failure; the final outcome is written to notification_logs.
 */
class DeliverNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> seconds before the 2nd and 3rd attempt */
    public array $backoff = [30, 180];

    public function __construct(
        public int $userId,
        public string $event,
        public string $channel,
        public string $title,
        public string $body,
        public array $data = [],
        public ?int $broadcastId = null,
    ) {}

    public function handle(PushGateway $push, WhatsAppGateway $whatsapp, NotificationLogger $log): void
    {
        $user = User::find($this->userId);
        $channel = NotificationChannelType::from($this->channel);

        if ($user === null) {
            return; // account deleted meanwhile
        }

        $skipped = match ($channel) {
            NotificationChannelType::Push => $this->push($user, $push),
            NotificationChannelType::WhatsApp => $this->whatsapp($user, $whatsapp),
            NotificationChannelType::Email => $this->email($user),
        };

        $log->record(
            $channel, $this->event, $skipped === null ? NotificationLog::SENT : NotificationLog::SKIPPED,
            userId: $user->id,
            phone: $channel === NotificationChannelType::WhatsApp ? $user->whatsappNumber() : null,
            title: $this->title, error: $skipped, broadcastId: $this->broadcastId,
        );
    }

    /** Called once all attempts are used up (or immediately on the sync queue). */
    public function failed(?Throwable $e): void
    {
        app(NotificationLogger::class)->record(
            NotificationChannelType::from($this->channel), $this->event, NotificationLog::FAILED,
            userId: $this->userId, title: $this->title, error: $e?->getMessage(), broadcastId: $this->broadcastId,
        );
    }

    /** @return string|null reason the send was skipped, null when sent */
    private function push(User $user, PushGateway $gateway): ?string
    {
        $tokens = $user->deviceTokens()->pluck('token')->all();
        if ($tokens === []) {
            return 'لا يوجد جهاز مسجّل';
        }

        $invalid = $gateway->send($tokens, $this->title, $this->body, ['event' => $this->event] + $this->data);
        if ($invalid !== []) {
            DeviceToken::whereIn('token', $invalid)->delete();
        }

        return count($invalid) === count($tokens) ? 'كل أجهزة المستخدم غير صالحة' : null;
    }

    private function whatsapp(User $user, WhatsAppGateway $gateway): ?string
    {
        $gateway->send(
            $user->whatsappNumber(),
            $this->event,
            ['title' => $this->title, 'body' => $this->body] + $this->data,
            "$this->title\n$this->body",
        );

        return null;
    }

    private function email(User $user): ?string
    {
        if (blank($user->email)) {
            return 'لا يوجد بريد إلكتروني';
        }

        Mail::to($user->email)->send(new NotificationMail($this->title, $this->body));

        return null;
    }
}
