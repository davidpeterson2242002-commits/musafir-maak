<?php

namespace App\Filament\Resources\Settlements;

use App\Enums\AdminRole;
use App\Enums\SettlementPurpose;
use App\Enums\SettlementStatus;
use App\Filament\Concerns\RestrictedToRoles;
use App\Filament\Resources\Drivers\DriverResource;
use App\Models\SettlementRequest;
use App\Support\Phone;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\ImageEntry;
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

/** Payment confirmations queue (spec 9.7). */
class SettlementResource extends Resource
{
    use RestrictedToRoles;

    protected static array $allowedRoles = [AdminRole::Finance];

    protected static ?string $model = SettlementRequest::class;

    protected static ?string $slug = 'payments';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'المالية';

    protected static ?string $modelLabel = 'دفعة';

    protected static ?string $pluralModelLabel = 'تأكيد المدفوعات';

    protected static ?int $navigationSort = 1;

    public static function getNavigationBadge(): ?string
    {
        $count = SettlementRequest::where('status', SettlementStatus::Pending)->count();

        return $count ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
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
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['driverProfile.user', 'paymentMethod', 'reviewer']))
            ->defaultSort('created_at')
            ->columns([
                TextColumn::make('created_at')->label('أُرسل')->dateTime('Y-m-d H:i')->timezone(config('app.display_timezone'))->sortable(),
                TextColumn::make('driverProfile.user.name')->label('السائق')->searchable(),
                TextColumn::make('purpose')->label('الغرض')->badge()->formatStateUsing(fn (SettlementPurpose $state) => $state->label()),
                TextColumn::make('amount')->label('المبلغ')->numeric()->suffix(' ج')->sortable()->weight('bold'),
                TextColumn::make('paymentMethod.name')->label('الطريقة'),
                TextColumn::make('reference_number')->label('رقم العملية')->searchable()->copyable()->placeholder('—'),
                TextColumn::make('driverProfile.balance_owed')->label('رصيده المستحق')->numeric()->suffix(' ج'),
                TextColumn::make('status')->label('الحالة')->badge()
                    ->formatStateUsing(fn (SettlementStatus $state) => $state->label())->color(fn (SettlementStatus $state) => $state->color()),
                TextColumn::make('reviewer.name')->label('راجعه')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('payment_method_id')->label('الطريقة')->relationship('paymentMethod', 'name'),
                SelectFilter::make('purpose')->label('الغرض')->options(SettlementPurpose::options()),
            ])
            ->recordActions([ViewAction::make()->label('مراجعة')]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Section::make('الدفعة')->columns(2)->columnSpan(1)->schema([
                TextEntry::make('amount')->label('المبلغ')->numeric()->suffix(' جنيه')->size('lg')->weight('bold'),
                TextEntry::make('purpose')->label('الغرض')->formatStateUsing(fn (SettlementPurpose $state) => $state->label()),
                TextEntry::make('paymentMethod.name')->label('الطريقة'),
                TextEntry::make('reference_number')->label('رقم العملية')->copyable()->placeholder('—'),
                TextEntry::make('created_at')->label('أُرسل')->dateTime('Y-m-d H:i')->timezone(config('app.display_timezone')),
                TextEntry::make('status')->label('الحالة')->badge()
                    ->formatStateUsing(fn (SettlementStatus $state) => $state->label())->color(fn (SettlementStatus $state) => $state->color()),
                TextEntry::make('reject_reason')->label('سبب الرفض')->visible(fn (SettlementRequest $record) => $record->status === SettlementStatus::Rejected)->columnSpanFull(),
            ]),
            Section::make('السائق')->columns(2)->columnSpan(1)->schema([
                TextEntry::make('driverProfile.user.name')->label('الاسم')
                    ->url(fn (SettlementRequest $record) => DriverResource::getUrl('view', ['record' => $record->driver_profile_id])),
                TextEntry::make('driverProfile.user.phone')->label('الهاتف')->formatStateUsing(fn ($state) => Phone::displayLtr($state)),
                TextEntry::make('driverProfile.balance_owed')->label('الرصيد المستحق')->numeric()->suffix(' ج'),
                TextEntry::make('driverProfile.wallet_balance')->label('المحفظة')->numeric()->suffix(' ج'),
                TextEntry::make('driverProfile.settlement_due_at')->label('موعد السداد')->dateTime('Y-m-d H:i')->timezone(config('app.display_timezone'))->placeholder('—'),
                TextEntry::make('driverProfile.status')->label('حالة الحساب')->badge()
                    ->formatStateUsing(fn ($state) => $state->label())->color(fn ($state) => $state->color()),
            ]),
            Section::make('صورة الإشعار / الإيصال')->columnSpanFull()->schema([
                ImageEntry::make('receipt')->hiddenLabel()
                    ->state(fn (SettlementRequest $record) => $record->receiptUrl())
                    ->url(fn (SettlementRequest $record) => $record->receiptUrl(), shouldOpenInNewTab: true)
                    ->imageHeight(420)
                    ->extraImgAttributes(['style' => 'object-fit: contain; max-width: 100%; background: #f4f4f5; border-radius: 8px']),
            ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSettlements::route('/'),
            'view' => Pages\ReviewSettlement::route('/{record}'),
        ];
    }
}
