<?php

namespace App\Filament\Resources\Admins;

use App\Enums\AdminRole;
use App\Filament\Concerns\RestrictedToRoles;
use App\Models\Admin;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class AdminResource extends Resource
{
    use RestrictedToRoles;

    protected static ?string $model = Admin::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|UnitEnum|null $navigationGroup = 'النظام';

    protected static ?string $modelLabel = 'مشرف';

    protected static ?string $pluralModelLabel = 'المشرفون';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('الاسم')->required()->maxLength(100),
            TextInput::make('email')->label('البريد الإلكتروني')->email()->required()->unique(ignoreRecord: true),
            Select::make('role')->label('الصلاحية')->options(AdminRole::options())->required()->default(AdminRole::Reviewer->value),
            TextInput::make('password')->label('كلمة المرور')->password()->revealable()
                ->minLength(10)
                ->required(fn (string $operation) => $operation === 'create')
                ->dehydrated(fn (?string $state) => filled($state))
                ->helperText(fn (string $operation) => $operation === 'edit' ? 'اتركها فارغة لعدم التغيير.' : null),
            Toggle::make('is_active')->label('الحساب نشط')->default(true)
                ->disabled(fn (?Admin $record) => $record?->is(Auth::guard('admin')->user())),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('الاسم')->searchable(),
                TextColumn::make('email')->label('البريد')->searchable(),
                TextColumn::make('role')->label('الصلاحية')->badge()->formatStateUsing(fn (AdminRole $state) => $state->label()),
                IconColumn::make('is_active')->label('نشط')->boolean(),
                TextColumn::make('created_at')->label('أُضيف')->since(),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageAdmins::route('/')];
    }
}
