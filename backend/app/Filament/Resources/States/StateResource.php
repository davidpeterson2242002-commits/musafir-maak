<?php

namespace App\Filament\Resources\States;

use App\Filament\Concerns\RestrictedToRoles;
use App\Models\State;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use UnitEnum;

class StateResource extends Resource
{
    use RestrictedToRoles;

    protected static ?string $model = State::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMap;

    protected static string|UnitEnum|null $navigationGroup = 'المدن والطرق';

    protected static ?string $modelLabel = 'ولاية';

    protected static ?string $pluralModelLabel = 'الولايات';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name_ar')->label('الاسم بالعربي')->required()->maxLength(100),
            TextInput::make('name_en')->label('الاسم بالإنجليزي')->required()->maxLength(100),
            TextInput::make('sort')->label('الترتيب')->numeric()->default(0)->required(),
            Toggle::make('enabled')->label('مفعّلة')->default(true)
                ->helperText('إيقاف الولاية يوقف كل مدنها وطرقها في التطبيق.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort')
            ->columns([
                TextColumn::make('name_ar')->label('الولاية')->searchable(),
                TextColumn::make('name_en')->label('English')->searchable()->toggleable(),
                TextColumn::make('cities_count')->counts('cities')->label('عدد المدن'),
                ToggleColumn::make('enabled')->label('مفعّلة'),
                TextColumn::make('sort')->label('الترتيب')->sortable(),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageStates::route('/')];
    }
}
