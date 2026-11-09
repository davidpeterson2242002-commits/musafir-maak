<?php

namespace App\Filament\Resources\Settlements\Pages;

use App\Enums\SettlementStatus;
use App\Filament\Resources\Settlements\SettlementResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListSettlements extends ListRecords
{
    protected static string $resource = SettlementResource::class;

    public function getTabs(): array
    {
        return [
            'pending' => Tab::make('بانتظار التأكيد')->badge(SettlementResource::getNavigationBadge())
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', SettlementStatus::Pending)),
            'confirmed' => Tab::make('مؤكدة')->modifyQueryUsing(fn (Builder $query) => $query->where('status', SettlementStatus::Confirmed)->reorder()->orderByDesc('reviewed_at')),
            'rejected' => Tab::make('مرفوضة')->modifyQueryUsing(fn (Builder $query) => $query->where('status', SettlementStatus::Rejected)->reorder()->orderByDesc('reviewed_at')),
        ];
    }
}
