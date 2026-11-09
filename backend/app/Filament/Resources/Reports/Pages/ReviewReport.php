<?php

namespace App\Filament\Resources\Reports\Pages;

use App\Enums\ReportStatus;
use App\Enums\UserStatus;
use App\Filament\Resources\Reports\ReportResource;
use App\Models\Booking;
use App\Models\Parcel;
use App\Models\Report;
use App\Services\AuditLogger;
use App\Services\EnforcementService;
use App\Services\LedgerService;
use App\Services\ReportService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

class ReviewReport extends ViewRecord
{
    protected static string $resource = ReportResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);
        app(ReportService::class)->startReview($this->getRecord(), Auth::guard('admin')->user());
    }

    protected function getHeaderActions(): array
    {
        $open = fn (Report $record) => $record->status !== ReportStatus::Resolved;
        $admin = fn () => Auth::guard('admin')->user();

        return [
            Action::make('resolve')
                ->label('إغلاق البلاغ')->color('success')->icon(Heroicon::OutlinedCheckCircle)
                ->visible($open)
                ->schema([Textarea::make('note')->label('القرار / ملاحظة الإغلاق')->required()->maxLength(1000)])
                ->action(function (Report $record, array $data) use ($admin) {
                    app(ReportService::class)->resolve($record, $admin(), $data['note']);
                    Notification::make()->title('تم إغلاق البلاغ')->success()->send();
                    $this->redirect(ReportResource::getUrl());
                }),
            ActionGroup::make([
                Action::make('warn')->label('تنبيه المستخدم')->icon(Heroicon::OutlinedExclamationTriangle)
                    ->schema([Textarea::make('reason')->label('نص التنبيه (يصل للمستخدم)')->required()->maxLength(500)])
                    ->action(function (Report $record, array $data) use ($admin) {
                        app(EnforcementService::class)->warn($record->reportedUser, $data['reason'], $admin(), $record);
                        Notification::make()->title('تم إرسال التنبيه')->success()->send();
                    }),
                Action::make('suspend')->label('إيقاف مؤقت')->icon(Heroicon::OutlinedPauseCircle)->color('warning')
                    ->visible(fn (Report $record) => $record->reportedUser->status === UserStatus::Active)
                    ->schema([
                        Select::make('days')->label('المدة')->required()->default(7)
                            ->options([1 => 'يوم', 3 => '3 أيام', 7 => 'أسبوع', 30 => 'شهر']),
                        Textarea::make('reason')->label('السبب (يصل للمستخدم)')->required()->maxLength(500),
                    ])
                    ->action(function (Report $record, array $data) use ($admin) {
                        app(EnforcementService::class)->suspend($record->reportedUser, now()->addDays((int) $data['days']), $data['reason'], $admin(), $record);
                        Notification::make()->title('تم الإيقاف')->success()->send();
                    }),
                Action::make('ban')->label('حظر نهائي')->icon(Heroicon::OutlinedNoSymbol)->color('danger')
                    ->visible(fn (Report $record) => $record->reportedUser->status !== UserStatus::Banned)
                    ->requiresConfirmation()
                    ->modalDescription('يُسجّل خروجه، وتُلغى رحلاته القادمة (مع إشعار الركاب) وحجوزاته وارسالياته المفتوحة.')
                    ->schema([Textarea::make('reason')->label('السبب')->required()->maxLength(500)])
                    ->action(function (Report $record, array $data) use ($admin) {
                        app(EnforcementService::class)->ban($record->reportedUser, $data['reason'], $admin(), $record);
                        Notification::make()->title('تم الحظر')->success()->send();
                    }),
                Action::make('reverse_fee')->label('إلغاء عمولة الحجز')->icon(Heroicon::OutlinedReceiptRefund)
                    ->visible(fn (Report $record) => ($record->subject instanceof Booking || $record->subject instanceof Parcel)
                        && $record->subject->fee_model !== null && $record->subject->fee_reversed_at === null)
                    ->requiresConfirmation()
                    ->action(function (Report $record) use ($admin) {
                        $subject = $record->subject;
                        $reason = 'بقرار من الدعم — بلاغ #'.$record->id;
                        $subject instanceof Booking
                            ? app(LedgerService::class)->reverseBooking($subject, $reason)
                            : app(LedgerService::class)->reverseParcel($subject, $reason);
                        app(AuditLogger::class)->log('report.fee_reversed', $record, [], $admin());
                        Notification::make()->title('تم إلغاء العمولة')->success()->send();
                    }),
            ])->label('إجراء على المُبلَّغ عنه')->icon(Heroicon::OutlinedShieldExclamation)->button()->color('gray')
                ->visible(fn () => $this->getRecord()->status !== ReportStatus::Resolved),
        ];
    }
}
