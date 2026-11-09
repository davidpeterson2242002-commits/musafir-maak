<?php

namespace App\Filament\Resources\PaymentMethods;

use App\Enums\AdminRole;
use App\Filament\Concerns\RestrictedToRoles;
use App\Models\PaymentMethod;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use UnitEnum;

/** Payment methods (spec 1.5, 9.9): add / edit / enable / disable / reorder. */
class PaymentMethodResource extends Resource
{
    use RestrictedToRoles;

    protected static array $allowedRoles = [AdminRole::Finance];

    protected static ?string $model = PaymentMethod::class;

    protected static ?string $slug = 'payment-methods';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static string|UnitEnum|null $navigationGroup = 'المالية';

    protected static ?string $modelLabel = 'طريقة دفع';

    protected static ?string $pluralModelLabel = 'طرق الدفع';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('الاسم')->required()->maxLength(100),
            TextInput::make('account_name')->label('اسم الحساب')->maxLength(150),
            TextInput::make('account_number')->label('رقم الحساب / الرقم')->maxLength(100),
            Toggle::make('requires_reference')->label('يتطلب رقم عملية')->default(true),
            Textarea::make('instructions')->label('تعليمات للسائق')->rows(3)->columnSpanFull(),
            TagsInput::make('agent_locations')->label('مواقع الوكلاء / المكاتب')->placeholder('اكتب الموقع ثم Enter')->columnSpanFull(),
            Toggle::make('enabled')->label('مفعّلة')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable('sort')
            ->defaultSort('sort')
            ->columns([
                TextColumn::make('name')->label('الطريقة')->weight('bold'),
                TextColumn::make('account_name')->label('اسم الحساب')->placeholder('—'),
                TextColumn::make('account_number')->label('الرقم')->placeholder('—')->copyable(),
                IconColumn::make('requires_reference')->label('رقم عملية')->boolean(),
                ToggleColumn::make('enabled')->label('مفعّلة'),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManagePaymentMethods::route('/')];
    }
}
