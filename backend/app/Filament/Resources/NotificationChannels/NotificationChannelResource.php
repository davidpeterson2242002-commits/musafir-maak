<?php

namespace App\Filament\Resources\NotificationChannels;

use App\Enums\NotificationEvent;
use App\Filament\Concerns\RestrictedToRoles;
use App\Models\NotificationChannel;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Channel matrix (spec 5.14 / 9.9): which channels each notification event uses.
 * Toggles save immediately and are written to the audit log.
 */
class NotificationChannelResource extends Resource
{
    use RestrictedToRoles;

    protected static ?string $model = NotificationChannel::class;

    protected static ?string $slug = 'notification-channels';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBellAlert;

    protected static string|UnitEnum|null $navigationGroup = 'الإشعارات';

    protected static ?string $modelLabel = 'إشعار';

    protected static ?string $pluralModelLabel = 'قنوات الإشعارات';

    protected static ?int $navigationSort = 1;

    public static function canCreate(): bool
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
            ->defaultSort('id')
            ->paginated(false)
            ->description('اختر لكل إشعار القنوات التي يُرسل عبرها. رسائل واتساب مدفوعة لكل رسالة، والبريد يصل فقط لمن أضاف بريداً إلكترونياً.')
            ->columns([
                TextColumn::make('event')->label('الإشعار')->weight('bold')
                    ->formatStateUsing(fn (string $state) => NotificationEvent::labelFor($state))
                    ->description(fn (NotificationChannel $record) => $record->event),
                ToggleColumn::make('push')->label('إشعار التطبيق'),
                ToggleColumn::make('whatsapp')->label('واتساب'),
                ToggleColumn::make('email')->label('بريد إلكتروني'),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageNotificationChannels::route('/')];
    }
}
