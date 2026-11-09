<?php

namespace App\Filament\Resources\Bookings\Pages;

use App\Exceptions\BusinessRuleException;
use App\Filament\Resources\Bookings\BookingResource;
use App\Models\Booking;
use App\Services\BookingService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

class ViewBooking extends ViewRecord
{
    protected static string $resource = BookingResource::class;

    protected function getHeaderActions(): array
    {
        $resolve = fn (bool $confirm) => function (Booking $record, array $data) use ($confirm) {
            try {
                app(BookingService::class)->resolveNoShow($record, $confirm, Auth::guard('admin')->user(), $data['note'] ?? null);
                Notification::make()->title('تم حفظ القرار')->success()->send();
                $this->refreshFormData([]);
            } catch (BusinessRuleException $e) {
                Notification::make()->title($e->getMessage())->danger()->send();
            }
        };

        return [
            Action::make('confirm_no_show')
                ->label('تأكيد عدم الحضور')->color('danger')->icon(Heroicon::OutlinedUserMinus)
                ->visible(fn (Booking $record) => $record->noShowDisputeOpen())
                ->requiresConfirmation()->modalDescription('تُلغى عمولة هذا الحجز على السائق.')
                ->schema([Textarea::make('note')->label('ملاحظة')->maxLength(500)])
                ->action($resolve(true)),
            Action::make('overturn_no_show')
                ->label('الراكب حضر — ألغِ التسجيل')->color('success')->icon(Heroicon::OutlinedUserPlus)
                ->visible(fn (Booking $record) => $record->noShowDisputeOpen())
                ->requiresConfirmation()->modalDescription('يُعتبر الحجز مكتملاً وتبقى العمولة.')
                ->schema([Textarea::make('note')->label('ملاحظة')->maxLength(500)])
                ->action($resolve(false)),
        ];
    }
}
