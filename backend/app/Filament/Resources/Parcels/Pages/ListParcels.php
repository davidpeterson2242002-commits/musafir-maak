<?php

namespace App\Filament\Resources\Parcels\Pages;

use App\Enums\ParcelStatus;
use App\Filament\Resources\Parcels\ParcelResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListParcels extends ListRecords
{
    protected static string $resource = ParcelResource::class;

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('الكل'),
            'active' => Tab::make('في الطريق')->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', [ParcelStatus::Accepted, ParcelStatus::PickedUp])),
            'disputes' => Tab::make('النزاعات')->badge(ParcelResource::getNavigationBadge())->badgeColor('danger')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', ParcelStatus::Disputed)),
        ];
    }
}
