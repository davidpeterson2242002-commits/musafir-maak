<?php

namespace App\Filament\Resources\Cities;

use App\Filament\Concerns\RestrictedToRoles;
use App\Models\City;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

class CityResource extends Resource
{
    use RestrictedToRoles;

    protected static ?string $model = City::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static string|UnitEnum|null $navigationGroup = 'المدن والطرق';

    protected static ?string $modelLabel = 'مدينة';

    protected static ?string $pluralModelLabel = 'المدن';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('state_id')->label('الولاية')->relationship('state', 'name_ar')->searchable()->preload()->required(),
            TextInput::make('name_ar')->label('الاسم بالعربي')->required()->maxLength(100),
            TextInput::make('name_en')->label('الاسم بالإنجليزي')->required()->maxLength(100),
            TextInput::make('sort')->label('الترتيب داخل الولاية')->numeric()->default(0)->required(),
            Toggle::make('enabled')->label('مفعّلة')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('state'))
            ->defaultSort('state_id')
            ->columns([
                TextColumn::make('name_ar')->label('المدينة')->searchable()->sortable(),
                TextColumn::make('name_en')->label('English')->searchable()->toggleable(),
                TextColumn::make('state.name_ar')->label('الولاية')->sortable(),
                ToggleColumn::make('enabled')->label('مفعّلة'),
                TextColumn::make('sort')->label('الترتيب')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('state_id')->label('الولاية')->relationship('state', 'name_ar'),
                TernaryFilter::make('enabled')->label('مفعّلة'),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageCities::route('/')];
    }
}
