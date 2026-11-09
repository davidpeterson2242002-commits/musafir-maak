<?php

namespace App\Filament\Resources\Drivers;

use App\Enums\AdminRole;
use App\Enums\DriverStatus;
use App\Enums\TripStatus;
use App\Enums\VehicleStatus;
use App\Filament\Concerns\RestrictedToRoles;
use App\Filament\Resources\Users\UserResource;
use App\Models\DriverProfile;
use App\Support\Phone;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class DriverResource extends Resource
{
    use RestrictedToRoles;

    protected static array $allowedRoles = [AdminRole::Reviewer, AdminRole::Support, AdminRole::Finance];

    protected static ?string $model = DriverProfile::class;

    protected static ?string $slug = 'drivers';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static string|UnitEnum|null $navigationGroup = 'المستخدمون';

    protected static ?string $modelLabel = 'سائق';

    protected static ?string $pluralModelLabel = 'السائقون';

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
            ->modifyQueryUsing(fn (Builder $query) => $query->with('user')->withCount([
                'vehicles', 'vehicles as approved_vehicles_count' => fn ($q) => $q->where('status', VehicleStatus::Approved),
            ]))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('user.name')->label('الاسم')->searchable(),
                TextColumn::make('user.phone')->label('الهاتف')->searchable()->formatStateUsing(fn ($state) => Phone::displayLtr($state)),
                TextColumn::make('status')->label('الحالة')->badge()
                    ->formatStateUsing(fn (DriverStatus $state) => $state->label())->color(fn (DriverStatus $state) => $state->color()),
                TextColumn::make('approved_vehicles_count')->label('عربيات معتمدة')
                    ->formatStateUsing(fn ($state, DriverProfile $record) => "$state / {$record->vehicles_count}"),
                TextColumn::make('free_bookings_remaining')->label('حجوزات مجانية'),
                TextColumn::make('rating_avg')->label('التقييم')->toggleable(),
                TextColumn::make('approved_at')->label('اعتُمد')->date()->placeholder('—')->sortable(),
                TextColumn::make('created_at')->label('بدأ التسجيل')->since()->sortable()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('الحالة')->options(DriverStatus::options()),
            ])
            ->recordActions([ViewAction::make()]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('السائق')->columns(4)->schema([
                TextEntry::make('user.name')->label('الاسم')
                    ->url(fn (DriverProfile $record) => UserResource::getUrl('view', ['record' => $record->user_id])),
                TextEntry::make('user.phone')->label('الهاتف')->formatStateUsing(fn ($state) => Phone::displayLtr($state))->copyable(),
                TextEntry::make('status')->label('الحالة')->badge()
                    ->formatStateUsing(fn (DriverStatus $state) => $state->label())->color(fn (DriverStatus $state) => $state->color()),
                TextEntry::make('user.id_verification_status')->label('الهوية')->badge()
                    ->formatStateUsing(fn ($state) => $state->label())->color(fn ($state) => $state->color()),
                TextEntry::make('free_bookings_remaining')->label('حجوزات مجانية متبقية'),
                TextEntry::make('balance_owed')->label('الرصيد المستحق')->suffix(' ج'),
                TextEntry::make('recent_cancellations')->label('رحلات ألغاها مؤخراً')
                    ->state(fn (DriverProfile $record) => $record->trips()->where('status', TripStatus::Cancelled)
                        ->where('cancelled_at', '>=', now()->subDays(settings()->int('cancellation.driver_window_days')))->count())
                    ->badge()->color(fn ($state) => $state >= settings()->int('cancellation.driver_limit') ? 'danger' : 'gray')
                    ->helperText(fn () => 'خلال '.settings()->int('cancellation.driver_window_days').' يوماً'),
                TextEntry::make('completed_trips')->label('رحلات مكتملة')
                    ->state(fn (DriverProfile $record) => $record->trips()->where('status', TripStatus::Completed)->count()),
                TextEntry::make('submitted_at')->label('أرسل الطلب')->dateTime('Y-m-d H:i')->placeholder('—'),
                TextEntry::make('approved_at')->label('اعتُمد')->dateTime('Y-m-d H:i')->placeholder('—'),
            ]),
            Section::make('العربيات')->schema([
                RepeatableEntry::make('vehicles')->hiddenLabel()->schema([
                    Grid::make(6)->schema([
                        TextEntry::make('type')->label('النوع')->formatStateUsing(fn ($state) => $state->label()),
                        TextEntry::make('make')->label('العربية')->formatStateUsing(fn ($state, $record) => "{$record->make} {$record->model} ({$record->color})"),
                        TextEntry::make('plate_number')->label('اللوحة')->weight('bold'),
                        TextEntry::make('seats')->label('المقاعد'),
                        TextEntry::make('status')->label('الحالة')->badge()
                            ->formatStateUsing(fn (VehicleStatus $state) => $state->label())->color(fn (VehicleStatus $state) => $state->color()),
                        TextEntry::make('approved_at')->label('اعتُمدت')->date()->placeholder('—'),
                    ]),
                ])->placeholder('لا توجد عربيات'),
            ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDrivers::route('/'),
            'view' => Pages\ViewDriver::route('/{record}'),
        ];
    }
}
