<?php

namespace App\Filament\Resources\NotificationLogs;

use App\Enums\AdminRole;
use App\Enums\NotificationChannelType;
use App\Enums\NotificationEvent;
use App\Filament\Concerns\RestrictedToRoles;
use App\Models\NotificationLog;
use App\Support\Phone;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/** Read-only log of every notification attempt: what was sent to whom, on which channel, and why it failed. */
class NotificationLogResource extends Resource
{
    use RestrictedToRoles;

    protected static array $allowedRoles = [AdminRole::Support];

    protected static ?string $model = NotificationLog::class;

    protected static ?string $slug = 'notification-logs';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static string|UnitEnum|null $navigationGroup = 'الإشعارات';

    protected static ?string $modelLabel = 'إشعار مُرسل';

    protected static ?string $pluralModelLabel = 'سجل الإشعارات';

    protected static ?int $navigationSort = 3;

    public const STATUSES = [
        NotificationLog::SENT => 'تم الإرسال',
        NotificationLog::FAILED => 'فشل',
        NotificationLog::SKIPPED => 'لم يُرسل',
    ];

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

    /** Failures in the last 24 hours. */
    public static function getNavigationBadge(): ?string
    {
        $n = NotificationLog::where('status', NotificationLog::FAILED)->where('created_at', '>=', now()->subDay())->count();

        return $n ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'إشعارات فشل إرسالها خلال 24 ساعة';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('user'))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('الوقت')
                    ->formatStateUsing(fn ($state) => local_time($state)->format('Y-m-d H:i')),
                TextColumn::make('user.name')->label('المستخدم')->placeholder('—')
                    ->description(fn (NotificationLog $record) => Phone::displayLtr($record->phone ?? $record->user?->phone))
                    ->searchable(query: fn (Builder $query, string $search) => $query->where(fn (Builder $q) => $q
                        ->where('phone', 'like', '%'.ltrim($search, '0').'%')
                        ->orWhereHas('user', fn (Builder $u) => $u->where('name', 'ilike', "%$search%")->orWhere('phone', 'like', '%'.ltrim($search, '0').'%')))),
                TextColumn::make('event')->label('الإشعار')
                    ->formatStateUsing(fn (string $state) => NotificationEvent::labelFor($state))
                    ->description(fn (NotificationLog $record) => $record->title),
                TextColumn::make('channel')->label('القناة')->badge()->color('gray')
                    ->formatStateUsing(fn (string $state) => NotificationChannelType::tryFrom($state)?->label() ?? $state),
                TextColumn::make('status')->label('الحالة')->badge()
                    ->formatStateUsing(fn (string $state) => self::STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        NotificationLog::SENT => 'success', NotificationLog::FAILED => 'danger', default => 'gray',
                    }),
                TextColumn::make('error')->label('السبب')->placeholder('—')->wrap()->limit(160),
            ])
            ->filters([
                SelectFilter::make('status')->label('الحالة')->options(self::STATUSES),
                SelectFilter::make('channel')->label('القناة')->options(NotificationChannelType::options()),
                SelectFilter::make('event')->label('الإشعار')->searchable()
                    ->options(collect(NotificationEvent::cases())->mapWithKeys(fn ($e) => [$e->value => $e->label()])
                        ->put('otp_code', NotificationEvent::labelFor('otp_code'))
                        ->put('parcel_delivery_code', NotificationEvent::labelFor('parcel_delivery_code'))->all()),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListNotificationLogs::route('/')];
    }
}
