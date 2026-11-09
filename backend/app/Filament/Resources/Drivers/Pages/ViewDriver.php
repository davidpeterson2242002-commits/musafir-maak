<?php

namespace App\Filament\Resources\Drivers\Pages;

use App\Enums\AdminRole;
use App\Exceptions\BusinessRuleException;
use App\Filament\Resources\Drivers\DriverResource;
use App\Filament\Resources\Ledger\LedgerResource;
use App\Models\DriverProfile;
use App\Models\LedgerEntry;
use App\Services\LedgerService;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

class ViewDriver extends ViewRecord
{
    protected static string $resource = DriverResource::class;

    protected function getHeaderActions(): array
    {
        $finance = fn () => Auth::guard('admin')->user()->hasRole(AdminRole::Finance);

        return [
            Action::make('ledger')
                ->label('كشف الحساب')->color('gray')->icon(Heroicon::OutlinedBookOpen)
                ->url(fn (DriverProfile $record) => LedgerResource::getUrl('index', ['filters' => ['driver_profile_id' => ['value' => $record->id]]]))
                ->visible($finance),
            Action::make('adjust')
                ->label('تسوية يدوية')->color('warning')->icon(Heroicon::OutlinedAdjustmentsHorizontal)
                ->visible($finance)
                ->schema([
                    Radio::make('bucket')->label('الحساب')->required()->default(LedgerEntry::BUCKET_BALANCE)
                        ->options([LedgerEntry::BUCKET_BALANCE => 'الرصيد المستحق', LedgerEntry::BUCKET_WALLET => 'المحفظة'])->inline(),
                    TextInput::make('amount')->label('المبلغ (موجب = زيادة، سالب = نقص)')->integer()->required()->notIn([0]),
                    Textarea::make('reason')->label('السبب (إلزامي، يُسجّل)')->required()->maxLength(500),
                ])
                ->action(function (DriverProfile $record, array $data) {
                    try {
                        app(LedgerService::class)->adjust($record, $data['bucket'], (int) $data['amount'], $data['reason'], Auth::guard('admin')->user());
                        Notification::make()->title('تم تسجيل التسوية')->success()->send();
                        $this->refreshFormData(['balance_owed', 'wallet_balance', 'status']);
                    } catch (BusinessRuleException $e) {
                        Notification::make()->title($e->getMessage())->danger()->send();
                    }
                }),
            Action::make('grant_free')
                ->label('منح حجوزات مجانية')->color('gray')->icon(Heroicon::OutlinedGift)
                ->visible($finance)
                ->schema([
                    TextInput::make('count')->label('العدد')->integer()->minValue(1)->maxValue(100)->required()->default(1),
                    Textarea::make('reason')->label('السبب')->required()->maxLength(500),
                ])
                ->action(function (DriverProfile $record, array $data) {
                    app(LedgerService::class)->grantFreeBookings($record, (int) $data['count'], $data['reason'], Auth::guard('admin')->user());
                    Notification::make()->title('تم منح '.$data['count'].' حجز مجاني')->success()->send();
                    $this->refreshFormData(['free_bookings_remaining']);
                }),
        ];
    }
}
