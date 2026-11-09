<?php

namespace App\Filament\Resources\Bookings;

use App\Enums\AdminRole;
use App\Enums\BookingStatus;
use App\Filament\Concerns\RestrictedToRoles;
use App\Filament\Resources\Trips\TripResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\Booking;
use App\Support\Phone;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\IconEntry;
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

/** Bookings & no-show disputes (spec 9.5). */
class BookingResource extends Resource
{
    use RestrictedToRoles;

    protected static array $allowedRoles = [AdminRole::Support];

    protected static ?string $model = Booking::class;

    protected static ?string $slug = 'bookings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTicket;

    protected static string|UnitEnum|null $navigationGroup = 'الرحلات';

    protected static ?string $modelLabel = 'حجز';

    protected static ?string $pluralModelLabel = 'الحجوزات';

    protected static ?int $navigationSort = 2;

    public static function getNavigationBadge(): ?string
    {
        $open = Booking::where('status', BookingStatus::NoShow)->whereNotNull('no_show_disputed_at')->whereNull('no_show_resolution')->count();

        return $open ? (string) $open : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'اعتراضات "لم يحضر" مفتوحة';
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
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['trip.route.origin', 'trip.route.destination', 'trip.driverProfile.user', 'passenger']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('id')->label('#')->sortable(),
                TextColumn::make('trip.departs_at')->label('الرحلة')->dateTime('Y-m-d H:i')->timezone(config('app.display_timezone'))
                    ->description(fn (Booking $record) => $record->trip->route->label()),
                TextColumn::make('passenger.name')->label('الراكب')->searchable(),
                TextColumn::make('trip.driverProfile.user.name')->label('السائق'),
                TextColumn::make('seats')->label('مقاعد'),
                TextColumn::make('price_total')->label('المبلغ')->numeric()->suffix(' ج'),
                TextColumn::make('status')->label('الحالة')->badge()
                    ->formatStateUsing(fn (BookingStatus $state) => $state->label())->color(fn (BookingStatus $state) => $state->color()),
                TextColumn::make('dispute')->label('اعتراض')->badge()->color('danger')
                    ->state(fn (Booking $record) => $record->noShowDisputeOpen() ? 'مفتوح' : null),
                TextColumn::make('created_at')->label('الطلب')->since()->sortable()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('الحالة')->options(BookingStatus::options()),
            ])
            ->recordActions([ViewAction::make()]);
    }

    public static function infolist(Schema $schema): Schema
    {
        $tz = config('app.display_timezone');

        return $schema->columns(1)->components([
            Section::make('الحجز')->columns(4)->schema([
                TextEntry::make('status')->label('الحالة')->badge()
                    ->formatStateUsing(fn (BookingStatus $state) => $state->label())->color(fn (BookingStatus $state) => $state->color()),
                TextEntry::make('seats')->label('المقاعد'),
                TextEntry::make('price_total')->label('المبلغ (يدفع للسائق)')->suffix(' ج'),
                TextEntry::make('pickupPoint.label')->label('نقطة التحرك')->placeholder('—'),
                TextEntry::make('extra_bags')->label('شنط إضافية'),
                IconEntry::make('includes_family')->label('مع عائلة')->boolean(),
                TextEntry::make('fee_amount')->label('العمولة')->suffix(' ج')->placeholder('—'),
                IconEntry::make('late_cancellation')->label('إلغاء متأخر')->boolean(),
                TextEntry::make('message')->label('رسالة الراكب')->placeholder('—')->columnSpanFull(),
            ]),
            Section::make('الأطراف')->columns(4)->schema([
                TextEntry::make('passenger.name')->label('الراكب')
                    ->url(fn (Booking $record) => UserResource::getUrl('view', ['record' => $record->passenger_id])),
                TextEntry::make('passenger.phone')->label('هاتف الراكب')->formatStateUsing(fn ($state) => Phone::displayLtr($state))->copyable(),
                TextEntry::make('trip.driverProfile.user.name')->label('السائق'),
                TextEntry::make('trip.driverProfile.user.phone')->label('هاتف السائق')->formatStateUsing(fn ($state) => Phone::displayLtr($state))->copyable(),
                TextEntry::make('trip')->label('الرحلة')->columnSpan(2)
                    ->state(fn (Booking $record) => $record->trip->route->label().' — '.local_time($record->trip->departs_at)->format('Y-m-d H:i'))
                    ->url(fn (Booking $record) => TripResource::getUrl('view', ['record' => $record->trip_id])),
            ]),
            Section::make('التسلسل الزمني')->columns(4)->schema([
                TextEntry::make('created_at')->label('الطلب')->dateTime('Y-m-d H:i')->timezone($tz),
                TextEntry::make('expires_at')->label('مهلة الرد')->dateTime('Y-m-d H:i')->timezone($tz)->placeholder('—'),
                TextEntry::make('responded_at')->label('رد السائق')->dateTime('Y-m-d H:i')->timezone($tz)->placeholder('—'),
                TextEntry::make('rejection_reason')->label('سبب الرفض')->placeholder('—'),
                TextEntry::make('cancelled_at')->label('الإلغاء')->dateTime('Y-m-d H:i')->timezone($tz)->placeholder('—'),
                TextEntry::make('cancel_reason')->label('سبب الإلغاء')->placeholder('—'),
                TextEntry::make('completed_at')->label('الاكتمال')->dateTime('Y-m-d H:i')->timezone($tz)->placeholder('—'),
            ]),
            Section::make('عدم الحضور')->columns(4)
                ->visible(fn (Booking $record) => $record->no_show_at !== null)
                ->schema([
                    TextEntry::make('no_show_at')->label('سجّله السائق')->dateTime('Y-m-d H:i')->timezone($tz),
                    TextEntry::make('no_show_disputed_at')->label('اعترض الراكب')->dateTime('Y-m-d H:i')->timezone($tz)->placeholder('لم يعترض'),
                    TextEntry::make('no_show_resolution')->label('القرار')->badge()
                        ->formatStateUsing(fn ($state) => match ($state) {
                            'confirmed' => 'تأكيد عدم الحضور', 'overturned' => 'إلغاء التسجيل', default => $state
                        })
                        ->placeholder('بانتظار القرار'),
                    TextEntry::make('no_show_confirmed_at')->label('تأكيد')->dateTime('Y-m-d H:i')->timezone($tz)->placeholder('—'),
                    TextEntry::make('no_show_dispute_details')->label('تفاصيل الاعتراض')->placeholder('—')->columnSpanFull(),
                ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBookings::route('/'),
            'view' => Pages\ViewBooking::route('/{record}'),
        ];
    }
}
