<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\NotificationEvent;
use App\Enums\ParcelStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Booking;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Parcel;
use App\Models\User;
use App\Support\Phone;

/**
 * In-app chat per booking / parcel (spec 5.8).
 *
 * Before the driver accepts, phone numbers and messaging links are masked (so contact
 * can't move outside the app before a booking exists) and the message is flagged for review.
 * Messages with listed keywords are flagged too (not blocked). Admins read chats only
 * through reports or flags.
 */
class ChatService
{
    public const MASK = '[رقم مخفي — يظهر بعد قبول الحجز]';

    public function __construct(private Notifier $notifier) {}

    /** Created when the booking/parcel is requested; idempotent. */
    public function openFor(Booking|Parcel $subject): Conversation
    {
        $subject->loadMissing('trip.driverProfile');

        return Conversation::firstOrCreate(
            ['subject_type' => $subject->getMorphClass(), 'subject_id' => $subject->id],
            [
                'customer_id' => $subject instanceof Booking ? $subject->passenger_id : $subject->sender_id,
                'driver_user_id' => $subject->trip->driverProfile->user_id,
            ],
        );
    }

    public function send(Conversation $conversation, User $sender, string $body): Message
    {
        $body = trim($body);
        if ($body === '') {
            throw new BusinessRuleException('اكتب رسالة.', 'empty_message');
        }
        if (! $conversation->isParticipant($sender)) {
            abort(404);
        }

        $subject = $conversation->subject;
        if (! $this->isOpen($subject)) {
            throw new BusinessRuleException('هذه المحادثة مغلقة.', 'conversation_closed');
        }
        if ($sender->isBlockedWith($conversation->otherParticipantId($sender))) {
            throw new BusinessRuleException('لا يمكن إرسال رسائل لهذا المستخدم.', 'blocked');
        }

        $revealed = $subject->revealsContacts();
        [$shown, $masked] = $revealed ? [$body, false] : self::mask($body);
        $flagReason = $masked ? 'number_before_acceptance' : $this->keywordFlag($body);

        $message = $conversation->messages()->create([
            'sender_id' => $sender->id,
            'body' => $shown,
            'original_body' => $masked ? $body : null,
            'was_masked' => $masked,
            'flagged' => $flagReason !== null,
            'flag_reason' => $flagReason,
        ]);

        $conversation->forceFill([
            'last_message_at' => now(),
            $conversation->lastReadColumn($sender) => now(),
            'has_flagged' => $conversation->has_flagged || $flagReason !== null,
        ])->save();

        $recipient = User::find($conversation->otherParticipantId($sender));
        if ($recipient) {
            $this->notifier->send($recipient, NotificationEvent::NewChatMessage, $sender->name ?? 'رسالة جديدة', mb_strimwidth($shown, 0, 120, '…'),
                ['conversation_id' => $conversation->id]);
        }

        return $message;
    }

    public function markRead(Conversation $conversation, User $user): void
    {
        $conversation->forceFill([$conversation->lastReadColumn($user) => now()])->save();
    }

    public function unreadCount(Conversation $conversation, User $user): int
    {
        $lastRead = $conversation->{$conversation->lastReadColumn($user)};

        return $conversation->messages()
            ->where('sender_id', '!=', $user->id)
            ->when($lastRead, fn ($q) => $q->where('created_at', '>', $lastRead))
            ->count();
    }

    /** Can new messages be sent? Active bookings/parcels, plus a few days after the trip. */
    public function isOpen(Booking|Parcel $subject): bool
    {
        $subject->loadMissing('trip');
        $graceEnd = $subject->trip->departs_at->copy()->addDays(settings()->int('chat.days_after_trip') + 1);

        if ($subject instanceof Booking) {
            return $subject->status->isActive()
                || (in_array($subject->status, [BookingStatus::Completed, BookingStatus::NoShow], true) && now()->lt($graceEnd));
        }

        return in_array($subject->status, [ParcelStatus::Pending, ParcelStatus::Accepted, ParcelStatus::PickedUp, ParcelStatus::Disputed], true)
            || ($subject->status === ParcelStatus::Delivered && now()->lt($graceEnd));
    }

    /**
     * Mask phone numbers (7+ digits, with spaces/dashes, Arabic-Indic digits) and
     * messaging links. @return array{0: string, 1: bool}
     */
    public static function mask(string $text): array
    {
        $masked = false;
        $digit = '[0-9٠-٩۰-۹]';

        // Candidate runs of digits with spaces/dashes/dots between them. Only phone-like runs are masked:
        // a valid Sudanese number, an international number, or 9+ digits. Dates, times and prices stay.
        $text = preg_replace_callback('/(?:\+|00)?(?:'.$digit.'[\s\-\.\/\(\)]{0,2}){6,}'.$digit.'/u', function ($m) use (&$masked) {
            $digits = preg_replace('/\D+/', '', strtr($m[0], [
                '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
                '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            ]));
            $international = str_starts_with(ltrim($m[0]), '+') || str_starts_with($digits, '00');

            if (Phone::isValid($digits) || strlen($digits) >= 9 || ($international && strlen($digits) >= 8)) {
                $masked = true;

                return self::MASK;
            }

            return $m[0];
        }, $text);

        $text = preg_replace('~(?:https?://)?(?:wa\.me|api\.whatsapp\.com|chat\.whatsapp\.com|t\.me)/\S*~iu', self::MASK, $text, -1, $links);

        return [$text, $masked || $links > 0];
    }

    private function keywordFlag(string $body): ?string
    {
        $lower = mb_strtolower($body);
        foreach (settings()->list('chat.flag_keywords') as $word) {
            if ($word !== '' && str_contains($lower, mb_strtolower($word))) {
                return 'keyword';
            }
        }

        return null;
    }
}
