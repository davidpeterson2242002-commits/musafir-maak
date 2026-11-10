<?php

namespace App\Filament\Resources\Broadcasts;

use App\Enums\NotificationChannelType;
use App\Filament\Concerns\RestrictedToRoles;
use App\Models\Broadcast;
use App\Models\City;
use App\Models\NotificationLog;
use App\Services\BroadcastService;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/** Broadcast messages to user segments (spec 9.10). Super admin only: WhatsApp broadcasts cost money per recipient. */
class BroadcastResource extends Resource
{
    use RestrictedToRoles;

    protected static ?string $model = Broadcast::class;

    protected static ?string $slug = 'broadcasts';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static string|UnitEnum|null $navigationGroup = 'الإشعارات';

    protected static ?string $modelLabel = 'إشعار جماعي';

    protected static ?string $pluralModelLabel = 'الإشعارات الجماعية';

    protected static ?int $navigationSort = 2;

    public static function canCreate(): bool
    {
        return false; // created through the "send" action on the list page
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    /** Form of the "send broadcast" action. */
    public static function composeSchema(): array
    {
        $cities = fn () => City::where('enabled', true)->orderBy('sort')->pluck('name_ar', 'id');

        return [
            Select::make('segment')->label('إلى')->options(Broadcast::SEGMENTS)->required()->native(false)->live()
                ->default('all_users')->selectablePlaceholder(false),
            Select::make('city_id')->label('المدينة')->options($cities)->searchable()->live()
                ->visible(fn (Get $get) => in_array($get('segment'), ['users_in_city', 'drivers_in_city'], true))
                ->required(fn (Get $get) => in_array($get('segment'), ['users_in_city', 'drivers_in_city'], true)),
            Select::make('origin_city_id')->label('من')->options($cities)->searchable()->live()
                ->visible(fn (Get $get) => $get('segment') === 'searched_route')
                ->required(fn (Get $get) => $get('segment') === 'searched_route'),
            Select::make('destination_city_id')->label('إلى مدينة')->options($cities)->searchable()->live()
                ->visible(fn (Get $get) => $get('segment') === 'searched_route')
                ->required(fn (Get $get) => $get('segment') === 'searched_route')
                ->different('origin_city_id'),
            TextEntry::make('audience')->label('عدد المستلمين الآن')
                ->state(fn (Get $get) => number_format(self::audienceCount($get))),
            CheckboxList::make('channels')->label('القنوات')->required()->default(['push'])
                ->options(NotificationChannelType::options())
                ->descriptions([
                    'push' => 'مجاني — يصل لمن فتح التطبيق وسمح بالإشعارات.',
                    'whatsapp' => 'مدفوع لكل مستلم، ويحتاج قالباً تسويقياً معتمداً من واتساب.',
                    'email' => 'يصل فقط لمن أضاف بريداً إلكترونياً.',
                ]),
            TextInput::make('title')->label('العنوان')->required()->maxLength(60),
            Textarea::make('body')->label('نص الرسالة')->required()->maxLength(500)->rows(4),
        ];
    }

    public static function filtersFrom(array|Get $source): array
    {
        $value = fn (string $key) => $source instanceof Get ? $source($key) : ($source[$key] ?? null);
        $segment = $value('segment');

        return match (true) {
            in_array($segment, ['users_in_city', 'drivers_in_city'], true) => ['city_id' => (int) $value('city_id')],
            $segment === 'searched_route' => [
                'origin_city_id' => (int) $value('origin_city_id'), 'destination_city_id' => (int) $value('destination_city_id'),
            ],
            default => [],
        };
    }

    private static function audienceCount(Get $get): int
    {
        $segment = $get('segment');

        return isset(Broadcast::SEGMENTS[$segment])
            ? app(BroadcastService::class)->audience($segment, self::filtersFrom($get))->count()
            : 0;
    }

    public static function infolist(Schema $schema): Schema
    {
        $count = fn (Broadcast $record, string $status) => number_format(
            NotificationLog::where('broadcast_id', $record->id)->where('status', $status)->count()
        );

        return $schema->components([
            TextEntry::make('title')->label('العنوان')->weight('bold'),
            TextEntry::make('created_at')->label('أُرسل في')->formatStateUsing(fn ($state) => local_time($state)->format('Y-m-d H:i')),
            TextEntry::make('body')->label('النص')->columnSpanFull(),
            TextEntry::make('segment')->label('إلى')->formatStateUsing(fn (Broadcast $record) => self::segmentLabel($record)),
            TextEntry::make('channels')->label('القنوات')->badge()
                ->formatStateUsing(fn (string $state) => NotificationChannelType::tryFrom($state)?->label() ?? $state),
            TextEntry::make('admin.name')->label('المشرف')->placeholder('—'),
            TextEntry::make('status')->label('الحالة')->badge()->formatStateUsing(fn (string $state) => Broadcast::STATUSES[$state] ?? $state),
            TextEntry::make('recipients_count')->label('المستلمون')->numeric(),
            TextEntry::make('sent')->label('رسائل وصلت')->state(fn (Broadcast $record) => $count($record, NotificationLog::SENT))->color('success'),
            TextEntry::make('skipped')->label('لم تُرسل (بلا جهاز/بريد)')->state(fn (Broadcast $record) => $count($record, NotificationLog::SKIPPED)),
            TextEntry::make('failed')->label('فشلت')->state(fn (Broadcast $record) => $count($record, NotificationLog::FAILED))->color('danger'),
        ]);
    }

    public static function segmentLabel(Broadcast $record): string
    {
        $label = Broadcast::SEGMENTS[$record->segment] ?? $record->segment;
        $filters = $record->filters ?? [];
        $city = fn (string $key) => isset($filters[$key]) ? City::find($filters[$key])?->name_ar : null;

        return match (true) {
            isset($filters['city_id']) => $label.': '.$city('city_id'),
            isset($filters['origin_city_id']) => $label.': '.$city('origin_city_id').' ← '.$city('destination_city_id'),
            default => $label,
        };
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('admin'))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('الوقت')->formatStateUsing(fn ($state) => local_time($state)->format('Y-m-d H:i')),
                TextColumn::make('title')->label('العنوان')->weight('bold')->description(fn (Broadcast $record) => str($record->body)->limit(80)),
                TextColumn::make('segment')->label('إلى')->formatStateUsing(fn (Broadcast $record) => Broadcast::SEGMENTS[$record->segment] ?? $record->segment),
                TextColumn::make('channels')->label('القنوات')->badge()->color('gray')
                    ->formatStateUsing(fn (string $state) => NotificationChannelType::tryFrom($state)?->label() ?? $state),
                TextColumn::make('recipients_count')->label('المستلمون')->numeric(),
                TextColumn::make('status')->label('الحالة')->badge()
                    ->formatStateUsing(fn (string $state) => Broadcast::STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        'sent' => 'success', 'failed' => 'danger', default => 'warning',
                    }),
                TextColumn::make('admin.name')->label('المشرف')->placeholder('—'),
            ])
            ->recordActions([ViewAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListBroadcasts::route('/')];
    }
}
