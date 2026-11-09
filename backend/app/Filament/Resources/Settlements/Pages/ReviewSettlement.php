<?php

namespace App\Filament\Resources\Settlements\Pages;

use App\Exceptions\BusinessRuleException;
use App\Filament\Resources\Settlements\SettlementResource;
use App\Models\SettlementRequest;
use App\Services\LedgerService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

class ReviewSettlement extends ViewRecord
{
    protected static string $resource = SettlementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('confirm')
                ->label('تأكيد الاستلام')->color('success')->icon(Heroicon::OutlinedCheckCircle)
                ->visible(fn (SettlementRequest $record) => $record->isPending())
                ->requiresConfirmation()
                ->modalHeading(fn (SettlementRequest $record) => 'تأكيد استلام '.number_format($record->amount).' جنيه؟')
                ->modalDescription('تأكد أن المبلغ وصل فعلاً لحساب الشركة ورقم العملية صحيح. سيُخصم من رصيد السائق ويُرفع التجميد إذا سدّد كامل الرصيد.')
                ->action(function (SettlementRequest $record) {
                    try {
                        app(LedgerService::class)->confirmSettlement($record, Auth::guard('admin')->user());
                        Notification::make()->title('تم تأكيد الدفعة')->success()->send();
                        $this->redirect(SettlementResource::getUrl());
                    } catch (BusinessRuleException $e) {
                        Notification::make()->title($e->getMessage())->danger()->send();
                    }
                }),
            Action::make('reject')
                ->label('رفض')->color('danger')->icon(Heroicon::OutlinedXCircle)
                ->visible(fn (SettlementRequest $record) => $record->isPending())
                ->schema([Textarea::make('reason')->label('السبب (يظهر للسائق)')->required()->maxLength(500)
                    ->placeholder('مثلاً: لم يصل المبلغ للحساب / رقم العملية غير صحيح')])
                ->action(function (SettlementRequest $record, array $data) {
                    try {
                        app(LedgerService::class)->rejectSettlement($record, Auth::guard('admin')->user(), $data['reason']);
                        Notification::make()->title('تم رفض الدفعة وإشعار السائق')->success()->send();
                        $this->redirect(SettlementResource::getUrl());
                    } catch (BusinessRuleException $e) {
                        Notification::make()->title($e->getMessage())->danger()->send();
                    }
                }),
        ];
    }
}
