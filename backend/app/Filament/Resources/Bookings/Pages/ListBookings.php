<?php

namespace App\Filament\Resources\Bookings\Pages;

use App\Enums\BookingStatus;
use App\Filament\Resources\Bookings\BookingResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListBookings extends ListRecords
{
    protected static string $resource = BookingResource::class;

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('الكل'),
            'pending' => Tab::make('بانتظار السائق')->modifyQueryUsing(fn (Builder $query) => $query->where('status', BookingStatus::Pending)),
            'disputes' => Tab::make('اعتراضات "لم يحضر"')
                ->badge(BookingResource::getNavigationBadge())->badgeColor('danger')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', BookingStatus::NoShow)->whereNotNull('no_show_disputed_at')->whereNull('no_show_resolution')),
        ];
    }
}
