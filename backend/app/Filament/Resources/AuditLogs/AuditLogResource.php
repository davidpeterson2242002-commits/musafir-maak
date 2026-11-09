<?php

namespace App\Filament\Resources\AuditLogs;

use App\Filament\Concerns\RestrictedToRoles;
use App\Models\AuditLog;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/** Read-only view of every admin action. */
class AuditLogResource extends Resource
{
    use RestrictedToRoles;

    protected static ?string $model = AuditLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'النظام';

    protected static ?string $modelLabel = 'سجل';

    protected static ?string $pluralModelLabel = 'سجل التدقيق';

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

    public static function subjectLabel(?string $type): string
    {
        return match ($type ? class_basename($type) : null) {
            'Setting' => 'إعداد',
            'TravelRoute' => 'طريق',
            'City' => 'مدينة',
            'State' => 'ولاية',
            'Admin' => 'مشرف',
            'User' => 'مستخدم',
            null => '—',
            default => class_basename($type),
        };
    }

    public static function actionLabel(string $action): string
    {
        return match ($action) {
            'created' => 'إضافة',
            'updated' => 'تعديل',
            'deleted' => 'حذف',
            default => $action,
        };
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('created_at')->label('الوقت')->dateTime('Y-m-d H:i:s'),
            TextEntry::make('admin.name')->label('المشرف')->placeholder('النظام'),
            TextEntry::make('action')->label('الإجراء')->formatStateUsing(fn ($state) => self::actionLabel($state)),
            TextEntry::make('subject_type')->label('العنصر')
                ->formatStateUsing(fn ($state, AuditLog $record) => self::subjectLabel($state).' #'.$record->subject_id),
            TextEntry::make('ip_address')->label('IP')->placeholder('—'),
            KeyValueEntry::make('changes')->label('التغييرات')->columnSpanFull()
                ->keyLabel('الحقل')->valueLabel('قبل ← بعد')
                ->state(fn (AuditLog $record) => collect($record->changes ?? [])->map(function ($change) {
                    $fmt = fn ($v) => is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE) : (string) ($v ?? '∅');

                    return is_array($change) && array_key_exists('new', $change)
                        ? $fmt($change['old'] ?? null).'  ←  '.$fmt($change['new'])
                        : $fmt($change);
                })->all()),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('admin'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('الوقت')->dateTime('Y-m-d H:i')->sortable(),
                TextColumn::make('admin.name')->label('المشرف')->placeholder('النظام')->searchable(),
                TextColumn::make('action')->label('الإجراء')->badge()->formatStateUsing(fn ($state) => self::actionLabel($state)),
                TextColumn::make('subject_type')->label('العنصر')
                    ->formatStateUsing(fn ($state, AuditLog $record) => self::subjectLabel($state).' #'.$record->subject_id),
                TextColumn::make('changes')->label('الحقول')
                    ->state(fn (AuditLog $record) => implode('، ', array_keys($record->changes ?? [])))->limit(50),
            ])
            ->filters([
                SelectFilter::make('admin_id')->label('المشرف')->relationship('admin', 'name'),
                SelectFilter::make('action')->label('الإجراء')->options(['created' => 'إضافة', 'updated' => 'تعديل', 'deleted' => 'حذف']),
            ])
            ->recordActions([ViewAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListAuditLogs::route('/')];
    }
}
