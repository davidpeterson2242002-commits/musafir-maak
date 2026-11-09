<?php

namespace App\Filament\Resources\Ratings;

use App\Enums\AdminRole;
use App\Filament\Concerns\RestrictedToRoles;
use App\Models\Rating;
use App\Services\RatingService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/** Ratings moderation: hide abusive comments (average is recalculated). */
class RatingResource extends Resource
{
    use RestrictedToRoles;

    protected static array $allowedRoles = [AdminRole::Support];

    protected static ?string $model = Rating::class;

    protected static ?string $slug = 'ratings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedStar;

    protected static string|UnitEnum|null $navigationGroup = 'البلاغات والدعم';

    protected static ?string $modelLabel = 'تقييم';

    protected static ?string $pluralModelLabel = 'التقييمات';

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
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['rater', 'ratee']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('الوقت')->since(),
                TextColumn::make('ratee.name')->label('المُقيَّم')->searchable(),
                TextColumn::make('role')->label('كـ')->formatStateUsing(fn ($state) => $state === 'as_driver' ? 'سائق' : 'راكب'),
                TextColumn::make('stars')->label('النجوم')->formatStateUsing(fn ($state) => str_repeat('★', $state).str_repeat('☆', 5 - $state))
                    ->color(fn ($state) => $state <= 2 ? 'danger' : null),
                TextColumn::make('comment')->label('التعليق')->limit(60)->placeholder('—'),
                TextColumn::make('rater.name')->label('من'),
                IconColumn::make('hidden')->label('مخفي')->boolean()->trueColor('danger'),
            ])
            ->filters([
                SelectFilter::make('stars')->label('النجوم')->options([1 => '1', 2 => '2', 3 => '3', 4 => '4', 5 => '5']),
                SelectFilter::make('role')->label('كـ')->options(['as_driver' => 'سائق', 'as_passenger' => 'راكب']),
                TernaryFilter::make('hidden')->label('مخفي'),
            ])
            ->recordActions([
                Action::make('toggle_hidden')
                    ->label(fn (Rating $record) => $record->hidden ? 'إظهار' : 'إخفاء')
                    ->icon(Heroicon::OutlinedEyeSlash)->color('gray')
                    ->requiresConfirmation()
                    ->action(fn (Rating $record) => app(RatingService::class)->setHidden($record, ! $record->hidden, Auth::guard('admin')->user())),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListRatings::route('/')];
    }
}
