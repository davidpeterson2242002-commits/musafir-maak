<?php

namespace App\Filament\Resources\Trips\Pages;

use App\Enums\TripStatus;
use App\Filament\Resources\Trips\TripResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListTrips extends ListRecords
{
    protected static string $resource = TripResource::class;

    public function getTabs(): array
    {
        return [
            'upcoming' => Tab::make('القادمة')->modifyQueryUsing(fn (Builder $query) => $query->where('status', TripStatus::Scheduled)->reorder()->orderBy('departs_at')),
            'ongoing' => Tab::make('في الطريق')->modifyQueryUsing(fn (Builder $query) => $query->where('status', TripStatus::Departed)),
            'all' => Tab::make('الكل'),
        ];
    }
}
