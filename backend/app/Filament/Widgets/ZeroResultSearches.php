<?php

namespace App\Filament\Widgets;

use App\Models\SearchLog;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/** Routes people search for but find nothing (last 7 days) — where to recruit drivers (spec 9.1). */
class ZeroResultSearches extends TableWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'بحث بدون نتائج — آخر 7 أيام (أين نحتاج سائقين)';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => SearchLog::query()
                ->selectRaw('min(id) as id, origin_city_id, destination_city_id, count(*) as searches, count(distinct user_id) as users, max(created_at) as last_at')
                ->where('results_count', 0)
                ->where('created_at', '>=', now()->subDays(7))
                ->groupBy('origin_city_id', 'destination_city_id')
                ->with(['origin', 'destination']))
            ->defaultSort('searches', 'desc')
            ->defaultKeySort(false)
            ->paginated([5, 10])
            ->defaultPaginationPageOption(5)
            ->columns([
                TextColumn::make('route')->label('الطريق')
                    ->state(fn ($record) => $record->origin->name_ar.' ← '.$record->destination->name_ar),
                TextColumn::make('searches')->label('مرات البحث')->sortable(),
                TextColumn::make('users')->label('مستخدمون'),
                TextColumn::make('last_at')->label('آخر بحث')->since(),
            ])
            ->emptyStateHeading('لا يوجد بحث بدون نتائج');
    }
}
