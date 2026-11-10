<?php

namespace App\Filament\Resources\NotificationLogs\Pages;

use App\Filament\Resources\NotificationLogs\NotificationLogResource;
use App\Models\NotificationLog;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListNotificationLogs extends ListRecords
{
    protected static string $resource = NotificationLogResource::class;

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('الكل'),
            'failed' => Tab::make('الفاشلة')->badge(NotificationLogResource::getNavigationBadge())->badgeColor('danger')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', NotificationLog::FAILED)),
            'whatsapp' => Tab::make('واتساب')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('channel', 'whatsapp')),
        ];
    }
}
