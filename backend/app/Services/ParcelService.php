<?php

namespace App\Services;

use App\Contracts\WhatsAppGateway;
use App\Enums\IdVerificationStatus;
use App\Enums\NotificationEvent;
use App\Enums\ParcelStatus;
use App\Exceptions\BusinessRuleException;
use App\Jobs\SendParcelCodeToReceiver;
use App\Models\Admin;
use App\Models\Parcel;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/** Parcels / ارسالية (spec 5.6, S1–S4, D11–D12). */
class ParcelService
{
    public function __construct(
        private Notifier $notifier,
        private LedgerService $ledger,
        private ChatService $chat,
        private WhatsAppGateway $whatsapp,
        private AuditLogger $audit,
    ) {}

    // ------------------------------------------------------------------ Sender

    /**
     * @param  array{description:string, size:string, receiver_name:string, receiver_phone:string, payer:string, message?:string|null}  $data
     */
    public function request(User $sender, Trip $trip, array $data, UploadedFile $photo): Parcel
    {
        $trip->loadMissing(['driverProfile.user', 'route.origin', 'route.destination']);

        if ($trip->driverProfile->user_id === $sender->id) {
            throw new BusinessRuleException('لا يمكنك إرسال ارسالية في رحلتك.', 'own_trip');
        }
        app(BookingService::class)->assertCanBook($sender);
        if ($sender->isBlockedWith($trip->driverProfile->user_id)) {
            throw new BusinessRuleException('لا يمكنك الإرسال مع هذا السائق.', 'blocked');
        }
        if (! $trip->isBookable()) {
            throw new BusinessRuleException('هذه الرحلة لم تعد متاحة.', 'trip_unavailable');
        }
        if (! $trip->accepts_parcels || $trip->parcelSlotsLeft() === 0) {
            throw new BusinessRuleException('هذه الرحلة لا تقبل ارساليات أو اكتملت.', 'parcels_unavailable');
        }

        $price = (int) $trip->{'parcel_price_'.$data['size']};
        if ($price <= 0) {
            throw new BusinessRuleException('السائق لم يحدد سعراً لهذا الحجم.', 'parcels_unavailable');
        }

        $disk = config('filesystems.private_disk');
        $path = $photo->store('parcels/'.$sender->id, ['disk' => $disk]);

        $parcel = DB::transaction(function () use ($sender, $trip, $data, $price, $disk, $path) {
            $parcel = $trip->parcels()->create([
                'sender_id' => $sender->id,
                'description' => $data['description'],
                'size' => $data['size'],
                'photo_disk' => $disk,
                'photo_path' => $path,
                'receiver_name' => $data['receiver_name'],
                'receiver_phone' => $data['receiver_phone'],
                'payer' => $data['payer'],
                'message' => $data['message'] ?? null,
                'price' => $price,
                'status' => ParcelStatus::Pending,
                'prohibited_ack_at' => now(),
                'expires_at' => now()->addHours(settings()->int('booking.response_timeout_hours'))->min($trip->departs_at),
            ]);

            $this->chat->openFor($parcel);

            if ($sender->id_verification_status === IdVerificationStatus::Pending) {
                $sender->verificationReviews()->pending()->update(['priority' => 10]);
            }

            return $parcel;
        });

        $this->notifier->send(
            $trip->driverProfile->user,
            NotificationEvent::ParcelRequested,
            'طلب ارسالية جديد',
            sprintf('%s يريد إرسال ارسالية %s (%s) إلى %s في رحلة %s. الدفع: %s.',
                $sender->name, $parcel->sizeLabel(), $parcel->description, $trip->route->destination->name_ar,
                local_time($trip->departs_at)->format('Y-m-d H:i'), $parcel->payer === 'sender' ? 'المرسل يدفع' : 'المستلم يدفع'),
            ['parcel_id' => $parcel->id, 'trip_id' => $trip->id],
        );

        return $parcel;
    }

    /** Sender cancels before pickup. Commission reversed if it was accepted. */
    public function cancelBySender(Parcel $parcel, ?string $reason = null): Parcel
    {
        if (! in_array($parcel->status, [ParcelStatus::Pending, ParcelStatus::Accepted], true)) {
            throw new BusinessRuleException('لا يمكن إلغاء الارسالية بعد استلامها من السائق.', 'not_cancellable');
        }

        $wasAccepted = $parcel->status === ParcelStatus::Accepted;
        $parcel->forceFill(['status' => ParcelStatus::Cancelled, 'cancelled_at' => now(), 'cancel_reason' => $reason])->save();

        if ($wasAccepted) {
            $this->ledger->reverseParcel($parcel, 'إلغاء المرسل للارسالية #'.$parcel->id);
        }

        $this->notifier->send($parcel->trip->driverProfile->user, NotificationEvent::ParcelCancelled, 'ألغى المرسل ارسالية',
            "تم إلغاء الارسالية #{$parcel->id} ({$parcel->description}).", ['parcel_id' => $parcel->id]);

        return $parcel;
    }

    /** Not delivered / damaged → admin decides (spec 5.6). */
    public function dispute(Parcel $parcel, string $details): Parcel
    {
        $deliveredWindowOk = $parcel->status === ParcelStatus::Delivered
            && $parcel->delivered_at->gt(now()->subDays(settings()->int('parcels.dispute_days')));

        if (! ($parcel->status === ParcelStatus::PickedUp || $deliveredWindowOk)) {
            throw new BusinessRuleException('لا يمكن فتح نزاع على هذه الارسالية الآن.', 'not_disputable');
        }

        $parcel->forceFill(['status' => ParcelStatus::Disputed, 'disputed_at' => now(), 'dispute_details' => $details])->save();

        return $parcel;
    }

    // ------------------------------------------------------------------ Driver

    public function accept(Parcel $parcel): Parcel
    {
        $parcel->loadMissing(['trip.driverProfile.user', 'trip.vehicle', 'trip.route.origin', 'trip.route.destination', 'sender']);
        $driver = $parcel->trip->driverProfile;

        if ($driver->isFrozen()) {
            throw new BusinessRuleException('حسابك مجمّد لعدم سداد الرصيد المستحق.', 'driver_frozen');
        }
        if (! $driver->isActive() || $driver->user->isSuspended()) {
            throw new BusinessRuleException('حسابك كسائق غير نشط حالياً.', 'driver_not_active');
        }
        $this->assertPendingAndValid($parcel);

        $parcel = DB::transaction(function () use ($parcel) {
            $trip = Trip::whereKey($parcel->trip_id)->lockForUpdate()->first();
            $parcel = Parcel::whereKey($parcel->id)->lockForUpdate()->first();

            if ($parcel->status !== ParcelStatus::Pending) {
                throw new BusinessRuleException('تم الرد على هذا الطلب أو انتهت مهلته.', 'not_pending');
            }
            if ($trip->parcelSlotsLeft() === 0) {
                throw new BusinessRuleException('اكتمل عدد الارساليات في هذه الرحلة.', 'parcels_full');
            }

            $parcel->forceFill([
                'status' => ParcelStatus::Accepted,
                'accepted_at' => now(),
                'delivery_code' => str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT),
            ])->save();

            $this->ledger->chargeParcel($parcel, $trip);

            return $parcel;
        });

        $parcel->load(['trip.driverProfile.user', 'trip.vehicle', 'trip.route.origin', 'trip.route.destination', 'sender']);
        $trip = $parcel->trip;
        $driverUser = $trip->driverProfile->user;

        $this->notifier->send($parcel->sender, NotificationEvent::ParcelAccepted, 'تم قبول الارسالية',
            "سلّم الارسالية للسائق {$driverUser->name} ({$driverUser->phone}). رمز التسليم: {$parcel->delivery_code} — أرسله للمستلم إذا لم يصله على واتساب.",
            ['parcel_id' => $parcel->id]);

        DB::afterCommit(fn () => $this->notifyReceiver($parcel));

        return $parcel;
    }

    public function reject(Parcel $parcel, ?string $reason = null): Parcel
    {
        $this->assertPendingAndValid($parcel);
        $parcel->forceFill(['status' => ParcelStatus::Rejected, 'rejection_reason' => $reason])->save();

        $this->notifier->send($parcel->sender, NotificationEvent::ParcelRejected, 'لم يتم قبول الارسالية',
            'اعتذر السائق عن ارساليتك. ابحث عن رحلة أخرى في التطبيق.', ['parcel_id' => $parcel->id]);

        return $parcel;
    }

    /** Driver photographs the parcel when receiving it (spec 5.6). */
    public function pickUp(Parcel $parcel, UploadedFile $photo): Parcel
    {
        if ($parcel->status !== ParcelStatus::Accepted) {
            throw new BusinessRuleException('الارسالية ليست بانتظار الاستلام.', 'not_accepted');
        }

        $disk = config('filesystems.private_disk');
        $parcel->forceFill([
            'status' => ParcelStatus::PickedUp,
            'picked_up_at' => now(),
            'pickup_photo_disk' => $disk,
            'pickup_photo_path' => $photo->store('parcels/pickup/'.$parcel->id, ['disk' => $disk]),
        ])->save();

        $this->notifier->send($parcel->sender, NotificationEvent::ParcelPickedUp, 'استلم السائق الارسالية',
            'ارساليتك الآن مع السائق. يمكنك رؤية صورة الاستلام في التطبيق.', ['parcel_id' => $parcel->id]);

        return $parcel;
    }

    /** Contents don't match the description → driver refuses at pickup; commission reversed. */
    public function refuse(Parcel $parcel, string $reason): Parcel
    {
        if ($parcel->status !== ParcelStatus::Accepted) {
            throw new BusinessRuleException('الارسالية ليست بانتظار الاستلام.', 'not_accepted');
        }

        $parcel->forceFill(['status' => ParcelStatus::Refused, 'refusal_reason' => $reason])->save();
        $this->ledger->reverseParcel($parcel, 'رفض الارسالية عند الاستلام #'.$parcel->id);

        $this->notifier->send($parcel->sender, NotificationEvent::ParcelRefused, 'رفض السائق استلام الارسالية',
            "السبب: $reason", ['parcel_id' => $parcel->id]);

        return $parcel;
    }

    /** Receiver gives the 4-digit code; the driver enters it (spec 5.6). */
    public function deliver(Parcel $parcel, string $code): Parcel
    {
        if ($parcel->status !== ParcelStatus::PickedUp) {
            throw new BusinessRuleException('الارسالية ليست في الطريق.', 'not_picked_up');
        }

        $max = settings()->int('parcels.code_max_attempts');
        if ($parcel->code_attempts >= $max) {
            throw new BusinessRuleException('تجاوزت عدد المحاولات. تواصل مع الدعم لتأكيد التسليم.', 'code_locked');
        }

        if (! hash_equals((string) $parcel->delivery_code, trim($code))) {
            $parcel->increment('code_attempts');
            $left = $max - $parcel->code_attempts;
            throw new BusinessRuleException(
                $left > 0 ? "الرمز غير صحيح. تبقّت $left محاولة." : 'الرمز غير صحيح. تواصل مع الدعم لتأكيد التسليم.',
                $left > 0 ? 'wrong_code' : 'code_locked',
                ['attempts_left' => max(0, $left)],
            );
        }

        $parcel->forceFill(['status' => ParcelStatus::Delivered, 'delivered_at' => now()])->save();

        $this->notifier->send($parcel->sender, NotificationEvent::ParcelDelivered, 'تم تسليم الارسالية',
            "استلم {$parcel->receiver_name} الارسالية. قيّم السائق من التطبيق.", ['parcel_id' => $parcel->id]);

        return $parcel;
    }

    // ------------------------------------------------------------------ Trip / admin / timers

    /** The trip was cancelled: every open parcel is cancelled and refunded. */
    public function cancelForTrip(Trip $trip, string $reason): void
    {
        $trip->parcels()->whereIn('status', [ParcelStatus::Pending, ParcelStatus::Accepted, ParcelStatus::PickedUp])->with('sender')->get()
            ->each(function (Parcel $parcel) use ($reason) {
                $pickedUp = $parcel->status === ParcelStatus::PickedUp;
                $parcel->forceFill(['status' => ParcelStatus::Cancelled, 'cancelled_at' => now(), 'cancel_reason' => $reason])->save();
                $this->ledger->reverseParcel($parcel, 'إلغاء الرحلة — الارسالية #'.$parcel->id);

                $this->notifier->send($parcel->sender, NotificationEvent::ParcelCancelled, 'أُلغيت رحلة ارساليتك',
                    "السبب: $reason.".($pickedUp ? ' الارسالية مع السائق، تواصل معه لاستعادتها.' : ''), ['parcel_id' => $parcel->id]);
            });
    }

    /** @param string $resolution delivered | cancelled (cancelled reverses the commission) */
    public function resolveDispute(Parcel $parcel, string $resolution, Admin $admin, ?string $note = null): Parcel
    {
        if ($parcel->status !== ParcelStatus::Disputed) {
            throw new BusinessRuleException('لا يوجد نزاع مفتوح.', 'no_open_dispute');
        }

        $parcel->forceFill([
            'status' => $resolution === 'delivered' ? ParcelStatus::Delivered : ParcelStatus::Cancelled,
            'delivered_at' => $resolution === 'delivered' ? ($parcel->delivered_at ?? now()) : $parcel->delivered_at,
            'cancelled_at' => $resolution === 'cancelled' ? now() : null,
            'dispute_resolution' => $resolution,
            'dispute_resolved_by' => $admin->id,
        ])->save();

        if ($resolution === 'cancelled') {
            $this->ledger->reverseParcel($parcel, 'حُسم النزاع لصالح المرسل — الارسالية #'.$parcel->id);
        }

        $this->audit->log('parcel.dispute_'.$resolution, $parcel, array_filter(['note' => $note]), $admin);

        return $parcel;
    }

    public function expireOverdue(): int
    {
        $count = 0;
        Parcel::where('status', ParcelStatus::Pending)->where('expires_at', '<=', now())->with('sender')
            ->each(function (Parcel $parcel) use (&$count) {
                if (Parcel::whereKey($parcel->id)->where('status', ParcelStatus::Pending)->update(['status' => ParcelStatus::Expired])) {
                    $count++;
                    $this->notifier->send($parcel->sender, NotificationEvent::ParcelExpired, 'انتهت مهلة طلب الارسالية',
                        'لم يرد السائق في الوقت المحدد. ابحث عن رحلة أخرى.', ['parcel_id' => $parcel->id]);
                }
            });

        return $count;
    }

    public function remindDrivers(): int
    {
        $due = now()->subMinutes(settings()->int('booking.reminder_interval_minutes'));
        $count = 0;

        Parcel::where('status', ParcelStatus::Pending)->where('expires_at', '>', now())
            ->where(fn ($q) => $q->where('last_reminded_at', '<=', $due)
                ->orWhere(fn ($q) => $q->whereNull('last_reminded_at')->where('created_at', '<=', $due)))
            ->with('trip.driverProfile.user')
            ->each(function (Parcel $parcel) use (&$count) {
                $parcel->forceFill(['last_reminded_at' => now()])->save();
                $this->notifier->send($parcel->trip->driverProfile->user, NotificationEvent::BookingReminder, 'طلب ارسالية بانتظار ردك',
                    "ارسالية {$parcel->sizeLabel()}: {$parcel->description}.", ['parcel_id' => $parcel->id]);
                $count++;
            });

        return $count;
    }

    /** Text for the receiver (spec S4); also used for the sender's "share with receiver" button. */
    public function receiverMessage(Parcel $parcel): string
    {
        return sprintf(
            "لديك ارسالية من %s عبر مسافر معاك.\n%s\nرمز التسليم: %s\nلا تعطِ الرمز للسائق إلا بعد استلام الارسالية.",
            $parcel->sender->name, $this->receiverDetails($parcel), $parcel->delivery_code,
        );
    }

    /** Driver, car, arrival and payment lines of the receiver message (the "details" WhatsApp template variable). */
    private function receiverDetails(Parcel $parcel): string
    {
        $trip = $parcel->trip;
        $driverUser = $trip->driverProfile->user;
        $payment = $parcel->payer === 'sender' ? 'المبلغ مدفوع من المرسل' : 'ادفع للسائق '.number_format($parcel->price).' جنيه عند الاستلام';

        return sprintf(
            "السائق: %s — %s\nالعربية: %s %s، لوحة %s\nالوصول المتوقع: %s إلى %s\n%s.",
            $driverUser->name, $driverUser->phone, $trip->vehicle->make, $trip->vehicle->model, $trip->vehicle->plate_number,
            local_time($trip->est_arrival_at ?? $trip->departs_at)->format('Y-m-d'), $trip->route->destination->name_ar,
            $payment,
        );
    }

    /**
     * Send the delivery-code message to the receiver's WhatsApp (they don't need the app).
     * Called by the SendParcelCodeToReceiver job; throws on provider failure so the job can retry.
     */
    public function sendReceiverMessage(Parcel $parcel): void
    {
        $this->whatsapp->send($parcel->receiver_phone, 'parcel_delivery_code', [
            'sender' => $parcel->sender->name, 'details' => $this->receiverDetails($parcel),
            'code' => $parcel->delivery_code, 'parcel_id' => $parcel->id,
        ], $this->receiverMessage($parcel));

        $parcel->forceFill(['receiver_notified_at' => now()])->save();
    }

    private function notifyReceiver(Parcel $parcel): void
    {
        try {
            SendParcelCodeToReceiver::dispatch($parcel->id);
        } catch (Throwable $e) {
            // The sender still sees the code in the app and can share it (spec 5.6).
            Log::warning('Receiver WhatsApp failed', ['parcel' => $parcel->id, 'error' => $e->getMessage()]);
        }
    }

    private function assertPendingAndValid(Parcel $parcel): void
    {
        if ($parcel->status !== ParcelStatus::Pending) {
            throw new BusinessRuleException('تم الرد على هذا الطلب أو انتهت مهلته.', 'not_pending');
        }
        if ($parcel->expires_at?->isPast()) {
            throw new BusinessRuleException('انتهت مهلة هذا الطلب.', 'expired');
        }
    }
}
