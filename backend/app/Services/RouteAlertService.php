<?php

namespace App\Services;

use App\Enums\NotificationEvent;
use App\Models\City;
use App\Models\RouteAlert;
use App\Models\TravelRoute;
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
            ->where('kind', 'trips')
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

    /**
     * "Tell me when it opens" alerts whose route is open now: notify once and switch them off.
     * Called whenever a route, city or state is switched on (observers) and by the scheduler.
     */
    public function notifyReopened(): int
    {
        $alerts = RouteAlert::where('kind', 'reopen')->where('active', true)
            ->with(['user', 'origin.state', 'destination.state'])->get();
        if ($alerts->isEmpty()) {
            return 0;
        }

        $sent = 0;
        foreach ($alerts->groupBy(fn ($a) => $a->origin_city_id.'-'.$a->destination_city_id) as $group) {
            $first = $group->first();
            if (! $this->isOpen($first->origin, $first->destination)) {
                continue;
            }
            $label = $first->origin->name_ar.' ← '.$first->destination->name_ar;
            foreach ($group as $alert) {
                if (! $alert->user) {
                    continue;
                }
                $this->notifier->send(
                    $alert->user,
                    NotificationEvent::RouteReopened,
                    'الطريق اتفتح 🎉',
                    $alert->role === 'driver'
                        ? "طريق $label بقى متاح. تقدر تنشر رحلتك هسي."
                        : "طريق $label بقى متاح. ابحث عن رحلة هسي.",
                    ['origin_city_id' => $alert->origin_city_id, 'destination_city_id' => $alert->destination_city_id, 'route_alert_id' => $alert->id],
                );
                $alert->forceFill(['active' => false, 'last_notified_at' => now(), 'notified_count' => $alert->notified_count + 1])->save();
                $sent++;
            }
        }

        return $sent;
    }

    /** Same rule as search: an existing route must be open; a missing one needs both cities on (it is auto-created). */
    public function isOpen(?City $origin, ?City $destination): bool
    {
        if (! $origin || ! $destination) {
            return false;
        }
        $route = TravelRoute::between($origin->id, $destination->id)->with(['origin.state', 'destination.state'])->first();
        if ($route) {
            return $route->isOpen();
        }

        return $origin->isAvailable() && $destination->isAvailable() && settings()->bool('routes.auto_create');
    }

    /** Alerts for a specific date are switched off once that day has passed. */
    public function deactivatePast(): int
    {
        return RouteAlert::where('active', true)->whereNotNull('date')
            ->whereDate('date', '<', now(config('app.display_timezone'))->toDateString())
            ->update(['active' => false]);
    }
}
