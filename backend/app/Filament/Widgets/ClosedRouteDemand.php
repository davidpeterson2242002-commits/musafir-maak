<?php

namespace App\Filament\Widgets;

use App\Models\City;
use App\Models\RouteAlert;
use App\Models\SearchLog;
use App\Models\TravelRoute;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Facades\Cache;

/**
 * Closed routes people still want: searches that hit a closed route (30 days) and
 * "tell me when it opens" requests from passengers and drivers. Top = open these first.
 */
class ClosedRouteDemand extends TableWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected static bool $isDiscovered = false;

    protected static ?string $heading = 'طرق مقفولة عليها طلب — افتح الأكثر طلباً أولاً';

    public static function rows(): array
    {
        return Cache::remember('dashboard.closed_demand.v1', 60, function () {
            $since = now()->subDays(30);
            $searches = SearchLog::where('closed', true)->where('created_at', '>=', $since)
                ->selectRaw('origin_city_id, destination_city_id, count(*) as searches, count(distinct user_id) as users, max(created_at) as last_at')
                ->groupBy('origin_city_id', 'destination_city_id')->get()
                ->keyBy(fn ($r) => $r->origin_city_id.'-'.$r->destination_city_id);
            $alerts = RouteAlert::where('kind', 'reopen')->where('active', true)
                ->selectRaw("origin_city_id, destination_city_id, sum(case when role = 'driver' then 1 else 0 end) as drivers, sum(case when role = 'driver' then 0 else 1 end) as passengers, max(created_at) as last_at")
                ->groupBy('origin_city_id', 'destination_city_id')->get()
                ->keyBy(fn ($r) => $r->origin_city_id.'-'.$r->destination_city_id);

            $keys = $searches->keys()->merge($alerts->keys())->unique();
            if ($keys->isEmpty()) {
                return [];
            }
            $cityIds = $keys->flatMap(fn ($k) => explode('-', $k))->unique();
            $cities = City::whereIn('id', $cityIds)->pluck('name_ar', 'id');
            $routes = TravelRoute::whereIn('origin_city_id', $cityIds)->get()->keyBy(fn ($r) => $r->origin_city_id.'-'.$r->destination_city_id);

            return $keys->map(function ($key) use ($searches, $alerts, $cities, $routes) {
                [$o, $d] = array_map('intval', explode('-', $key));
                $s = $searches->get($key);
                $a = $alerts->get($key);
                $drivers = (int) ($a->drivers ?? 0);
                $passengers = (int) ($a->passengers ?? 0);
                $last = collect([$s?->last_at, $a?->last_at])->filter()->max();

                return [
                    'key' => $key,
                    'route' => ($cities[$o] ?? '?').' ← '.($cities[$d] ?? '?'),
                    'route_id' => $routes->get($key)?->id,
                    'searches' => (int) ($s->searches ?? 0),
                    'users' => (int) ($s->users ?? 0),
                    'passengers' => $passengers,
                    'drivers' => $drivers,
                    // A driver ready to drive is worth more than a search.
                    'score' => (int) ($s->users ?? 0) + $passengers * 2 + $drivers * 5,
                    'last_at' => $last,
                ];
            })->sortByDesc('score')->values()->all();
        });
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (): array => collect(static::rows())->keyBy('key')->all())
            ->paginated([10, 25])
            ->columns([
                TextColumn::make('route')->label('الطريق')->weight('bold')
                    ->url(fn (array $record) => $record['route_id'] ? \App\Filament\Resources\Routes\RouteResource::getUrl() : null),
                TextColumn::make('drivers')->label('سائقين عايزين يشتغلوا')->badge()->color(fn ($state) => $state ? 'success' : 'gray'),
                TextColumn::make('passengers')->label('ركاب طلبوا تنبيه')->badge()->color(fn ($state) => $state ? 'warning' : 'gray'),
                TextColumn::make('searches')->label('بحث (30 يوم)'),
                TextColumn::make('users')->label('أشخاص بحثوا'),
                TextColumn::make('last_at')->label('آخر طلب')->since(),
            ])
            ->emptyStateHeading('لا يوجد طلب على طرق مقفولة')
            ->emptyStateDescription('لما راكب أو سائق يبحث عن طريق مقفول أو يطلب تنبيه، بيظهر هنا.');
    }
}
