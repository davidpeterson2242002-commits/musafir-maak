<?php

namespace App\Filament\Resources\Parcels;

use App\Enums\AdminRole;
use App\Enums\ParcelStatus;
use App\Filament\Concerns\RestrictedToRoles;
use App\Filament\Resources\Trips\TripResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\Parcel;
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

/** Parcels & disputes (spec 9.5). */
class ParcelResource extends Resource
{
    use RestrictedToRoles;

    protected static array $allowedRoles = [AdminRole::Support];

    protected static ?string $model = Parcel::class;

    protected static ?string $slug = 'parcels';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCube;

    protected static string|UnitEnum|null $navigationGroup = 'الرحلات';

    protected static ?string $modelLabel = 'ارسالية';

    protected static ?string $pluralModelLabel = 'الارساليات';

    protected static ?int $navigationSort = 3;

    public static function getNavigationBadge(): ?string
    {
        $n = Parcel::where('status', ParcelStatus::Disputed)->count();

        return $n ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
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
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['sender', 'trip.route.origin', 'trip.route.destination', 'trip.driverProfile.user']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('id')->label('#'),
                TextColumn::make('trip.departs_at')->label('الرحلة')->dateTime('Y-m-d H:i')->timezone(config('app.display_timezone'))
                    ->description(fn (Parcel $record) => $record->trip->route->label()),
                TextColumn::make('sender.name')->label('المرسل')->searchable(),
                TextColumn::make('receiver_name')->label('المستلم')->searchable(),
                TextColumn::make('trip.driverProfile.user.name')->label('السائق'),
                TextColumn::make('size')->label('الحجم')->formatStateUsing(fn ($state, Parcel $record) => $record->sizeLabel()),
                TextColumn::make('price')->label('السعر')->numeric()->suffix(' ج'),
                TextColumn::make('status')->label('الحالة')->badge()
                    ->formatStateUsing(fn (ParcelStatus $state) => $state->label())->color(fn (ParcelStatus $state) => $state->color()),
            ])
            ->filters([SelectFilter::make('status')->label('الحالة')->options(ParcelStatus::options())])
            ->recordActions([ViewAction::make()]);
    }

    public static function infolist(Schema $schema): Schema
    {
        $tz = config('app.display_timezone');

        return $schema->columns(2)->components([
            Section::make('الارسالية')->columns(2)->schema([
                TextEntry::make('description')->label('المحتوى')->columnSpanFull(),
                TextEntry::make('size')->label('الحجم')->formatStateUsing(fn ($state, Parcel $record) => $record->sizeLabel()),
                TextEntry::make('price')->label('السعر')->numeric()->suffix(' ج'),
                TextEntry::make('payer')->label('الدفع')->formatStateUsing(fn ($state) => $state === 'sender' ? 'المرسل يدفع' : 'المستلم يدفع'),
                TextEntry::make('status')->label('الحالة')->badge()
                    ->formatStateUsing(fn (ParcelStatus $state) => $state->label())->color(fn (ParcelStatus $state) => $state->color()),
                TextEntry::make('fee_amount')->label('العمولة')->numeric()->suffix(' ج')->placeholder('—'),
                TextEntry::make('code_attempts')->label('محاولات رمز خاطئة'),
                TextEntry::make('refusal_reason')->label('سبب الرفض عند الاستلام')->placeholder('—'),
                TextEntry::make('cancel_reason')->label('سبب الإلغاء')->placeholder('—'),
            ]),
            Section::make('الأطراف')->columns(2)->schema([
                TextEntry::make('sender.name')->label('المرسل')->url(fn (Parcel $record) => UserResource::getUrl('view', ['record' => $record->sender_id])),
                TextEntry::make('sender.phone')->label('هاتف المرسل')->formatStateUsing(fn ($state) => Phone::displayLtr($state)),
                TextEntry::make('receiver_name')->label('المستلم'),
                TextEntry::make('receiver_phone')->label('هاتف المستلم')->formatStateUsing(fn ($state) => Phone::displayLtr($state)),
                TextEntry::make('trip.driverProfile.user.name')->label('السائق'),
                TextEntry::make('trip')->label('الرحلة')
                    ->state(fn (Parcel $record) => $record->trip->route->label().' — '.local_time($record->trip->departs_at)->format('Y-m-d H:i'))
                    ->url(fn (Parcel $record) => TripResource::getUrl('view', ['record' => $record->trip_id])),
                TextEntry::make('receiver_notified_at')->label('وصل المستلم رمز واتساب')->dateTime('Y-m-d H:i')->timezone($tz)->placeholder('لا'),
            ]),
            Section::make('الصور')->columns(2)->columnSpanFull()->schema([
                ImageEntry::make('photo')->label('صورة المرسل')->state(fn (Parcel $record) => $record->photoUrl())->imageHeight(240),
                ImageEntry::make('pickup_photo')->label('صورة السائق عند الاستلام')->state(fn (Parcel $record) => $record->pickupPhotoUrl())
                    ->imageHeight(240)->placeholder('لم يستلم بعد'),
            ]),
            Section::make('النزاع')->columns(3)->columnSpanFull()
                ->visible(fn (Parcel $record) => $record->disputed_at !== null)
                ->schema([
                    TextEntry::make('disputed_at')->label('فُتح')->dateTime('Y-m-d H:i')->timezone($tz),
                    TextEntry::make('dispute_resolution')->label('القرار')->badge()
                        ->formatStateUsing(fn ($state) => $state === 'delivered' ? 'اعتُبرت مسلّمة' : 'أُلغيت (لصالح المرسل)')->placeholder('مفتوح'),
                    TextEntry::make('dispute_details')->label('تفاصيل المرسل')->columnSpanFull(),
                ]),
            Section::make('التسلسل الزمني')->columns(5)->columnSpanFull()->schema([
                TextEntry::make('created_at')->label('الطلب')->dateTime('m-d H:i')->timezone($tz),
                TextEntry::make('accepted_at')->label('القبول')->dateTime('m-d H:i')->timezone($tz)->placeholder('—'),
                TextEntry::make('picked_up_at')->label('الاستلام')->dateTime('m-d H:i')->timezone($tz)->placeholder('—'),
                TextEntry::make('delivered_at')->label('التسليم')->dateTime('m-d H:i')->timezone($tz)->placeholder('—'),
                TextEntry::make('cancelled_at')->label('الإلغاء')->dateTime('m-d H:i')->timezone($tz)->placeholder('—'),
            ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListParcels::route('/'),
            'view' => Pages\ViewParcel::route('/{record}'),
        ];
    }
}
