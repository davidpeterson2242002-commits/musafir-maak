<?php

namespace App\Filament\Resources\Parcels\Pages;

use App\Enums\ParcelStatus;
use App\Exceptions\BusinessRuleException;
use App\Filament\Resources\Parcels\ParcelResource;
use App\Models\Parcel;
use App\Services\ParcelService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

class ViewParcel extends ViewRecord
{
    protected static string $resource = ParcelResource::class;

    protected function getHeaderActions(): array
    {
        $resolve = fn (string $resolution) => function (Parcel $record, array $data) use ($resolution) {
            try {
                app(ParcelService::class)->resolveDispute($record, $resolution, Auth::guard('admin')->user(), $data['note'] ?? null);
                Notification::make()->title('تم حسم النزاع')->success()->send();
                $this->refreshFormData([]);
            } catch (BusinessRuleException $e) {
                Notification::make()->title($e->getMessage())->danger()->send();
            }
        };

        return [
            Action::make('resolve_delivered')
                ->label('اعتبارها مسلّمة')->color('success')->icon(Heroicon::OutlinedCheckCircle)
                ->visible(fn (Parcel $record) => $record->status === ParcelStatus::Disputed)
                ->requiresConfirmation()->modalDescription('تبقى عمولة السائق.')
                ->schema([Textarea::make('note')->label('ملاحظة')->maxLength(500)])
                ->action($resolve('delivered')),
            Action::make('resolve_cancelled')
                ->label('إلغاء لصالح المرسل')->color('danger')->icon(Heroicon::OutlinedXCircle)
                ->visible(fn (Parcel $record) => $record->status === ParcelStatus::Disputed)
                ->requiresConfirmation()->modalDescription('تُلغى عمولة السائق على هذه الارسالية.')
                ->schema([Textarea::make('note')->label('ملاحظة')->maxLength(500)])
                ->action($resolve('cancelled')),
        ];
    }
}
