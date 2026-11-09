<?php

namespace App\Filament\Resources\Users;

use App\Enums\AdminRole;
use App\Enums\BookingStatus;
use App\Enums\Gender;
use App\Enums\IdVerificationStatus;
use App\Enums\UserStatus;
use App\Filament\Concerns\RestrictedToRoles;
use App\Filament\Resources\Reports\ReportResource;
use App\Models\City;
use App\Models\Report;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\EnforcementService;
use App\Support\Phone;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class UserResource extends Resource
{
    use RestrictedToRoles;

    protected static array $allowedRoles = [AdminRole::Support, AdminRole::Reviewer, AdminRole::Finance];

    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = 'المستخدمون';

    protected static ?string $modelLabel = 'مستخدم';

    protected static ?string $pluralModelLabel = 'المستخدمون';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'phone'];
    }

    public static function canCreate(): bool
    {
        return false; // users sign up from the app
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('بيانات المستخدم')->columns(2)->schema([
                TextInput::make('name')->label('الاسم')->maxLength(80),
                TextInput::make('phone')->label('الهاتف')->disabled()->dehydrated(false),
                TextInput::make('whatsapp_phone')->label('واتساب')->tel()
                    ->rule(fn () => function ($attr, $value, $fail) {
                        if (filled($value) && ! Phone::isValid($value)) {
                            $fail('رقم غير صحيح.');
                        }
                    })
                    ->dehydrateStateUsing(fn ($state) => Phone::normalize($state)),
                TextInput::make('email')->label('البريد')->email(),
                Select::make('gender')->label('الجنس')->options(Gender::options())
                    ->helperText('تغيير الجنس يؤثر على رحلات النساء والعائلات فقط.'),
                Select::make('city_id')->label('المدينة')->options(fn () => City::orderBy('name_ar')->pluck('name_ar', 'id'))->searchable(),
            ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('الملف الشخصي')->columns(3)->schema([
                TextEntry::make('name')->label('الاسم')->placeholder('لم يُكمل الملف'),
                TextEntry::make('phone')->label('الهاتف')->formatStateUsing(fn ($state) => Phone::displayLtr($state))->copyable(),
                TextEntry::make('whatsapp_phone')->label('واتساب')->placeholder('نفس الهاتف'),
                TextEntry::make('email')->label('البريد')->placeholder('—'),
                TextEntry::make('gender')->label('الجنس')->formatStateUsing(fn (?Gender $state) => $state?->label())->placeholder('—'),
                TextEntry::make('city.name_ar')->label('المدينة')->placeholder('—'),
                TextEntry::make('created_at')->label('عضو منذ')->date(),
                TextEntry::make('last_seen_at')->label('آخر نشاط')->since()->placeholder('—'),
                TextEntry::make('rating_avg')->label('التقييم')->formatStateUsing(fn ($state, User $record) => $record->rating_count ? "$state ({$record->rating_count})" : 'لا يوجد'),
            ]),
            Section::make('السجل')->columns(4)->schema([
                TextEntry::make('reports_against')->label('بلاغات ضده')
                    ->state(fn (User $record) => Report::where('reported_user_id', $record->id)->count())
                    ->url(fn (User $record) => ReportResource::getUrl('index')),
                TextEntry::make('reports_by')->label('بلاغات منه')
                    ->state(fn (User $record) => Report::where('reporter_id', $record->id)->count()),
                TextEntry::make('bookings_count')->label('حجوزات مكتملة')
                    ->state(fn (User $record) => $record->bookings()->where('status', BookingStatus::Completed)->count()),
                TextEntry::make('parcels_count')->label('ارساليات')
                    ->state(fn (User $record) => $record->parcels()->count()),
                TextEntry::make('enforcement')->label('الإجراءات السابقة')->columnSpanFull()->listWithLineBreaks()->bulleted()->placeholder('لا يوجد')
                    ->state(fn (User $record) => $record->enforcementActions()->with('admin')->limit(10)->get()
                        ->map(fn ($a) => local_time($a->created_at)->format('Y-m-d').' — '.$a->actionLabel().': '.$a->reason.' ('.$a->admin?->name.')')->all()),
            ]),
            Section::make('الحالة')->columns(3)->schema([
                TextEntry::make('status')->label('حالة الحساب')->badge()
                    ->formatStateUsing(fn (UserStatus $state) => $state->label())->color(fn (UserStatus $state) => $state->color()),
                TextEntry::make('suspended_until')->label('موقوف حتى')->dateTime('Y-m-d H:i')->placeholder('—'),
                TextEntry::make('booking_restricted_until')->label('الحجز موقوف حتى')->dateTime('Y-m-d H:i')->timezone(config('app.display_timezone'))->placeholder('—'),
                TextEntry::make('id_verification_status')->label('توثيق الهوية')->badge()
                    ->formatStateUsing(fn (IdVerificationStatus $state) => $state->label())->color(fn (IdVerificationStatus $state) => $state->color()),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('city'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')->label('الاسم')->searchable()->placeholder('لم يُكمل الملف'),
                TextColumn::make('phone')->label('الهاتف')->searchable()->formatStateUsing(fn ($state) => Phone::displayLtr($state)),
                TextColumn::make('city.name_ar')->label('المدينة')->placeholder('—'),
                TextColumn::make('status')->label('الحالة')->badge()
                    ->formatStateUsing(fn (UserStatus $state) => $state->label())->color(fn (UserStatus $state) => $state->color()),
                TextColumn::make('id_verification_status')->label('الهوية')->badge()
                    ->formatStateUsing(fn (IdVerificationStatus $state) => $state->label())->color(fn (IdVerificationStatus $state) => $state->color()),
                TextColumn::make('rating_avg')->label('التقييم')->sortable()->toggleable(),
                TextColumn::make('created_at')->label('التسجيل')->since()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('الحالة')->options(UserStatus::options()),
                SelectFilter::make('id_verification_status')->label('توثيق الهوية')->options(IdVerificationStatus::options()),
                SelectFilter::make('city_id')->label('المدينة')->relationship('city', 'name_ar')->searchable()->preload(),
                SelectFilter::make('gender')->label('الجنس')->options(Gender::options()),
            ])
            ->recordActions([ViewAction::make()]);
    }

    /** Suspend / ban / reactivate — used on the view page. Logged in the audit log. */
    public static function enforcementActions(): array
    {
        return [
            Action::make('warn')
                ->label('تنبيه')->color('gray')->icon(Heroicon::OutlinedExclamationTriangle)
                ->schema([Textarea::make('reason')->label('نص التنبيه (يصل للمستخدم)')->required()->maxLength(500)])
                ->action(function (User $record, array $data) {
                    app(EnforcementService::class)->warn($record, $data['reason'], Auth::guard('admin')->user());
                    Notification::make()->title('تم إرسال التنبيه')->success()->send();
                }),
            Action::make('suspend')
                ->label('إيقاف مؤقت')->color('warning')->icon(Heroicon::OutlinedPauseCircle)
                ->visible(fn (User $record) => $record->status === UserStatus::Active)
                ->schema([
                    DateTimePicker::make('until')->label('حتى')->required()->minDate(now())->default(now()->addDays(7)),
                    Textarea::make('reason')->label('السبب (يصل للمستخدم)')->required()->maxLength(500),
                ])
                ->action(function (User $record, array $data) {
                    app(EnforcementService::class)->suspend($record, Carbon::parse($data['until']), $data['reason'], Auth::guard('admin')->user());
                    Notification::make()->title('تم إيقاف الحساب مؤقتاً')->success()->send();
                }),
            Action::make('ban')
                ->label('حظر')->color('danger')->icon(Heroicon::OutlinedNoSymbol)
                ->visible(fn (User $record) => $record->status !== UserStatus::Banned)
                ->requiresConfirmation()
                ->modalDescription('يُسجّل خروجه من كل الأجهزة، وتُلغى رحلاته القادمة وحجوزاته وارسالياته المفتوحة.')
                ->schema([Textarea::make('reason')->label('السبب')->required()->maxLength(500)])
                ->action(function (User $record, array $data) {
                    app(EnforcementService::class)->ban($record, $data['reason'], Auth::guard('admin')->user());
                    Notification::make()->title('تم حظر الحساب')->success()->send();
                }),
            Action::make('lift_booking_restriction')
                ->label('رفع إيقاف الحجز')->color('gray')->icon(Heroicon::OutlinedLockOpen)
                ->visible(fn (User $record) => $record->isBookingRestricted())
                ->requiresConfirmation()
                ->schema([Textarea::make('reason')->label('السبب')->required()->maxLength(500)])
                ->action(function (User $record, array $data) {
                    $record->forceFill(['booking_restricted_until' => null])->save();
                    app(AuditLogger::class)->log('user.booking_restriction_lifted', $record, ['reason' => $data['reason']]);
                    Notification::make()->title('تم رفع الإيقاف')->success()->send();
                }),
            Action::make('reactivate')
                ->label('إعادة التفعيل')->color('success')->icon(Heroicon::OutlinedCheckCircle)
                ->visible(fn (User $record) => $record->status !== UserStatus::Active)
                ->requiresConfirmation()
                ->schema([Textarea::make('reason')->label('السبب')->required()->maxLength(500)])
                ->action(function (User $record, array $data) {
                    app(EnforcementService::class)->reactivate($record, $data['reason'], Auth::guard('admin')->user());
                    Notification::make()->title('تم تفعيل الحساب')->success()->send();
                }),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'view' => Pages\ViewUser::route('/{record}'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
