<?php

namespace App\Filament\Resources\Verifications;

use App\Enums\AdminRole;
use App\Enums\DocumentKind;
use App\Enums\ReviewStatus;
use App\Filament\Concerns\RestrictedToRoles;
use App\Models\Document;
use App\Models\DriverProfile;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VerificationReview;
use App\Support\Phone;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use UnitEnum;

/**
 * Verification queue (spec 9.2): passenger IDs, driver applications, vehicles.
 * Oldest first, higher priority (e.g. pending first booking) on top.
 */
class VerificationResource extends Resource
{
    use RestrictedToRoles;

    protected static array $allowedRoles = [AdminRole::Reviewer];

    protected static ?string $model = VerificationReview::class;

    protected static ?string $slug = 'verifications';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static string|UnitEnum|null $navigationGroup = 'المستخدمون';

    protected static ?string $modelLabel = 'طلب توثيق';

    protected static ?string $pluralModelLabel = 'قائمة التوثيق';

    protected static ?int $navigationSort = -1;

    public static function getNavigationBadge(): ?string
    {
        $count = VerificationReview::pending()->count();

        return $count ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
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

    /** The app user behind a review, whatever its subject. */
    public static function ownerOf(VerificationReview $review): ?User
    {
        return match (true) {
            $review->subject instanceof User => $review->subject,
            $review->subject instanceof DriverProfile => $review->subject->user,
            $review->subject instanceof Vehicle => $review->subject->driverProfile?->user,
            default => null,
        };
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with([
            'assignee', 'reviewer',
            'subject' => fn (MorphTo $morph) => $morph->morphWith([
                DriverProfile::class => ['user'],
                Vehicle::class => ['driverProfile.user'],
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort(fn (Builder $query) => $query->orderByDesc('priority')->orderBy('created_at'))
            ->columns([
                TextColumn::make('subject_type')->label('النوع')->badge()
                    ->formatStateUsing(fn ($state, VerificationReview $record) => $record->kindLabel())
                    ->color(fn ($state) => match ($state) {
                        'driver_profile' => 'primary', 'vehicle' => 'info', default => 'gray',
                    }),
                TextColumn::make('owner_name')->label('الاسم')
                    ->state(fn (VerificationReview $record) => self::ownerOf($record)?->name ?? '—'),
                TextColumn::make('owner_phone')->label('الهاتف')
                    ->state(fn (VerificationReview $record) => ($p = self::ownerOf($record)?->phone) ? Phone::displayLtr($p) : '—'),
                TextColumn::make('detail')->label('التفاصيل')
                    ->state(fn (VerificationReview $record) => match (true) {
                        $record->subject instanceof Vehicle => $record->subject->label(),
                        default => $record->id_document_type?->label(),
                    })->placeholder('—'),
                TextColumn::make('priority')->label('أولوية')->badge()->color('danger')
                    ->state(fn (VerificationReview $record) => $record->priority > 0 ? 'عاجل' : null),
                TextColumn::make('created_at')->label('أُرسل')->since()->sortable(),
                TextColumn::make('status')->label('الحالة')->badge()
                    ->formatStateUsing(fn (ReviewStatus $state) => $state->label())->color(fn (ReviewStatus $state) => $state->color()),
                TextColumn::make('assignee.name')->label('يراجعه')->placeholder('—')
                    ->state(fn (VerificationReview $record) => $record->isPending() && $record->assigned_at?->gt(now()->subMinutes(VerificationReview::CLAIM_MINUTES)) ? $record->assignee?->name : null),
                TextColumn::make('reviewer.name')->label('راجعه')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([ViewAction::make()->label('مراجعة')])
            ->recordUrl(fn (VerificationReview $record) => self::getUrl('view', ['record' => $record]))
            ->poll('30s');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('الطلب')->columns(4)->schema([
                TextEntry::make('kind')->label('النوع')->state(fn (VerificationReview $record) => $record->kindLabel())->badge(),
                TextEntry::make('status')->label('الحالة')->badge()
                    ->formatStateUsing(fn (ReviewStatus $state) => $state->label())->color(fn (ReviewStatus $state) => $state->color()),
                TextEntry::make('created_at')->label('أُرسل')->dateTime('Y-m-d H:i')->timezone('Africa/Khartoum'),
                TextEntry::make('reviewer.name')->label('راجعه')->placeholder('—'),
                TextEntry::make('reasons')->label('الأسباب')->listWithLineBreaks()->bulleted()->placeholder('—')
                    ->visible(fn (VerificationReview $record) => ! $record->isPending())->columnSpan(2),
                TextEntry::make('note')->label('ملاحظة للمستخدم')->placeholder('—')
                    ->visible(fn (VerificationReview $record) => ! $record->isPending())->columnSpan(2),
            ]),

            Section::make('صاحب الطلب')->columns(4)->schema([
                TextEntry::make('owner.name')->label('الاسم')->state(fn (VerificationReview $record) => self::ownerOf($record)?->name),
                TextEntry::make('owner.phone')->label('الهاتف')->state(fn (VerificationReview $record) => ($p = self::ownerOf($record)?->phone) ? Phone::displayLtr($p) : null)->copyable(),
                TextEntry::make('owner.gender')->label('الجنس')->state(fn (VerificationReview $record) => self::ownerOf($record)?->gender?->label()),
                TextEntry::make('owner.since')->label('عضو منذ')->state(fn (VerificationReview $record) => self::ownerOf($record)?->created_at?->toDateString()),
            ]),

            // Identity: selfie side by side with the ID (passenger IDs, and driver applications that include identity).
            Section::make('الهوية')
                ->description(fn (VerificationReview $record) => 'قارن السيلفي بصورة الهوية. نوع الهوية: '.$record->id_document_type?->label())
                ->visible(fn (VerificationReview $record) => $record->id_document_type !== null)
                ->schema([
                    Grid::make(3)->schema(fn (VerificationReview $record) => collect(
                        $record->id_document_type?->requiredKinds() ?? []
                    )->map(fn (DocumentKind $kind) => self::docEntry($kind, fn (VerificationReview $r) => self::ownerOf($r)))->all()),
                ]),

            Section::make('رخصة القيادة')
                ->visible(fn (VerificationReview $record) => $record->subject instanceof DriverProfile)
                ->schema([
                    Grid::make(3)->schema([self::docEntry(DocumentKind::DrivingLicence, fn (VerificationReview $r) => $r->subject)]),
                ]),

            // Vehicles in a driver application, or the single vehicle under review.
            Section::make('العربيات')
                ->visible(fn (VerificationReview $record) => ! $record->subject instanceof User)
                ->schema([
                    RepeatableEntry::make('vehicles_under_review')
                        ->hiddenLabel()
                        ->state(fn (VerificationReview $record) => match (true) {
                            $record->subject instanceof Vehicle => [$record->subject->load('documents')],
                            $record->subject instanceof DriverProfile => $record->subject->vehicles()->with('documents')->orderBy('id')->get()->all(),
                            default => [],
                        })
                        ->schema([
                            Grid::make(6)->schema([
                                TextEntry::make('type')->label('النوع')->formatStateUsing(fn ($state) => $state?->label()),
                                TextEntry::make('make')->label('الماركة')->formatStateUsing(fn ($state, Vehicle $record) => "{$record->make} {$record->model}"),
                                TextEntry::make('color')->label('اللون'),
                                TextEntry::make('year')->label('السنة')->placeholder('—'),
                                TextEntry::make('plate_number')->label('اللوحة')->weight('bold')->copyable(),
                                TextEntry::make('seats')->label('المقاعد'),
                            ]),
                            Grid::make(5)->schema(collect(DocumentKind::vehicleKinds())
                                ->map(fn (DocumentKind $kind) => self::docEntry($kind, fn (Vehicle $v) => $v, height: 160))->all()),
                        ]),
                ]),

            Section::make('الطلبات السابقة')
                ->collapsed()
                ->visible(fn (VerificationReview $record) => self::history($record)->isNotEmpty())
                ->schema([
                    RepeatableEntry::make('history')->hiddenLabel()
                        ->state(fn (VerificationReview $record) => self::history($record)->all())
                        ->schema([
                            Grid::make(4)->schema([
                                TextEntry::make('created_at')->label('أُرسل')->date(),
                                TextEntry::make('status')->label('القرار')->badge()
                                    ->formatStateUsing(fn (ReviewStatus $state) => $state->label())->color(fn (ReviewStatus $state) => $state->color()),
                                TextEntry::make('reasons')->label('الأسباب')->listWithLineBreaks()->placeholder('—'),
                                TextEntry::make('note')->label('ملاحظة')->placeholder('—'),
                            ]),
                        ]),
                ]),
        ]);
    }

    /** Image of one document, opening the full-size signed URL in a new tab. */
    private static function docEntry(DocumentKind $kind, \Closure $owner, int $height = 220): ImageEntry
    {
        $doc = function ($record) use ($owner, $kind): ?Document {
            $model = $owner($record);

            return $model?->documents()->where('kind', $kind)->first();
        };

        return ImageEntry::make('doc_'.$kind->value)
            ->label($kind->label())
            ->state(fn ($record) => $doc($record)?->temporaryUrl())
            ->url(fn ($record) => $doc($record)?->temporaryUrl(), shouldOpenInNewTab: true)
            ->imageHeight($height)
            ->extraImgAttributes(['style' => 'object-fit: contain; max-width: 100%; border-radius: 8px; background: #f4f4f5', 'loading' => 'lazy'])
            ->placeholder('لم يُرفع');
    }

    private static function history(VerificationReview $record)
    {
        return VerificationReview::where('subject_type', $record->subject_type)
            ->where('subject_id', $record->subject_id)
            ->whereKeyNot($record->id)
            ->latest()->get();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVerifications::route('/'),
            'view' => Pages\ReviewVerification::route('/{record}'),
        ];
    }
}
