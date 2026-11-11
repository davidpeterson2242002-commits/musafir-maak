<?php

namespace App\Services;

use App\Enums\ParcelStatus;
use App\Models\City;
use App\Models\SearchLog;
use App\Models\TravelRoute;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/** Trip search by city pair + date (spec 2 "Matching", P4, S1). Every search is logged (spec 9.1/9.8). */
class SearchService
{
    public const TIMES_OF_DAY = [
        'night' => [0, 5],      // 00:00–05:00
        'morning' => [5, 12],
        'afternoon' => [12, 17],
        'evening' => [17, 24],
    ];

    /**
     * @param  array{origin_city_id:int, destination_city_id:int, date:string, seats?:int, kind?:string,
     *               time_of_day?:array, max_price?:int, vehicle_types?:array, women_only?:bool, min_rating?:float, sort?:string}  $params
     * @return array{route: ?TravelRoute, closed: bool, closure_message: ?string, trips: Collection, nearby_dates: array}
     */
    public function search(array $params, ?User $user = null): array
    {
        $originId = (int) $params['origin_city_id'];
        $destinationId = (int) $params['destination_city_id'];
        $kind = $params['kind'] ?? 'trip';
        $date = Carbon::parse($params['date'], config('app.display_timezone'))->startOfDay();

        $route = TravelRoute::between($originId, $destinationId)->with(['origin.state', 'destination.state'])->first();
        $closed = $route !== null && ! $route->isOpen();

        if ($route === null && (! City::find($originId)?->enabled || ! City::find($destinationId)?->enabled)) {
            $closed = true;
        }

        $trips = ($route === null || $closed)
            ? new Collection
            : $this->query($route, $params, $user)->whereBetween('departs_at', [$date->copy()->utc(), $date->copy()->endOfDay()->utc()])->get();

        SearchLog::create([
            'origin_city_id' => $originId,
            'destination_city_id' => $destinationId,
            'route_id' => $route?->id,
            'date' => $date->toDateString(),
            'kind' => $kind,
            'results_count' => $trips->count(),
            'user_id' => $user?->id,
        ]);

        return [
            'route' => $route,
            'closed' => $closed,
            'closure_message' => $closed ? ($route?->closure_message ?: 'هذا الطريق موقوف مؤقتاً') : null,
            'trips' => $trips,
            'nearby_dates' => ($trips->isEmpty() && $route && ! $closed) ? $this->nearbyDates($route, $params, $date, $user) : [],
        ];
    }

    private function query(TravelRoute $route, array $params, ?User $user): Builder
    {
        $seats = max(1, (int) ($params['seats'] ?? 1));
        $isParcel = ($params['kind'] ?? 'trip') === 'parcel';

        $query = Trip::query()
            ->bookable()
            ->where('trips.route_id', $route->id)
            // A trip with every seat taken can still carry parcels.
            ->when(! $isParcel, fn ($q) => $q->where('trips.seats_available', '>=', $seats))
            ->with(['driverProfile.user', 'vehicle', 'route.origin', 'route.destination'])
            ->select('trips.*');

        if ($user) {
            $hidden = [$user->id, ...$user->blockedUserIds()];
            $query->whereHas('driverProfile', fn ($q) => $q->whereNotIn('user_id', $hidden));
        }

        if (($params['kind'] ?? 'trip') === 'parcel') {
            $query->where('trips.accepts_parcels', true)
                ->whereRaw('trips.max_parcels > (select count(*) from parcels p where p.trip_id = trips.id and p.status in (?, ?, ?, ?))',
                    array_map(fn ($s) => $s->value, ParcelStatus::slotHolders()));
        }

        if (! empty($params['time_of_day'])) {
            $tz = config('app.display_timezone');
            if ($query->getConnection()->getDriverName() === 'pgsql') {
                $hourSql = "extract(hour from (trips.departs_at at time zone 'UTC' at time zone ?))";
                $tzArg = $tz;
            } else {
                // MySQL / MariaDB: a numeric offset works even without the server's time-zone tables.
                $hourSql = "hour(convert_tz(trips.departs_at, '+00:00', ?))";
                $tzArg = now($tz)->format('P');
            }
            $query->where(function ($q) use ($params, $hourSql, $tzArg) {
                foreach ((array) $params['time_of_day'] as $slot) {
                    [$from, $to] = self::TIMES_OF_DAY[$slot] ?? [0, 24];
                    $q->orWhereRaw("$hourSql >= ? and $hourSql < ?", [$tzArg, $from, $tzArg, $to]);
                }
            });
        }

        if (! empty($params['max_price'])) {
            $query->where('trips.price_per_seat', '<=', (int) $params['max_price']);
        }

        if (! empty($params['vehicle_types'])) {
            $query->whereHas('vehicle', fn ($q) => $q->whereIn('type', (array) $params['vehicle_types']));
        }

        if (! empty($params['women_only'])) {
            $query->where('trips.women_only', true);
        }

        if (! empty($params['min_rating'])) {
            $query->whereHas('driverProfile', fn ($q) => $q->where('rating_avg', '>=', (float) $params['min_rating']));
        }

        match ($params['sort'] ?? 'earliest') {
            'cheapest' => $query->orderBy('trips.price_per_seat')->orderBy('trips.departs_at'),
            'rating' => $query->join('driver_profiles as dp', 'dp.id', '=', 'trips.driver_profile_id')
                ->orderByDesc('dp.rating_avg')->orderBy('trips.departs_at'),
            default => $query->orderBy('trips.departs_at'),
        };

        return $query;
    }

    /** Trip counts on nearby days (± setting search.nearby_days) when the chosen day is empty. */
    private function nearbyDates(TravelRoute $route, array $params, Carbon $date, ?User $user): array
    {
        $days = settings()->int('search.nearby_days');
        if ($days === 0) {
            return [];
        }

        $tz = config('app.display_timezone');
        $from = $date->copy()->subDays($days)->max(now($tz)->startOfDay());
        $to = $date->copy()->addDays($days)->endOfDay();

        $counts = $this->query($route, array_diff_key($params, ['sort' => 1]), $user)
            ->reorder()
            ->whereBetween('trips.departs_at', [$from->copy()->utc(), $to->copy()->utc()])
            ->get(['trips.departs_at'])
            ->groupBy(fn (Trip $t) => local_time($t->departs_at)->toDateString())
            ->map->count();

        return $counts->sortKeys()->map(fn ($count, $day) => ['date' => $day, 'trips' => $count])->values()->all();
    }
}
