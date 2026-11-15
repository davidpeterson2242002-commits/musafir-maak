<?php

namespace App\Filament\Widgets;

use App\Models\City;
use App\Models\SearchLog;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Facades\Cache;

/** The most searched routes in the last 7 days and how often people found a trip. */
class TopRoutes extends TableWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected static bool $isDiscovered = false;

    protected static ?string $heading = 'أكثر الطرق بحثاً — آخر 7 أيام';

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (): array => Cache::remember('dashboard.top_routes.v1', 120, function () {
                $rows = SearchLog::where('created_at', '>=', now()->subDays(7))
                    ->selectRaw('origin_city_id, destination_city_id, count(*) as searches, count(distinct user_id) as users, sum(case when results_count > 0 then 1 else 0 end) as found')
                    ->groupBy('origin_city_id', 'destination_city_id')->orderByDesc('searches')->limit(15)->get();
                $cities = City::whereIn('id', $rows->pluck('origin_city_id')->merge($rows->pluck('destination_city_id')))->pluck('name_ar', 'id');

                return $rows->mapWithKeys(fn ($r) => [$r->origin_city_id.'-'.$r->destination_city_id => [
                    'route' => ($cities[$r->origin_city_id] ?? '?').' ← '.($cities[$r->destination_city_id] ?? '?'),
                    'searches' => (int) $r->searches,
                    'users' => (int) $r->users,
                    'found' => $r->searches ? (int) round($r->found * 100 / $r->searches).'%' : '—',
                ]])->all();
            }))
            ->paginated(false)
            ->columns([
                TextColumn::make('route')->label('الطريق')->weight('bold'),
                TextColumn::make('searches')->label('مرات البحث'),
                TextColumn::make('users')->label('أشخاص'),
                TextColumn::make('found')->label('لقوا رحلة'),
            ])
            ->emptyStateHeading('لا يوجد بحث بعد');
    }
}
