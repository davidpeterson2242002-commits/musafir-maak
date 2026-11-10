<?php

namespace App\Filament\Resources\NotificationChannels\Pages;

use App\Enums\NotificationEvent;
use App\Filament\Resources\NotificationChannels\NotificationChannelResource;
use App\Models\NotificationChannel;
use App\Services\AuditLogger;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;

class ManageNotificationChannels extends ManageRecords
{
    protected static string $resource = NotificationChannelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('reset')
                ->label('إرجاع الإعدادات الافتراضية')->color('gray')
                ->requiresConfirmation()
                ->modalDescription('تُرجَع كل الإشعارات إلى القنوات الافتراضية.')
                ->action(function () {
                    foreach (NotificationEvent::configurable() as $event) {
                        NotificationChannel::updateOrCreate(['event' => $event->value], $event->defaultChannels());
                    }
                    app(AuditLogger::class)->log('notification_channels.reset');
                    Notification::make()->title('تم إرجاع القنوات الافتراضية')->success()->send();
                }),
        ];
    }
}
