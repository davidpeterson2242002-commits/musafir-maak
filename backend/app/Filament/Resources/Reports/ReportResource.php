<?php

namespace App\Filament\Resources\Reports;

use App\Enums\AdminRole;
use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use App\Filament\Concerns\RestrictedToRoles;
use App\Filament\Resources\Bookings\BookingResource;
use App\Filament\Resources\Parcels\ParcelResource;
use App\Filament\Resources\Trips\TripResource;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Support\ChatLog;
use App\Models\Booking;
use App\Models\Parcel;
use App\Models\Report;
use App\Models\Trip;
use App\Support\Phone;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/** Reports queue (spec 9.6): safety-critical first, then oldest. */
class ReportResource extends Resource
{
    use RestrictedToRoles;

    protected static array $allowedRoles = [AdminRole::Support];

    protected static ?string $model = Report::class;

    protected static ?string $slug = 'reports';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFlag;

    protected static string|UnitEnum|null $navigationGroup = 'البلاغات والدعم';

    protected static ?string $modelLabel = 'بلاغ';

    protected static ?string $pluralModelLabel = 'البلاغات';

    protected static ?int $navigationSort = 1;

    public static function getNavigationBadge(): ?string
    {
        $n = Report::where('status', '!=', ReportStatus::Resolved)->count();

        return $n ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return Report::where('status', '!=', ReportStatus::Resolved)->where('priority', '>', 0)->exists() ? 'danger' : 'warning';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['reporter', 'reportedUser', 'handler']))
            ->defaultSort(fn (Builder $query) => $query->orderByDesc('priority')->orderBy('created_at'))
            ->columns([
                TextColumn::make('priority')->label('')->badge()->color('danger')
                    ->state(fn (Report $record) => $record->priority > 0 ? 'سلامة' : null),
                TextColumn::make('reason')->label('السبب')->formatStateUsing(fn (ReportReason $state) => $state->label()),
                TextColumn::make('reportedUser.name')->label('المُبلَّغ عنه')->searchable(),
                TextColumn::make('reporter.name')->label('المُبلِّغ')->searchable(),
                TextColumn::make('subject_type')->label('على')->formatStateUsing(fn ($state) => match ($state) {
                    'booking' => 'حجز', 'parcel' => 'ارسالية', 'trip' => 'رحلة', 'conversation' => 'محادثة', default => '—',
                })->placeholder('ملف شخصي'),
                TextColumn::make('created_at')->label('الوقت')->since()->sortable(),
                TextColumn::make('status')->label('الحالة')->badge()
                    ->formatStateUsing(fn (ReportStatus $state) => $state->label())->color(fn (ReportStatus $state) => $state->color()),
                TextColumn::make('handler.name')->label('المسؤول')->placeholder('—'),
            ])
            ->filters([SelectFilter::make('reason')->label('السبب')->options(ReportReason::options())])
            ->recordActions([ViewAction::make()->label('مراجعة')]);
    }

    public static function infolist(Schema $schema): Schema
    {
        $tz = config('app.display_timezone');

        return $schema->columns(2)->components([
            Section::make('البلاغ')->columns(2)->schema([
                TextEntry::make('reason')->label('السبب')->formatStateUsing(fn (ReportReason $state) => $state->label())->weight('bold'),
                TextEntry::make('status')->label('الحالة')->badge()
                    ->formatStateUsing(fn (ReportStatus $state) => $state->label())->color(fn (ReportStatus $state) => $state->color()),
                TextEntry::make('created_at')->label('الوقت')->dateTime('Y-m-d H:i')->timezone($tz),
                TextEntry::make('subject')->label('على')
                    ->state(fn (Report $record) => match (true) {
                        $record->subject instanceof Booking => 'حجز #'.$record->subject_id,
                        $record->subject instanceof Parcel => 'ارسالية #'.$record->subject_id,
                        $record->subject instanceof Trip => 'رحلة #'.$record->subject_id,
                        default => $record->subject_type ? $record->subject_type.' #'.$record->subject_id : 'الملف الشخصي',
                    })
                    ->url(fn (Report $record) => match (true) {
                        $record->subject instanceof Booking => BookingResource::getUrl('view', ['record' => $record->subject_id]),
                        $record->subject instanceof Parcel => ParcelResource::getUrl('view', ['record' => $record->subject_id]),
                        $record->subject instanceof Trip => TripResource::getUrl('view', ['record' => $record->subject_id]),
                        default => null,
                    }),
                TextEntry::make('details')->label('التفاصيل')->placeholder('—')->columnSpanFull(),
                TextEntry::make('resolution_note')->label('القرار')->placeholder('—')->columnSpanFull()
                    ->visible(fn (Report $record) => $record->status === ReportStatus::Resolved),
            ]),
            Section::make('الطرفان')->columns(2)->schema([
                TextEntry::make('reportedUser.name')->label('المُبلَّغ عنه')->weight('bold')
                    ->url(fn (Report $record) => UserResource::getUrl('view', ['record' => $record->reported_user_id])),
                TextEntry::make('reportedUser.phone')->label('هاتفه')->formatStateUsing(fn ($state) => Phone::displayLtr($state)),
                TextEntry::make('reported_history')->label('سجله')
                    ->state(fn (Report $record) => sprintf('%d بلاغ ضده · %d إجراء سابق · تقييم %s',
                        Report::where('reported_user_id', $record->reported_user_id)->count(),
                        $record->reportedUser->enforcementActions()->count(),
                        $record->reportedUser->driverProfile?->rating_avg ?? $record->reportedUser->rating_avg))
                    ->columnSpanFull(),
                TextEntry::make('reporter.name')->label('المُبلِّغ')
                    ->url(fn (Report $record) => UserResource::getUrl('view', ['record' => $record->reporter_id])),
                TextEntry::make('reporter.phone')->label('هاتفه')->formatStateUsing(fn ($state) => Phone::displayLtr($state)),
            ]),
            Section::make('المحادثة')
                ->description('تُعرض للمراجعة بسبب هذا البلاغ. الرسائل الحمراء معلّمة تلقائياً.')
                ->columnSpanFull()
                ->visible(fn (Report $record) => $record->relatedConversation() !== null)
                ->schema([ChatLog::entry(fn (Report $record) => $record->relatedConversation())]),
            Section::make('الإجراءات المتخذة')->columnSpanFull()
                ->visible(fn (Report $record) => $record->enforcementActions()->exists())
                ->schema([
                    TextEntry::make('actions')->hiddenLabel()->listWithLineBreaks()->bulleted()
                        ->state(fn (Report $record) => $record->enforcementActions()->with('admin')->get()
                            ->map(fn ($a) => $a->actionLabel().' — '.$a->reason.' ('.$a->admin?->name.')')->all()),
                ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReports::route('/'),
            'view' => Pages\ReviewReport::route('/{record}'),
        ];
    }
}
