<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\NotificationEvent;
use App\Enums\TripStatus;
use App\Models\Booking;
use App\Models\Trip;

/**
 * Time-based jobs run every minute by the scheduler (`php artisan musafir:tick`).
 * Every step is idempotent: running it twice changes nothing the second time.
 */
class TripTimers
{
    public function __construct(
        private BookingService $bookings,
        private RouteAlertService $alerts,
        private Notifier $notifier,
        private SettlementCycle $settlement,
    ) {}

    /** @return array<string, int> counts per step */
    public function tick(): array
    {
        return [
            'expired' => $this->bookings->expireOverdue(),
            'driver_reminders' => $this->bookings->remindDrivers(),
            'parcels_expired' => app(ParcelService::class)->expireOverdue(),
            'parcel_reminders' => app(ParcelService::class)->remindDrivers(),
            'trip_reminders' => $this->tripReminders(),
            'no_shows_confirmed' => $this->bookings->confirmUndisputedNoShows(),
            'auto_completed' => $this->autoComplete(),
            'alerts_deactivated' => $this->alerts->deactivatePast(),
            'settlement_reminders' => $this->settlement->remindDue(),
            'frozen' => $this->settlement->freezeOverdue(),
        ];
    }

    /** Evening before (setting booking.reminder_evening_hour) and N hours before departure, to passengers and driver. */
    public function tripReminders(): int
    {
        $tz = config('app.display_timezone');
        $now = now($tz);
        $sent = 0;

        // "Soon": departs within N hours.
        $soon = now()->addHours(settings()->int('booking.reminder_soon_hours'));
        $sent += $this->remind(
            Trip::where('status', TripStatus::Scheduled)->whereNull('reminded_soon_at')->whereBetween('departs_at', [now(), $soon]),
            'reminded_soon_at',
            fn (Trip $t) => 'رحلتك بعد قليل',
        );

        // "Evening before": after the configured hour, for trips departing tomorrow (Sudan time).
        if ($now->hour >= settings()->int('booking.reminder_evening_hour')) {
            $tomorrow = $now->copy()->addDay()->startOfDay();
            $sent += $this->remind(
                Trip::where('status', TripStatus::Scheduled)->whereNull('reminded_evening_at')
                    ->whereBetween('departs_at', [$tomorrow->copy()->utc(), $tomorrow->copy()->endOfDay()->utc()]),
                'reminded_evening_at',
                fn (Trip $t) => 'تذكير: رحلتك غداً',
            );
        }

        return $sent;
    }

    private function remind($query, string $column, \Closure $title): int
    {
        $sent = 0;

        $query->with(['driverProfile.user', 'route.origin', 'route.destination'])->chunkById(100, function ($trips) use ($column, $title, &$sent) {
            foreach ($trips as $trip) {
                $trip->forceFill([$column => now()])->save();
                $when = local_time($trip->departs_at)->format('Y-m-d H:i');

                $accepted = $trip->bookings()->where('status', BookingStatus::Accepted)->whereNull($column)->with(['passenger', 'pickupPoint'])->get();
                if ($accepted->isNotEmpty()) {
                    $this->notifier->send($trip->driverProfile->user, NotificationEvent::TripReminder, $title($trip),
                        "{$trip->route->label()} — $when. عدد الركاب المؤكدين: ".$accepted->sum('seats'), ['trip_id' => $trip->id]);
                    $sent++;
                }

                foreach ($accepted as $booking) {
                    $booking->forceFill([$column => now()])->save();
                    $this->notifier->send($booking->passenger, NotificationEvent::TripReminder, $title($trip),
                        "{$trip->route->label()} — $when. نقطة التحرك: ".($booking->pickupPoint?->label ?? '—'), ['booking_id' => $booking->id]);
                    $sent++;
                }
            }
        });

        return $sent;
    }

    /** Departed trips the driver forgot to complete are completed automatically. */
    public function autoComplete(): int
    {
        $count = 0;
        Trip::where('status', TripStatus::Departed)
            ->where('departed_at', '<=', now()->subHours(settings()->int('trips.auto_complete_hours')))
            ->each(function (Trip $trip) use (&$count) {
                app(TripService::class)->complete($trip, force: true);
                $count++;
            });

        return $count;
    }
}
