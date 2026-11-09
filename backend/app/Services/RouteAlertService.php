<?php

namespace App\Services;

use App\Enums\NotificationEvent;
use App\Models\RouteAlert;
use App\Models\Trip;

/** "Notify me when a trip is posted on this route" (spec P12). */
class RouteAlertService
{
    public function __construct(private Notifier $notifier) {}

    public function notifyFor(Trip $trip): int
    {
        $trip->loadMissing(['route.origin', 'route.destination', 'driverProfile']);
        $localDate = local_time($trip->departs_at)->toDateString();

        $alerts = RouteAlert::query()
            ->where('active', true)
            ->where('origin_city_id', $trip->route->origin_city_id)
            ->where('destination_city_id', $trip->route->destination_city_id)
            ->where(fn ($q) => $q->whereNull('date')->orWhereDate('date', $localDate))
            ->where('user_id', '!=', $trip->driverProfile->user_id)
            ->with('user')
            ->get();

        foreach ($alerts as $alert) {
            $this->notifier->send(
                $alert->user,
                NotificationEvent::RouteAlertMatch,
                'رحلة جديدة على طريقك',
                sprintf('%s يوم %s الساعة %s — %d جنيه للمقعد.',
                    $trip->route->label(), $localDate, local_time($trip->departs_at)->format('H:i'), $trip->price_per_seat),
                ['trip_id' => $trip->id, 'route_alert_id' => $alert->id],
            );
            $alert->forceFill(['last_notified_at' => now(), 'notified_count' => $alert->notified_count + 1])->save();
        }

        return $alerts->count();
    }

    /** Alerts for a specific date are switched off once that day has passed. */
    public function deactivatePast(): int
    {
        return RouteAlert::where('active', true)->whereNotNull('date')
            ->whereDate('date', '<', now(config('app.display_timezone'))->toDateString())
            ->update(['active' => false]);
    }
}
