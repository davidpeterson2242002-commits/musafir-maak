<?php

namespace App\Filament\Resources\Trips\Pages;

use App\Enums\TripStatus;
use App\Exceptions\BusinessRuleException;
use App\Filament\Resources\Trips\TripResource;
use App\Models\Trip;
use App\Services\TripService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class ViewTrip extends ViewRecord
{
    protected static string $resource = TripResource::class;

    protected function resolveRecord(int|string $key): Model
    {
        return parent::resolveRecord($key)->load([
            'route.origin', 'route.destination', 'driverProfile.user', 'vehicle', 'points',
            'bookings' => fn ($q) => $q->orderBy('id'), 'bookings.passenger', 'bookings.pickupPoint',
            'parcels' => fn ($q) => $q->orderBy('id'), 'parcels.sender',
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('cancel')
                ->label('إلغاء الرحلة')->color('danger')->icon(Heroicon::OutlinedXCircle)
                ->visible(fn (Trip $record) => $record->status === TripStatus::Scheduled)
                ->requiresConfirmation()
                ->modalDescription('سيتم إلغاء كل الحجوزات وإشعار الركاب بالواتساب.')
                ->schema([Textarea::make('reason')->label('السبب (يظهر للركاب)')->required()->maxLength(500)])
                ->action(function (Trip $record, array $data) {
                    try {
                        app(TripService::class)->cancel($record, $data['reason'], Auth::guard('admin')->user());
                        Notification::make()->title('تم إلغاء الرحلة')->success()->send();
                    } catch (BusinessRuleException $e) {
                        Notification::make()->title($e->getMessage())->danger()->send();
                    }
                }),
            Action::make('toggle_hidden')
                ->label(fn (Trip $record) => $record->hidden_by_admin ? 'إظهار في البحث' : 'إخفاء من البحث')
                ->color('gray')->icon(Heroicon::OutlinedEyeSlash)
                ->requiresConfirmation()
                ->action(function (Trip $record) {
                    app(TripService::class)->setHidden($record, ! $record->hidden_by_admin, Auth::guard('admin')->user());
                    Notification::make()->title('تم')->success()->send();
                }),
        ];
    }
}
