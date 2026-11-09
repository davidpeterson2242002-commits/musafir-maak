<?php

namespace App\Services;

use App\Contracts\PushGateway;
use App\Contracts\WhatsAppGateway;
use App\Enums\NotificationEvent;
use App\Mail\NotificationMail;
use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Sends a user-facing notification on the event's default channels.
 * Runs after the surrounding DB transaction commits, and never throws:
 * a failed notification must not undo a business action.
 */
class Notifier
{
    public function __construct(
        private PushGateway $push,
        private WhatsAppGateway $whatsapp,
    ) {}

    public function send(User $user, NotificationEvent $event, string $title, string $body, array $data = []): void
    {
        DB::afterCommit(function () use ($user, $event, $title, $body, $data) {
            $data = ['event' => $event->value] + $data;

            $tokens = $event->sendsPush() ? $user->deviceTokens()->pluck('token')->all() : [];
            if ($tokens !== []) {
                try {
                    $invalid = $this->push->send($tokens, $title, $body, $data);
                    if ($invalid !== []) {
                        DeviceToken::whereIn('token', $invalid)->delete();
                    }
                } catch (Throwable $e) {
                    Log::warning('Push failed', ['user' => $user->id, 'event' => $event->value, 'error' => $e->getMessage()]);
                }
            }

            if ($event->sendsEmail() && filled($user->email)) {
                try {
                    Mail::to($user->email)->send(new NotificationMail($title, $body));
                } catch (Throwable $e) {
                    Log::warning('Email failed', ['user' => $user->id, 'event' => $event->value, 'error' => $e->getMessage()]);
                }
            }

            if ($event->sendsWhatsApp()) {
                try {
                    $this->whatsapp->send($user->whatsappNumber(), $event->value, $data, "$title\n$body");
                } catch (Throwable $e) {
                    Log::warning('WhatsApp failed', ['user' => $user->id, 'event' => $event->value, 'error' => $e->getMessage()]);
                }
            }
        });
    }
}
