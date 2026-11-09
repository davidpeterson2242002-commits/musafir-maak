<?php

namespace App\Filament\Resources\Trips;

use App\Enums\AdminRole;
use App\Enums\BookingStatus;
use App\Enums\PointKind;
use App\Enums\TripStatus;
use App\Filament\Concerns\RestrictedToRoles;
use App\Filament\Resources\Bookings\BookingResource;
use App\Filament\Resources\Drivers\DriverResource;
use App\Models\TravelRoute;
use App\Models\Trip;
use App\Support\Phone;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use UnitEnum;

/** Trips management (spec 9.4). */
class TripResource extends Resource
{
    use RestrictedToRoles;

    protected static array $allowedRoles = [AdminRole::Support];

    protected static ?string $model = Trip::class;

    protected static ?string $slug = 'trips';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static string|UnitEnum|null $navigationGroup = 'الرحلات';

    protected static ?string $modelLabel = 'رحلة';

    protected static ?string $pluralModelLabel = 'الرحلات';

    protected static ?int $navigationSort = 1;

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
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['route.origin', 'route.destination', 'driverProfile.user', 'vehicle'])
                ->withCount(['bookings as pending_count' => fn ($q) => $q->where('status', BookingStatus::Pending)]))
            ->defaultSort('departs_at', 'desc')
            ->columns([
                TextColumn::make('departs_at')->label('الانطلاق')->dateTime('Y-m-d H:i')->timezone(config('app.display_timezone'))->sortable(),
                TextColumn::make('route')->label('الطريق')->state(fn (Trip $record) => $record->route->label()),
                TextColumn::make('driverProfile.user.name')->label('السائق')->searchable(),
                TextColumn::make('vehicle.type')->label('العربية')->formatStateUsing(fn ($state) => $state?->label())->toggleable(),
                TextColumn::make('seats')->label('المقاعد')->state(fn (Trip $record) => ($record->seats_total - $record->seats_available).' / '.$record->seats_total),
                TextColumn::make('pending_count')->label('طلبات معلقة')->badge()->color('warning'),
                TextColumn::make('price_per_seat')->label('السعر')->numeric()->suffix(' ج')->sortable(),
                TextColumn::make('status')->label('الحالة')->badge()
                    ->formatStateUsing(fn (TripStatus $state) => $state->label())->color(fn (TripStatus $state) => $state->color()),
                IconColumn::make('women_only')->label('نساء')->boolean()->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('hidden_by_admin')->label('مخفية')->boolean()->trueColor('danger')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('الحالة')->options(TripStatus::options()),
                SelectFilter::make('route_id')->label('الطريق')->searchable()
                    ->options(fn () => TravelRoute::with(['origin', 'destination'])->orderBy('sort')->get()
                        ->mapWithKeys(fn ($route) => [$route->id => $route->label()])),
                Filter::make('date')->label('التاريخ')
                    ->schema([DatePicker::make('from')->label('من'), DatePicker::make('until')->label('إلى')])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn ($q, $d) => $q->where('departs_at', '>=', Carbon::parse($d, config('app.display_timezone'))->startOfDay()->utc()))
                        ->when($data['until'] ?? null, fn ($q, $d) => $q->where('departs_at', '<=', Carbon::parse($d, config('app.display_timezone'))->endOfDay()->utc()))),
                TernaryFilter::make('hidden_by_admin')->label('مخفية'),
            ])
            ->recordActions([ViewAction::make()]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('الرحلة')->columns(4)->schema([
                TextEntry::make('route')->label('الطريق')->state(fn (Trip $record) => $record->route->label())->weight('bold'),
                TextEntry::make('departs_at')->label('الانطلاق')->dateTime('Y-m-d H:i')->timezone(config('app.display_timezone')),
                TextEntry::make('status')->label('الحالة')->badge()
                    ->formatStateUsing(fn (TripStatus $state) => $state->label())->color(fn (TripStatus $state) => $state->color()),
                IconEntry::make('hidden_by_admin')->label('مخفية من البحث')->boolean(),
                TextEntry::make('driverProfile.user.name')->label('السائق')
                    ->url(fn (Trip $record) => DriverResource::getUrl('view', ['record' => $record->driver_profile_id])),
                TextEntry::make('vehicle')->label('العربية')->state(fn (Trip $record) => $record->vehicle->label()),
                TextEntry::make('price_per_seat')->label('سعر المقعد')->suffix(' ج'),
                TextEntry::make('seats')->label('المقاعد المحجوزة')->state(fn (Trip $record) => ($record->seats_total - $record->seats_available).' / '.$record->seats_total),
                TextEntry::make('luggage')->label('الشنط لكل راكب')->state(fn (Trip $record) => "{$record->luggage_large} كبيرة + {$record->luggage_small} صغيرة"),
                TextEntry::make('extra_luggage_price')->label('سعر الشنطة الإضافية')->suffix(' ج')->placeholder('لا يقبل'),
                IconEntry::make('women_only')->label('نساء وعائلات فقط')->boolean(),
                IconEntry::make('accepts_parcels')->label('يقبل ارساليات')->boolean(),
                TextEntry::make('pickup')->label('نقاط التحرك')->state(fn (Trip $record) => $record->points->where('kind', PointKind::Pickup)->pluck('label')->values()->all())->listWithLineBreaks()->bulleted(),
                TextEntry::make('dropoff')->label('نقاط النزول')->state(fn (Trip $record) => $record->points->where('kind', PointKind::Dropoff)->pluck('label')->values()->all())->listWithLineBreaks()->bulleted(),
                TextEntry::make('notes')->label('ملاحظات')->placeholder('—')->columnSpan(2),
                TextEntry::make('cancel_reason')->label('سبب الإلغاء')->visible(fn (Trip $record) => $record->status === TripStatus::Cancelled)->columnSpanFull(),
            ]),
            Section::make('الحجوزات')->schema([
                RepeatableEntry::make('bookings')->hiddenLabel()->schema([
                    Grid::make(6)->schema([
                        TextEntry::make('passenger.name')->label('الراكب')
                            ->url(fn ($record) => BookingResource::getUrl('view', ['record' => $record->id])),
                        TextEntry::make('passenger.phone')->label('الهاتف')->formatStateUsing(fn ($state) => Phone::displayLtr($state)),
                        TextEntry::make('seats')->label('المقاعد'),
                        TextEntry::make('pickupPoint.label')->label('نقطة التحرك')->placeholder('—'),
                        TextEntry::make('price_total')->label('المبلغ')->suffix(' ج'),
                        TextEntry::make('status')->label('الحالة')->badge()
                            ->formatStateUsing(fn (BookingStatus $state) => $state->label())->color(fn (BookingStatus $state) => $state->color()),
                    ]),
                ])->placeholder('لا توجد حجوزات'),
            ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTrips::route('/'),
            'view' => Pages\ViewTrip::route('/{record}'),
        ];
    }
}
