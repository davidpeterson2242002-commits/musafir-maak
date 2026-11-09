<?php

namespace App\Filament\Resources\Routes;

use App\Filament\Concerns\RestrictedToRoles;
use App\Models\City;
use App\Models\TravelRoute;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;
use UnitEnum;

class RouteResource extends Resource
{
    use RestrictedToRoles;

    protected static ?string $model = TravelRoute::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static string|UnitEnum|null $navigationGroup = 'المدن والطرق';

    protected static ?string $modelLabel = 'طريق';

    protected static ?string $pluralModelLabel = 'الطرق';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        $cities = fn () => City::with('state')->orderBy('name_ar')->get()
            ->mapWithKeys(fn (City $c) => [$c->id => "{$c->name_ar} ({$c->state->name_ar})"]);

        return $schema->components([
            Select::make('origin_city_id')->label('من')->options($cities)->searchable()->required()
                ->different('destination_city_id')
                ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Get $get) => $rule->where('destination_city_id', $get('destination_city_id')))
                ->validationMessages(['unique' => 'هذا الطريق موجود مسبقاً.', 'different' => 'المدينتان يجب أن تكونا مختلفتين.']),
            Select::make('destination_city_id')->label('إلى')->options($cities)->searchable()->required(),
            Toggle::make('enabled')->label('مفعّل')->default(true)->live(),
            TextInput::make('closure_message')->label('رسالة الإيقاف للمستخدمين')
                ->placeholder('هذا الطريق موقوف مؤقتاً')->maxLength(200)
                ->visible(fn (Get $get) => ! $get('enabled')),
            TextInput::make('max_price_per_seat')->label('أقصى سعر للمقعد (جنيه)')->numeric()->minValue(1)
                ->helperText('اتركه فارغاً بدون حد أقصى.'),
            Toggle::make('is_popular')->label('يظهر كطريق شائع في الرئيسية'),
            TextInput::make('sort')->label('الترتيب')->numeric()->default(0)->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['origin.state', 'destination.state']))
            ->defaultSort('sort')
            ->columns([
                TextColumn::make('origin.name_ar')->label('من')->searchable(),
                TextColumn::make('destination.name_ar')->label('إلى')->searchable(),
                ToggleColumn::make('enabled')->label('مفعّل'),
                TextColumn::make('closure_message')->label('رسالة الإيقاف')->limit(40)->placeholder('—')->toggleable(),
                TextColumn::make('max_price_per_seat')->label('أقصى سعر')->numeric()->suffix(' ج')->placeholder('بدون'),
                IconColumn::make('is_popular')->label('شائع')->boolean(),
                TextColumn::make('sort')->label('الترتيب')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('enabled')->label('مفعّل'),
                TernaryFilter::make('is_popular')->label('شائع'),
                SelectFilter::make('origin_city_id')->label('من')->relationship('origin', 'name_ar')->searchable()->preload(),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('reverse')
                    ->label('إضافة الاتجاه المعاكس')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->visible(fn (TravelRoute $r) => ! TravelRoute::between($r->destination_city_id, $r->origin_city_id)->exists())
                    ->action(function (TravelRoute $r) {
                        TravelRoute::create([
                            'origin_city_id' => $r->destination_city_id,
                            'destination_city_id' => $r->origin_city_id,
                            'enabled' => $r->enabled,
                            'max_price_per_seat' => $r->max_price_per_seat,
                            'is_popular' => $r->is_popular,
                            'sort' => $r->sort,
                        ]);
                        Notification::make()->title('تمت إضافة الاتجاه المعاكس')->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageRoutes::route('/')];
    }
}
