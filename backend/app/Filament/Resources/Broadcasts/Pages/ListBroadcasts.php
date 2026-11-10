<?php

namespace App\Filament\Resources\Broadcasts\Pages;

use App\Filament\Resources\Broadcasts\BroadcastResource;
use App\Services\BroadcastService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

class ListBroadcasts extends ListRecords
{
    protected static string $resource = BroadcastResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('send')
                ->label('إرسال إشعار جماعي')->icon(Heroicon::OutlinedMegaphone)
                ->modalHeading('إشعار جماعي')
                ->modalDescription('يُرسل فوراً إلى كل المستلمين ولا يمكن التراجع عنه.')
                ->modalSubmitActionLabel('إرسال الآن')
                ->schema(BroadcastResource::composeSchema())
                ->action(function (array $data) {
                    $broadcast = app(BroadcastService::class)->create(
                        Auth::guard('admin')->user(), $data['segment'], BroadcastResource::filtersFrom($data),
                        $data['channels'], $data['title'], $data['body'],
                    );

                    Notification::make()->title('بدأ الإرسال إلى '.number_format($broadcast->recipients_count).' مستلم')->success()->send();
                }),
        ];
    }
}
