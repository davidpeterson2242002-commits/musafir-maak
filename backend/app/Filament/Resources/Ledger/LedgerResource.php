<?php

namespace App\Filament\Resources\Ledger;

use App\Enums\AdminRole;
use App\Enums\LedgerType;
use App\Filament\Concerns\RestrictedToRoles;
use App\Models\LedgerEntry;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use UnitEnum;

/** All ledger entries, filterable and exportable to CSV (spec 9.7). Read-only. */
class LedgerResource extends Resource
{
    use RestrictedToRoles;

    protected static array $allowedRoles = [AdminRole::Finance];

    protected static ?string $model = LedgerEntry::class;

    protected static ?string $slug = 'ledger';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static string|UnitEnum|null $navigationGroup = 'المالية';

    protected static ?string $modelLabel = 'قيد';

    protected static ?string $pluralModelLabel = 'دفتر الحسابات';

    protected static ?int $navigationSort = 3;

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
        $tz = config('app.display_timezone');

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['driverProfile.user', 'admin']))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('الوقت')->dateTime('Y-m-d H:i')->timezone($tz)->sortable(),
                TextColumn::make('driverProfile.user.name')->label('السائق')->searchable(),
                TextColumn::make('type')->label('النوع')->badge()
                    ->formatStateUsing(fn (LedgerType $state) => $state->label())->color(fn (LedgerType $state) => $state->color()),
                TextColumn::make('bucket')->label('الحساب')->formatStateUsing(fn ($state) => $state === 'wallet' ? 'المحفظة' : 'المستحق'),
                TextColumn::make('amount')->label('المبلغ')->numeric()->suffix(' ج')
                    ->color(fn (LedgerEntry $record) => $record->amount > 0 && $record->bucket === 'balance' ? 'danger' : 'success'),
                TextColumn::make('balance_after')->label('المستحق بعدها')->numeric(),
                TextColumn::make('wallet_after')->label('المحفظة بعدها')->numeric()->toggleable(),
                TextColumn::make('note')->label('البيان')->limit(40)->tooltip(fn (LedgerEntry $record) => $record->note),
                TextColumn::make('admin.name')->label('بواسطة')->placeholder('النظام')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')->label('النوع')->options(LedgerType::options()),
                SelectFilter::make('bucket')->label('الحساب')->options(['balance' => 'المستحق', 'wallet' => 'المحفظة']),
                SelectFilter::make('driver_profile_id')->label('السائق')->searchable()
                    ->relationship('driverProfile', 'id', fn (Builder $query) => $query->with('user'))
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->user?->name ?? "#{$record->id}"),
                Filter::make('date')->label('التاريخ')
                    ->schema([DatePicker::make('from')->label('من'), DatePicker::make('until')->label('إلى')])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn ($q, $d) => $q->where('created_at', '>=', Carbon::parse($d, $tz)->startOfDay()->utc()))
                        ->when($data['until'] ?? null, fn ($q, $d) => $q->where('created_at', '<=', Carbon::parse($d, $tz)->endOfDay()->utc()))),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListLedger::route('/')];
    }
}
