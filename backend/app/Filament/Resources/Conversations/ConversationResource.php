<?php

namespace App\Filament\Resources\Conversations;

use App\Enums\AdminRole;
use App\Filament\Concerns\RestrictedToRoles;
use App\Filament\Support\ChatLog;
use App\Models\Conversation;
use App\Models\Report;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Flagged chats (spec 9.6). Admins can open a chat only when it has an automatic flag
 * or a report — never by browsing (spec 5.8).
 */
class ConversationResource extends Resource
{
    use RestrictedToRoles;

    protected static array $allowedRoles = [AdminRole::Support];

    protected static ?string $model = Conversation::class;

    protected static ?string $slug = 'flagged-chats';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static string|UnitEnum|null $navigationGroup = 'البلاغات والدعم';

    protected static ?string $modelLabel = 'محادثة';

    protected static ?string $pluralModelLabel = 'محادثات معلّمة';

    protected static ?int $navigationSort = 2;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where(fn ($q) => $q->where('has_flagged', true)
            ->orWhereIn('id', Report::where('subject_type', 'conversation')->select('subject_id')));
    }

    public static function canView(Model $record): bool
    {
        return static::canAccess() && ($record->has_flagged || Report::where('subject_type', 'conversation')->where('subject_id', $record->id)->exists()
            || Report::where('subject_type', $record->subject_type)->where('subject_id', $record->subject_id)->exists());
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
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['customer', 'driverUser'])
                ->withCount(['messages as flagged_count' => fn ($q) => $q->where('flagged', true)]))
            ->defaultSort('last_message_at', 'desc')
            ->columns([
                TextColumn::make('customer.name')->label('الراكب / المرسل'),
                TextColumn::make('driverUser.name')->label('السائق'),
                TextColumn::make('subject_type')->label('على')->formatStateUsing(fn ($state, $record) => ($state === 'booking' ? 'حجز #' : 'ارسالية #').$record->subject_id),
                TextColumn::make('flagged_count')->label('رسائل معلّمة')->badge()->color('danger'),
                TextColumn::make('last_message_at')->label('آخر رسالة')->since(),
            ])
            ->recordActions([ViewAction::make()]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('الأطراف')->columns(3)->schema([
                TextEntry::make('customer.name')->label('الراكب / المرسل'),
                TextEntry::make('driverUser.name')->label('السائق'),
                TextEntry::make('subject_type')->label('على')->formatStateUsing(fn ($state, $record) => ($state === 'booking' ? 'حجز #' : 'ارسالية #').$record->subject_id),
            ]),
            Section::make('الرسائل')->description('الرسائل الحمراء معلّمة: رقم قبل القبول أو كلمة من القائمة.')
                ->schema([ChatLog::entry(fn (Conversation $record) => $record)]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListConversations::route('/'),
            'view' => Pages\ViewConversation::route('/{record}'),
        ];
    }
}
