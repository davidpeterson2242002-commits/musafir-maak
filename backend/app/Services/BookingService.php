<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\Gender;
use App\Enums\IdVerificationStatus;
use App\Enums\NotificationEvent;
use App\Enums\PointKind;
use App\Enums\TripStatus;
use App\Events\BookingAccepted;
use App\Events\BookingCancelled;
use App\Events\NoShowConfirmed;
use App\Exceptions\BusinessRuleException;
use App\Models\Admin;
use App\Models\Booking;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Seat bookings (spec 5.3, 5.4, 5.7, 5.12, 5.13). */
class BookingService
{
    public function __construct(
        private Notifier $notifier,
        private AuditLogger $audit,
        private LedgerService $ledger,
    ) {}

    // ------------------------------------------------------------------ Passenger

    /**
     * @param  array{seats:int, pickup_point_id?:int|null, extra_bags?:int, includes_family?:bool, message?:string|null}  $data
     */
    public function request(User $passenger, Trip $trip, array $data): Booking
    {
        $trip->loadMissing(['driverProfile.user', 'route.origin', 'route.destination', 'points']);

        if ($trip->driverProfile->user_id === $passenger->id) {
            throw new BusinessRuleException('لا يمكنك الحجز في رحلتك.', 'own_trip');
        }
        $this->assertCanBook($passenger);
        if ($passenger->isBlockedWith($trip->driverProfile->user_id)) {
            throw new BusinessRuleException('لا يمكنك الحجز مع هذا السائق.', 'blocked');
        }
        if (! $trip->isBookable()) {
            throw new BusinessRuleException('هذه الرحلة لم تعد متاحة للحجز.', 'trip_unavailable');
        }

        $seats = (int) $data['seats'];
        if ($seats > $trip->seats_available) {
            throw new BusinessRuleException("المقاعد المتاحة {$trip->seats_available} فقط.", 'not_enough_seats', ['seats_available' => $trip->seats_available]);
        }

        $includesFamily = (bool) ($data['includes_family'] ?? false);
        if ($trip->women_only && $passenger->gender !== Gender::Female && ! $includesFamily) {
            throw new BusinessRuleException('هذه الرحلة للنساء والعائلات فقط.', 'women_only');
        }

        $pickupId = $data['pickup_point_id'] ?? null;
        $pickups = $trip->points->where('kind', PointKind::Pickup);
        if ($pickupId === null && $pickups->count() === 1) {
            $pickupId = $pickups->first()->id;
        }
        if ($pickupId === null || ! $pickups->contains('id', $pickupId)) {
            throw new BusinessRuleException('اختر نقطة التحرك من نقاط السائق.', 'invalid_pickup_point');
        }

        $extraBags = (int) ($data['extra_bags'] ?? 0);
        if ($extraBags > 0 && $trip->extra_luggage_price === null) {
            throw new BusinessRuleException('السائق لا يقبل شنط إضافية في هذه الرحلة.', 'no_extra_luggage');
        }

        $booking = DB::transaction(function () use ($passenger, $trip, $seats, $pickupId, $extraBags, $includesFamily, $data) {
            // One active booking per passenger per trip (lock the passenger row to avoid double taps).
            User::whereKey($passenger->id)->lockForUpdate()->first();
            if ($trip->bookings()->where('passenger_id', $passenger->id)->whereIn('status', BookingStatus::active())->exists()) {
                throw new BusinessRuleException('عندك طلب حجز قائم في هذه الرحلة.', 'already_booked');
            }

            $timeout = now()->addHours(settings()->int('booking.response_timeout_hours'));

            $booking = $trip->bookings()->create([
                'passenger_id' => $passenger->id,
                'seats' => $seats,
                'pickup_point_id' => $pickupId,
                'extra_bags' => $extraBags,
                'includes_family' => $includesFamily,
                'message' => $data['message'] ?? null,
                'status' => BookingStatus::Pending,
                'price_total' => $seats * $trip->price_per_seat + $extraBags * (int) $trip->extra_luggage_price,
                'expires_at' => $timeout->min($trip->departs_at),
            ]);

            app(ChatService::class)->openFor($booking);

            // A first booking while the ID is under review jumps the verification queue (spec 9.2).
            if ($passenger->id_verification_status === IdVerificationStatus::Pending) {
                $passenger->verificationReviews()->pending()->update(['priority' => 10]);
            }

            return $booking;
        });

        $this->notifier->send(
            $trip->driverProfile->user,
            NotificationEvent::BookingRequested,
            'طلب حجز جديد',
            sprintf('%s يطلب %d %s في رحلة %s (%s). عندك حتى %s للرد.',
                $passenger->name, $seats, $seats === 1 ? 'مقعد' : 'مقاعد', $trip->route->label(),
                local_time($trip->departs_at)->format('Y-m-d H:i'), local_time($booking->expires_at)->format('H:i')),
            ['booking_id' => $booking->id, 'trip_id' => $trip->id],
        );

        return $booking;
    }

    /**
     * Passenger cancels before departure. Late cancellations count towards a temporary
     * booking restriction (settings cancellation.*).
     *
     * @return array{booking: Booking, warning: string|null, restricted_until: string|null}
     */
    public function cancelByPassenger(Booking $booking, ?string $reason = null): array
    {
        $booking->loadMissing(['trip.driverProfile.user', 'trip.route.origin', 'trip.route.destination', 'passenger']);
        $trip = $booking->trip;

        if (! $booking->status->isActive()) {
            throw new BusinessRuleException('لا يمكن إلغاء هذا الحجز.', 'not_cancellable');
        }
        if ($trip->status !== TripStatus::Scheduled || $trip->departs_at->isPast()) {
            throw new BusinessRuleException('لا يمكن الإلغاء بعد موعد الانطلاق. تواصل مع السائق.', 'trip_departed');
        }

        $wasAccepted = $booking->status === BookingStatus::Accepted;
        $late = $wasAccepted && now()->diffInHours($trip->departs_at, false) < settings()->int('cancellation.late_window_hours');

        $result = DB::transaction(function () use ($booking, $trip, $reason, $wasAccepted, $late) {
            $booking = Booking::whereKey($booking->id)->lockForUpdate()->first();
            if (! $booking->status->isActive()) {
                throw new BusinessRuleException('لا يمكن إلغاء هذا الحجز.', 'not_cancellable');
            }

            if ($wasAccepted) {
                Trip::whereKey($trip->id)->increment('seats_available', $booking->seats);
            }

            $booking->forceFill([
                'status' => BookingStatus::CancelledByPassenger,
                'cancelled_at' => now(),
                'cancel_reason' => $reason,
                'late_cancellation' => $late,
            ])->save();

            return ['booking' => $booking, ...$this->applyLateCancellationPolicy($booking->passenger()->first(), $late)];
        });

        if ($wasAccepted) {
            DB::afterCommit(fn () => BookingCancelled::dispatch($result['booking']));
        }

        $this->notifier->send(
            $trip->driverProfile->user,
            NotificationEvent::BookingCancelledByPassenger,
            'ألغى راكب حجزه',
            sprintf('%s ألغى حجز %d %s في رحلة %s.', $booking->passenger->name, $booking->seats, $booking->seats === 1 ? 'مقعد' : 'مقاعد', $trip->route->label()),
            ['booking_id' => $booking->id, 'trip_id' => $trip->id],
        );

        return $result;
    }

    /** Passenger disputes a no-show within the window (setting booking.no_show_dispute_hours). */
    public function disputeNoShow(Booking $booking, string $details): Booking
    {
        if ($booking->status !== BookingStatus::NoShow || $booking->no_show_confirmed_at !== null) {
            throw new BusinessRuleException('لا يمكن الاعتراض على هذا الحجز.', 'not_disputable');
        }
        if ($booking->no_show_disputed_at !== null) {
            throw new BusinessRuleException('سبق أن اعترضت، وسيراجع فريقنا الاعتراض.', 'already_disputed');
        }
        if ($booking->no_show_at->lt(now()->subHours(settings()->int('booking.no_show_dispute_hours')))) {
            throw new BusinessRuleException('انتهت مهلة الاعتراض.', 'dispute_window_closed');
        }

        $booking->forceFill(['no_show_disputed_at' => now(), 'no_show_dispute_details' => $details])->save();

        return $booking;
    }

    // ------------------------------------------------------------------ Driver

    public function accept(Booking $booking): Booking
    {
        $booking->loadMissing(['trip.driverProfile', 'trip.route.origin', 'trip.route.destination', 'passenger']);
        $this->assertDriverCanRespond($booking);

        $booking = DB::transaction(function () use ($booking) {
            $trip = Trip::whereKey($booking->trip_id)->lockForUpdate()->first();
            $booking = Booking::whereKey($booking->id)->lockForUpdate()->first();

            if ($booking->status !== BookingStatus::Pending) {
                throw new BusinessRuleException('تم الرد على هذا الطلب أو انتهت مهلته.', 'not_pending');
            }
            if ($booking->seats > $trip->seats_available) {
                throw new BusinessRuleException("المقاعد المتاحة {$trip->seats_available} فقط.", 'not_enough_seats', ['seats_available' => $trip->seats_available]);
            }

            $trip->decrement('seats_available', $booking->seats);
            $booking->forceFill([
                'status' => BookingStatus::Accepted,
                'accepted_at' => now(),
                'responded_at' => now(),
            ])->save();

            // Commission snapshot (free booking, wallet first, then balance). Throws → rollback.
            $this->ledger->chargeBooking($booking, $trip);

            return $booking;
        });

        DB::afterCommit(fn () => BookingAccepted::dispatch($booking));

        $trip = $booking->trip()->with(['route.origin', 'route.destination', 'driverProfile.user', 'vehicle'])->first();
        $this->notifier->send(
            $booking->passenger()->first(),
            NotificationEvent::BookingAccepted,
            'تم قبول حجزك',
            sprintf('رحلة %s يوم %s. السائق: %s، الهاتف %s، العربية %s %s (%s). افتح التطبيق لتفاصيل نقطة التحرك.',
                $trip->route->label(), local_time($trip->departs_at)->format('Y-m-d H:i'), $trip->driverProfile->user->name,
                $trip->driverProfile->user->phone, $trip->vehicle->make, $trip->vehicle->model, $trip->vehicle->plate_number),
            ['booking_id' => $booking->id, 'trip_id' => $trip->id],
        );

        return $booking;
    }

    public function reject(Booking $booking, ?string $reason = null): Booking
    {
        $booking->loadMissing(['trip.driverProfile', 'trip.route.origin', 'trip.route.destination']);
        $this->assertDriverCanRespond($booking, requireActive: false);

        $booking = DB::transaction(function () use ($booking, $reason) {
            $booking = Booking::whereKey($booking->id)->lockForUpdate()->first();
            if ($booking->status !== BookingStatus::Pending) {
                throw new BusinessRuleException('تم الرد على هذا الطلب أو انتهت مهلته.', 'not_pending');
            }
            $booking->forceFill(['status' => BookingStatus::Rejected, 'responded_at' => now(), 'rejection_reason' => $reason])->save();

            return $booking;
        });

        $this->notifier->send(
            $booking->passenger()->first(),
            NotificationEvent::BookingRejected,
            'لم يتم قبول طلب الحجز',
            'اعتذر السائق عن طلبك. افتح التطبيق لرؤية رحلات أخرى في نفس اليوم.',
            ['booking_id' => $booking->id],
        );

        return $booking;
    }

    /** Driver marks an accepted passenger as not showing up (after departure time). */
    public function markNoShow(Booking $booking): Booking
    {
        $booking->loadMissing(['trip', 'passenger']);

        if ($booking->status !== BookingStatus::Accepted) {
            throw new BusinessRuleException('يمكن تسجيل عدم الحضور للحجوزات المقبولة فقط.', 'not_accepted');
        }
        if ($booking->trip->departs_at->isFuture() && $booking->trip->status === TripStatus::Scheduled) {
            throw new BusinessRuleException('يمكن تسجيل عدم الحضور بعد موعد الانطلاق.', 'too_early');
        }
        if ($booking->trip->status === TripStatus::Cancelled) {
            throw new BusinessRuleException('الرحلة ملغية.', 'trip_cancelled');
        }

        $booking->forceFill(['status' => BookingStatus::NoShow, 'no_show_at' => now()])->save();

        $hours = settings()->int('booking.no_show_dispute_hours');
        $this->notifier->send(
            $booking->passenger,
            NotificationEvent::NoShowMarked,
            'سجّل السائق أنك لم تحضر',
            "إذا كان هذا غير صحيح، اعترض من التطبيق خلال $hours ساعة.",
            ['booking_id' => $booking->id],
        );

        return $booking;
    }

    // ------------------------------------------------------------------ Admin

    /** Resolve a disputed no-show: confirmed (fee reversed) or overturned (booking counts as completed). */
    public function resolveNoShow(Booking $booking, bool $confirm, Admin $admin, ?string $note = null): Booking
    {
        if ($booking->status !== BookingStatus::NoShow || $booking->no_show_resolution !== null) {
            throw new BusinessRuleException('لا يوجد اعتراض مفتوح على هذا الحجز.', 'no_open_dispute');
        }

        DB::transaction(function () use ($booking, $confirm, $admin, $note) {
            $booking->forceFill($confirm
                ? ['no_show_resolution' => 'confirmed', 'no_show_confirmed_at' => now(), 'no_show_resolved_by' => $admin->id]
                : ['no_show_resolution' => 'overturned', 'status' => BookingStatus::Completed, 'completed_at' => now(), 'no_show_resolved_by' => $admin->id]
            )->save();

            $this->audit->log($confirm ? 'no_show.confirmed' : 'no_show.overturned', $booking, array_filter(['note' => $note]), $admin);
        });

        if ($confirm) {
            DB::afterCommit(fn () => NoShowConfirmed::dispatch($booking));
        }

        return $booking;
    }

    // ------------------------------------------------------------------ Timers (scheduler)

    /** Pending requests past their deadline → expired; passenger notified. Idempotent. */
    public function expireOverdue(): int
    {
        $count = 0;

        Booking::where('status', BookingStatus::Pending)->where('expires_at', '<=', now())
            ->with('passenger')->chunkById(200, function ($bookings) use (&$count) {
                foreach ($bookings as $booking) {
                    $updated = Booking::whereKey($booking->id)->where('status', BookingStatus::Pending)
                        ->update(['status' => BookingStatus::Expired, 'updated_at' => now()]);
                    if ($updated) {
                        $count++;
                        $this->notifyExpired($booking);
                    }
                }
            });

        return $count;
    }

    /** Called when the driver marks departure: unanswered requests expire immediately. */
    public function expirePendingForTrip(Trip $trip): void
    {
        $trip->bookings()->where('status', BookingStatus::Pending)->with('passenger')->get()
            ->each(function (Booking $booking) {
                $booking->forceFill(['status' => BookingStatus::Expired])->save();
                $this->notifyExpired($booking);
            });
    }

    /** Remind drivers about pending requests every N minutes (setting booking.reminder_interval_minutes). */
    public function remindDrivers(): int
    {
        $interval = settings()->int('booking.reminder_interval_minutes');
        $due = now()->subMinutes($interval);
        $count = 0;

        Booking::where('status', BookingStatus::Pending)
            ->where('expires_at', '>', now())
            ->where(fn ($q) => $q->where('last_reminded_at', '<=', $due)
                ->orWhere(fn ($q) => $q->whereNull('last_reminded_at')->where('created_at', '<=', $due)))
            ->with(['trip.driverProfile.user', 'trip.route.origin', 'trip.route.destination', 'passenger'])
            ->chunkById(200, function ($bookings) use (&$count) {
                foreach ($bookings as $booking) {
                    $booking->forceFill(['last_reminded_at' => now()])->save();
                    $left = (int) ceil(now()->diffInMinutes($booking->expires_at) / 60);
                    $this->notifier->send(
                        $booking->trip->driverProfile->user,
                        NotificationEvent::BookingReminder,
                        'طلب حجز بانتظار ردك',
                        "{$booking->passenger->name} ينتظر ردك على رحلة {$booking->trip->route->label()}. يتبقى حوالي $left ساعة قبل انتهاء الطلب.",
                        ['booking_id' => $booking->id],
                    );
                    $count++;
                }
            });

        return $count;
    }

    /** No-shows not disputed within the window are confirmed (fee reversal in M4). */
    public function confirmUndisputedNoShows(): int
    {
        $count = 0;
        Booking::where('status', BookingStatus::NoShow)
            ->whereNull('no_show_disputed_at')->whereNull('no_show_confirmed_at')
            ->where('no_show_at', '<=', now()->subHours(settings()->int('booking.no_show_dispute_hours')))
            ->each(function (Booking $booking) use (&$count) {
                $booking->forceFill(['no_show_confirmed_at' => now(), 'no_show_resolution' => 'confirmed'])->save();
                NoShowConfirmed::dispatch($booking);
                $count++;
            });

        return $count;
    }

    // ------------------------------------------------------------------ Internals

    public function assertCanBook(User $passenger): void
    {
        if (! $passenger->profileCompleted()) {
            throw new BusinessRuleException('أكمل ملفك الشخصي أولاً.', 'profile_incomplete');
        }
        if ($passenger->isSuspended()) {
            throw new BusinessRuleException('حسابك موقوف مؤقتاً.', 'account_suspended');
        }
        if ($passenger->isBookingRestricted()) {
            throw new BusinessRuleException(
                'تم إيقاف الحجز مؤقتاً بسبب تكرار الإلغاء المتأخر، حتى '.local_time($passenger->booking_restricted_until)->format('Y-m-d').'.',
                'booking_restricted',
                ['restricted_until' => local_iso($passenger->booking_restricted_until)],
            );
        }
        if (! $passenger->idAllowsBooking()) {
            throw new BusinessRuleException(match ($passenger->id_verification_status) {
                IdVerificationStatus::Pending => 'هويتك قيد المراجعة. يمكنك الحجز بعد توثيقها.',
                IdVerificationStatus::Rejected => 'لم يتم قبول هويتك. أعد رفع المستندات للحجز.',
                default => 'وثّق هويتك قبل أول حجز.',
            }, 'id_verification_required', ['id_verification_status' => $passenger->id_verification_status->value]);
        }
    }

    private function assertDriverCanRespond(Booking $booking, bool $requireActive = true): void
    {
        $driver = $booking->trip->driverProfile;
        if ($requireActive && $driver->isFrozen()) {
            throw new BusinessRuleException('حسابك مجمّد لعدم سداد الرصيد المستحق. سدّد لتتمكن من قبول الحجوزات.', 'driver_frozen', ['balance_owed' => $driver->balance_owed]);
        }
        if ($requireActive && (! $driver->isActive() || $driver->user->isSuspended())) {
            throw new BusinessRuleException('حسابك كسائق غير نشط حالياً، لا يمكنك قبول حجوزات.', 'driver_not_active');
        }
        if ($booking->status !== BookingStatus::Pending) {
            throw new BusinessRuleException('تم الرد على هذا الطلب أو انتهت مهلته.', 'not_pending');
        }
        if ($booking->expires_at !== null && $booking->expires_at->isPast()) {
            throw new BusinessRuleException('انتهت مهلة هذا الطلب.', 'expired');
        }
    }

    /** @return array{warning: string|null, restricted_until: string|null} */
    private function applyLateCancellationPolicy(User $passenger, bool $late): array
    {
        if (! $late) {
            return ['warning' => null, 'restricted_until' => null];
        }

        $limit = settings()->int('cancellation.passenger_limit');
        $count = Booking::where('passenger_id', $passenger->id)
            ->where('late_cancellation', true)
            ->where('cancelled_at', '>=', now()->subDays(settings()->int('cancellation.passenger_window_days')))
            ->count();

        if ($count >= $limit) {
            $until = now()->addDays(settings()->int('cancellation.passenger_restriction_days'));
            $passenger->forceFill(['booking_restricted_until' => $until])->save();
            $msg = 'تم إيقاف الحجز مؤقتاً حتى '.local_time($until)->format('Y-m-d').' بسبب تكرار الإلغاء المتأخر.';
            $this->notifier->send($passenger, NotificationEvent::AccountWarning, 'إيقاف مؤقت للحجز', $msg);

            return ['warning' => $msg, 'restricted_until' => local_iso($until)];
        }

        if ($count === $limit - 1) {
            $msg = 'تنبيه: إلغاء متأخر آخر سيؤدي لإيقاف الحجز مؤقتاً.';
            $this->notifier->send($passenger, NotificationEvent::AccountWarning, 'تنبيه بخصوص الإلغاء', $msg);

            return ['warning' => $msg, 'restricted_until' => null];
        }

        return ['warning' => null, 'restricted_until' => null];
    }

    private function notifyExpired(Booking $booking): void
    {
        $this->notifier->send(
            $booking->passenger,
            NotificationEvent::BookingExpired,
            'انتهت مهلة طلب الحجز',
            'لم يرد السائق في الوقت المحدد. افتح التطبيق لرؤية رحلات أخرى في نفس اليوم.',
            ['booking_id' => $booking->id],
        );
    }
}
