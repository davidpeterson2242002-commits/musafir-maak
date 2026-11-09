<?php

namespace App\Filament\Resources\Reports\Pages;

use App\Enums\ReportStatus;
use App\Filament\Resources\Reports\ReportResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListReports extends ListRecords
{
    protected static string $resource = ReportResource::class;

    public function getTabs(): array
    {
        return [
            'open' => Tab::make('المفتوحة')->badge(ReportResource::getNavigationBadge())
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', '!=', ReportStatus::Resolved)),
            'resolved' => Tab::make('المغلقة')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', ReportStatus::Resolved)->reorder()->orderByDesc('handled_at')),
        ];
    }
}
