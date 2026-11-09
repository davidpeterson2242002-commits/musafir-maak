<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\NotificationEvent;
use App\Enums\PointKind;
use App\Enums\TripStatus;
use App\Enums\VehicleStatus;
use App\Events\TripCancelled;
use App\Exceptions\BusinessRuleException;
use App\Models\Admin;
use App\Models\Booking;
use App\Models\City;
use App\Models\DriverProfile;
use App\Models\TravelRoute;
use App\Models\Trip;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** Trip posting and lifecycle on the driver side (spec 5.2, 5.3 step 5, 5.4, D7–D9). */
class TripService
{
    public function __construct(
        private Notifier $notifier,
        private RouteAlertService $alerts,
        private AuditLogger $audit,
    ) {}

    /**
     * @param  array  $data  validated input from PostTripRequest (departs_at already a UTC Carbon)
     */
    public function post(DriverProfile $driver, array $data): Trip
    {
        if ($driver->isFrozen()) {
            throw new BusinessRuleException('حسابك مجمّد لعدم سداد الرصيد المستحق. سدّد لتتمكن من نشر رحلات.', 'driver_frozen', ['balance_owed' => $driver->balance_owed]);
        }
        if (! $driver->isActive()) {
            throw new BusinessRuleException('حسابك كسائق غير نشط حالياً، لا يمكن نشر رحلات.', 'driver_not_active');
        }

        $vehicle = $driver->vehicles()->whereKey($data['vehicle_id'])->first();
        if ($vehicle === null || $vehicle->status !== VehicleStatus::Approved) {
            throw new BusinessRuleException('اختر عربية معتمدة.', 'vehicle_not_approved');
        }
        if ($data['seats_total'] > $vehicle->seats) {
            throw new BusinessRuleException("عدد المقاعد أكبر من سعة العربية ({$vehicle->seats}).", 'too_many_seats');
        }

        $route = $this->resolveRoute((int) $data['origin_city_id'], (int) $data['destination_city_id']);
        $this->assertPrice($route, (int) $data['price_per_seat']);
        $this->assertDepartureWindow($data['departs_at']);

        $trip = DB::transaction(function () use ($driver, $route, $data) {
            $trip = Trip::create([
                ...Arr::only($data, [
                    'vehicle_id', 'departs_at', 'est_arrival_at', 'price_per_seat', 'seats_total',
                    'luggage_large', 'luggage_small', 'extra_luggage_price', 'accepts_parcels',
                    'parcel_price_small', 'parcel_price_medium', 'parcel_price_large', 'max_parcels',
                    'women_only', 'notes',
                ]),
                'driver_profile_id' => $driver->id,
                'route_id' => $route->id,
                'seats_available' => $data['seats_total'],
                'status' => TripStatus::Scheduled,
            ]);

            $this->syncPoints($trip, $data['pickup_points'], $data['dropoff_points']);

            return $trip;
        });

        DB::afterCommit(fn () => $this->alerts->notifyFor($trip));

        return $trip;
    }

    /**
     * Before any booking: full edit. With active bookings: only notes and more seats (spec D9).
     */
    public function update(Trip $trip, array $data): Trip
    {
        $this->assertScheduled($trip);

        return DB::transaction(function () use ($trip, $data) {
            $trip = Trip::whereKey($trip->id)->lockForUpdate()->first();

            if ($trip->hasActiveBookings()) {
                $forbidden = array_diff(array_keys($data), ['notes', 'seats_total']);
                if ($forbidden !== []) {
                    throw new BusinessRuleException('الرحلة عليها حجوزات؛ يمكنك فقط زيادة المقاعد أو تعديل الملاحظات.', 'trip_has_bookings');
                }
                if (isset($data['seats_total']) && $data['seats_total'] < $trip->seats_total) {
                    throw new BusinessRuleException('لا يمكن تقليل المقاعد بعد وجود حجوزات.', 'trip_has_bookings');
                }
            }

            $vehicle = isset($data['vehicle_id'])
                ? $trip->driverProfile->vehicles()->whereKey($data['vehicle_id'])->where('status', VehicleStatus::Approved)->first()
                : $trip->vehicle;
            if ($vehicle === null) {
                throw new BusinessRuleException('اختر عربية معتمدة.', 'vehicle_not_approved');
            }
            if (($data['seats_total'] ?? $trip->seats_total) > $vehicle->seats) {
                throw new BusinessRuleException("عدد المقاعد أكبر من سعة العربية ({$vehicle->seats}).", 'too_many_seats');
            }
            if (isset($data['price_per_seat'])) {
                $this->assertPrice($trip->route, (int) $data['price_per_seat']);
            }
            if (isset($data['departs_at'])) {
                $this->assertDepartureWindow($data['departs_at']);
            }

            if (isset($data['seats_total'])) {
                $booked = $trip->seats_total - $trip->seats_available;
                $trip->seats_available = $data['seats_total'] - $booked;
            }

            $trip->fill(Arr::except($data, ['pickup_points', 'dropoff_points', 'origin_city_id', 'destination_city_id']))->save();

            if (isset($data['pickup_points']) || isset($data['dropoff_points'])) {
                $this->syncPoints(
                    $trip,
                    $data['pickup_points'] ?? $trip->pickupPoints->map->only(['label', 'lat', 'lng'])->all(),
                    $data['dropoff_points'] ?? $trip->dropoffPoints->map->only(['label', 'lat', 'lng'])->all(),
                );
            }

            return $trip->fresh();
        });
    }

    /** Cancel a scheduled trip: every pending/accepted booking is cancelled and notified; fees reversed (M4). */
    public function cancel(Trip $trip, string $reason, ?Admin $admin = null): Trip
    {
        $this->assertScheduled($trip);

        [$trip, $bookingIds] = DB::transaction(function () use ($trip, $reason, $admin) {
            $trip = Trip::whereKey($trip->id)->lockForUpdate()->first();
            $this->assertScheduled($trip);

            $bookings = $trip->bookings()->whereIn('status', BookingStatus::active())->with('passenger')->get();

            foreach ($bookings as $booking) {
                $booking->forceFill([
                    'status' => BookingStatus::CancelledByDriver,
                    'cancelled_at' => now(),
                    'cancel_reason' => $reason,
                ])->save();

                $this->notifier->send(
                    $booking->passenger,
                    NotificationEvent::TripCancelled,
                    'تم إلغاء رحلتك',
                    sprintf('ألغى السائق رحلة %s يوم %s. السبب: %s. ابحث عن رحلة بديلة في التطبيق.',
                        $trip->route->label(), local_time($trip->departs_at)->format('Y-m-d H:i'), $reason),
                    ['booking_id' => $booking->id, 'trip_id' => $trip->id],
                );
            }

            $trip->forceFill([
                'status' => TripStatus::Cancelled,
                'cancelled_at' => now(),
                'cancel_reason' => $reason,
                'seats_available' => $trip->seats_total,
            ])->save();

            if ($admin) {
                $this->audit->log('trip.cancelled', $trip, ['reason' => $reason, 'bookings' => $bookings->count()], $admin);
            }

            return [$trip, $bookings->pluck('id')->all()];
        });

        DB::afterCommit(fn () => TripCancelled::dispatch($trip, $bookingIds));

        return $trip;
    }

    /** "انطلقت" — allowed from a little before departure (setting trips.depart_early_minutes). */
    public function depart(Trip $trip): Trip
    {
        $this->assertScheduled($trip);

        $earliest = $trip->departs_at->copy()->subMinutes(settings()->int('trips.depart_early_minutes'));
        if (now()->lt($earliest)) {
            throw new BusinessRuleException('لا يمكن تسجيل الانطلاق قبل موعد الرحلة.', 'too_early');
        }

        return DB::transaction(function () use ($trip) {
            $trip = Trip::whereKey($trip->id)->lockForUpdate()->first();
            $trip->forceFill(['status' => TripStatus::Departed, 'departed_at' => now()])->save();

            // Requests the driver never answered can't be accepted any more.
            app(BookingService::class)->expirePendingForTrip($trip);

            return $trip;
        });
    }

    /** "وصلت" — completes the trip and its accepted bookings. */
    public function complete(Trip $trip): Trip
    {
        if ($trip->status !== TripStatus::Departed) {
            throw new BusinessRuleException('سجّل الانطلاق أولاً.', 'not_departed');
        }

        return DB::transaction(function () use ($trip) {
            $trip = Trip::whereKey($trip->id)->lockForUpdate()->first();
            $trip->forceFill(['status' => TripStatus::Completed, 'completed_at' => now()])->save();

            $trip->bookings()->where('status', BookingStatus::Accepted)
                ->update(['status' => BookingStatus::Completed, 'completed_at' => now()]);

            // Rating prompts arrive with ratings in M5.

            return $trip;
        });
    }

    /** Admin: hide a trip from search (existing bookings stay). */
    public function setHidden(Trip $trip, bool $hidden, Admin $admin): void
    {
        $trip->forceFill(['hidden_by_admin' => $hidden])->save();
        $this->audit->log($hidden ? 'trip.hidden' : 'trip.unhidden', $trip, [], $admin);
    }

    /**
     * Route for a city pair. Created on the fly between two enabled cities when the admin
     * allows it (setting routes.auto_create); a disabled route blocks posting.
     */
    public function resolveRoute(int $originId, int $destinationId): TravelRoute
    {
        if ($originId === $destinationId) {
            throw new BusinessRuleException('مدينة الانطلاق والوصول لا يمكن أن تكونا نفس المدينة.', 'same_city');
        }

        $route = TravelRoute::between($originId, $destinationId)->with(['origin.state', 'destination.state'])->first();

        if ($route === null) {
            $origin = City::with('state')->find($originId);
            $destination = City::with('state')->find($destinationId);

            if (! $origin?->isAvailable() || ! $destination?->isAvailable()) {
                throw new BusinessRuleException('إحدى المدينتين غير متاحة حالياً.', 'city_unavailable');
            }
            if (! settings()->bool('routes.auto_create')) {
                throw new BusinessRuleException('هذا الطريق غير متاح حالياً في التطبيق.', 'route_unavailable');
            }

            $route = TravelRoute::create(['origin_city_id' => $originId, 'destination_city_id' => $destinationId, 'enabled' => true]);
            $route->setRelation('origin', $origin)->setRelation('destination', $destination);
        }

        if (! $route->isOpen()) {
            throw new BusinessRuleException($route->closure_message ?: 'هذا الطريق موقوف مؤقتاً', 'route_closed');
        }

        return $route;
    }

    private function assertPrice(TravelRoute $route, int $price): void
    {
        if ($route->max_price_per_seat !== null && $price > $route->max_price_per_seat) {
            throw new BusinessRuleException("أقصى سعر للمقعد في هذا الطريق {$route->max_price_per_seat} جنيه.", 'price_above_max', ['max_price_per_seat' => $route->max_price_per_seat]);
        }
    }

    private function assertDepartureWindow(Carbon $departsAt): void
    {
        if ($departsAt->lt(now()->addMinutes(settings()->int('trips.min_minutes_ahead')))) {
            throw new BusinessRuleException('موعد الانطلاق قريب جداً أو في الماضي.', 'departure_too_soon');
        }
        if ($departsAt->gt(now()->addDays(settings()->int('trips.max_days_ahead')))) {
            throw new BusinessRuleException('لا يمكن نشر رحلة بعد أكثر من '.settings()->int('trips.max_days_ahead').' يوماً.', 'departure_too_far');
        }
    }

    private function assertScheduled(Trip $trip): void
    {
        if ($trip->status !== TripStatus::Scheduled) {
            throw new BusinessRuleException('الرحلة '.$trip->status->label().' ولا يمكن تعديلها.', 'trip_not_scheduled');
        }
    }

    private function syncPoints(Trip $trip, array $pickups, array $dropoffs): void
    {
        // Points referenced by bookings are kept (matched by label) so bookings keep their pickup point.
        $existing = $trip->points()->get();
        $keep = [];

        foreach ([PointKind::Pickup->value => $pickups, PointKind::Dropoff->value => $dropoffs] as $kind => $points) {
            foreach (array_values($points) as $i => $point) {
                $match = $existing->first(fn ($p) => $p->kind->value === $kind && $p->label === $point['label'] && ! in_array($p->id, $keep, true));
                $attrs = ['kind' => $kind, 'label' => $point['label'], 'lat' => $point['lat'] ?? null, 'lng' => $point['lng'] ?? null, 'sort' => $i];
                if ($match) {
                    $match->update($attrs);
                } else {
                    $match = $trip->points()->create($attrs);
                }
                $keep[] = $match->id;
            }
        }

        $trip->points()->whereNotIn('id', $keep)
            ->whereNotIn('id', Booking::where('trip_id', $trip->id)->whereNotNull('pickup_point_id')->pluck('pickup_point_id'))
            ->delete();
        $trip->unsetRelation('points');
    }
}
