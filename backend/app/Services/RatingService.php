<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\NotificationEvent;
use App\Enums\ParcelStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Admin;
use App\Models\Booking;
use App\Models\DriverProfile;
use App\Models\Parcel;
use App\Models\Rating;
use App\Models\Trip;
use App\Models\User;
use App\Support\RatingTags;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Two-way ratings after a completed trip or delivered parcel (spec 5.9). */
class RatingService
{
    public function __construct(
        private Notifier $notifier,
        private AuditLogger $audit,
    ) {}

    /** @param array<int, string> $tags */
    public function rate(User $rater, Booking|Parcel $subject, int $stars, ?string $comment = null, array $tags = []): Rating
    {
        [$customerId, $driverUserId] = $this->parties($subject);

        $raterIsDriver = $rater->id === $driverUserId;
        if (! $raterIsDriver && $rater->id !== $customerId) {
            abort(404);
        }

        $completedAt = match (true) {
            $subject instanceof Booking && $subject->status === BookingStatus::Completed => $subject->completed_at,
            // A no-show passenger didn't travel; only the driver can rate.
            $subject instanceof Booking && $subject->status === BookingStatus::NoShow && $raterIsDriver => $subject->no_show_at,
            $subject instanceof Parcel && $subject->status === ParcelStatus::Delivered => $subject->delivered_at,
            default => null,
        };

        if ($completedAt === null) {
            throw new BusinessRuleException('يمكن التقييم بعد اكتمال الرحلة أو تسليم الارسالية.', 'not_ratable');
        }
        if ($completedAt->lt(now()->subDays(settings()->int('ratings.window_days')))) {
            throw new BusinessRuleException('انتهت مهلة التقييم.', 'rating_window_closed');
        }
        if ($stars < 1 || $stars > 5) {
            throw new BusinessRuleException('التقييم من 1 إلى 5 نجوم.', 'invalid_stars');
        }

        $allowedTags = array_keys($raterIsDriver ? RatingTags::FOR_PASSENGER : RatingTags::FOR_DRIVER);
        $tags = array_values(array_intersect($tags, $allowedTags));

        $rating = DB::transaction(function () use ($rater, $subject, $stars, $comment, $tags, $raterIsDriver, $customerId, $driverUserId) {
            if (Rating::where('rater_id', $rater->id)->where('subject_type', $subject->getMorphClass())->where('subject_id', $subject->id)->exists()) {
                throw new BusinessRuleException('قيّمت هذه الرحلة مسبقاً.', 'already_rated');
            }

            $rating = Rating::create([
                'rater_id' => $rater->id,
                'ratee_id' => $raterIsDriver ? $customerId : $driverUserId,
                'subject_type' => $subject->getMorphClass(),
                'subject_id' => $subject->id,
                'role' => $raterIsDriver ? Rating::AS_PASSENGER : Rating::AS_DRIVER,
                'stars' => $stars,
                'comment' => filled($comment) ? $comment : null,
                'tags' => $tags ?: null,
            ]);

            $this->recalculate($rating->ratee_id, $rating->role);

            return $rating;
        });

        return $rating;
    }

    /** Recompute the average and apply the low-rating review flag (spec 5.9). */
    public function recalculate(int $userId, string $role): void
    {
        $stats = Rating::where('ratee_id', $userId)->where('role', $role)->where('hidden', false)
            ->selectRaw('count(*) as c, coalesce(avg(stars), 0) as a')->first();
        $count = (int) $stats->c;
        $avg = round((float) $stats->a, 2);

        if ($role === Rating::AS_PASSENGER) {
            User::whereKey($userId)->update(['rating_avg' => $avg, 'rating_count' => $count]);

            return;
        }

        $driver = DriverProfile::where('user_id', $userId)->first();
        if (! $driver) {
            return;
        }

        $low = $count >= settings()->int('ratings.review_min_trips') && $avg < settings()->float('ratings.review_threshold');
        $driver->forceFill([
            'rating_avg' => $avg,
            'rating_count' => $count,
            'low_rating_flagged_at' => $low ? ($driver->low_rating_flagged_at ?? now()) : null,
        ])->save();
    }

    public function setHidden(Rating $rating, bool $hidden, Admin $admin): void
    {
        $rating->forceFill(['hidden' => $hidden])->save();
        $this->recalculate($rating->ratee_id, $rating->role);
        $this->audit->log($hidden ? 'rating.hidden' : 'rating.unhidden', $rating, [], $admin);
    }

    /** Push "rate your trip" to the driver and the passengers who travelled. */
    public function promptAfterTrip(Trip $trip): void
    {
        $trip->loadMissing(['driverProfile.user', 'route.origin', 'route.destination']);
        $bookings = $trip->bookings()->where('status', BookingStatus::Completed)->with('passenger')->get();

        foreach ($bookings as $booking) {
            $this->notifier->send($booking->passenger, NotificationEvent::RateTrip, 'كيف كانت رحلتك؟',
                'قيّم السائق في رحلة '.$trip->route->label().'.', ['booking_id' => $booking->id]);
        }
        if ($bookings->isNotEmpty()) {
            $this->notifier->send($trip->driverProfile->user, NotificationEvent::RateTrip, 'قيّم ركابك',
                'تقييمك يساعد السائقين الآخرين.', ['trip_id' => $trip->id]);
        }
    }

    /**
     * Bookings and parcels the user can still rate. @return Collection<int, Booking|Parcel>
     */
    public function pendingFor(User $user): Collection
    {
        $since = now()->subDays(settings()->int('ratings.window_days'));
        $rated = fn (string $type) => Rating::where('rater_id', $user->id)->where('subject_type', $type)->select('subject_id');
        $driverId = $user->driverProfile()->value('id');
        $with = ['trip.route.origin', 'trip.route.destination', 'trip.driverProfile.user'];

        $asPassenger = Booking::where('passenger_id', $user->id)->where('status', BookingStatus::Completed)
            ->where('completed_at', '>=', $since)->whereNotIn('id', $rated('booking'))->with([...$with, 'passenger'])->get();

        $asSender = Parcel::where('sender_id', $user->id)->where('status', ParcelStatus::Delivered)
            ->where('delivered_at', '>=', $since)->whereNotIn('id', $rated('parcel'))->with([...$with, 'sender'])->get();

        $asDriver = collect();
        if ($driverId) {
            $tripIds = Trip::where('driver_profile_id', $driverId)->select('id');
            $asDriver = Booking::whereIn('trip_id', $tripIds)
                ->where(fn ($q) => $q->where(fn ($q) => $q->where('status', BookingStatus::Completed)->where('completed_at', '>=', $since))
                    ->orWhere(fn ($q) => $q->where('status', BookingStatus::NoShow)->where('no_show_at', '>=', $since)))
                ->whereNotIn('id', $rated('booking'))->with([...$with, 'passenger'])->get()
                ->concat(Parcel::whereIn('trip_id', $tripIds)->where('status', ParcelStatus::Delivered)
                    ->where('delivered_at', '>=', $since)->whereNotIn('id', $rated('parcel'))->with([...$with, 'sender'])->get());
        }

        return $asPassenger->concat($asSender)->concat($asDriver)->values();
    }

    /** @return array{0: int, 1: int} [customer user id, driver user id] */
    public function parties(Booking|Parcel $subject): array
    {
        $subject->loadMissing('trip.driverProfile');

        return [
            $subject instanceof Booking ? $subject->passenger_id : $subject->sender_id,
            $subject->trip->driverProfile->user_id,
        ];
    }
}
