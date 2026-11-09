<?php

namespace App\Filament\Pages;

use App\Enums\SettingType;
use App\Filament\Concerns\RestrictedToRoles;
use App\Support\Settings;
use App\Support\SettingsRegistry;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;
use UnitEnum;

/**
 * All business parameters (spec 9.9), generated from SettingsRegistry.
 * Every change is written to the audit log by the Setting model.
 */
class ManageSettings extends Page
{
    use RestrictedToRoles;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = 'النظام';

    protected static ?string $navigationLabel = 'الإعدادات';

    protected static ?string $title = 'إعدادات التشغيل';

    protected static ?string $slug = 'settings';

    protected static ?int $navigationSort = -1;

    /** @var array<string, mixed> form state, keyed by field name (dots replaced) */
    public ?array $data = [];

    public function mount(): void
    {
        $settings = app(Settings::class);
        $state = [];

        foreach (SettingsRegistry::definitions() as $key => $def) {
            $state[self::field($key)] = $settings->get($key);
        }

        $this->form->fill($state);
    }

    public function form(Schema $schema): Schema
    {
        $byGroup = collect(SettingsRegistry::definitions())->groupBy(fn ($d) => $d['group'], preserveKeys: true);

        $tabs = collect(SettingsRegistry::GROUPS)
            ->filter(fn ($label, $group) => $byGroup->has($group))
            ->map(fn ($label, $group) => Tab::make($label)->schema([
                Grid::make(2)->schema(
                    $byGroup[$group]->map(fn ($def, $key) => $this->component($key, $def))->values()->all()
                ),
            ]))
            ->values()->all();

        return $schema
            ->components([Tabs::make('groups')->tabs($tabs)->persistTabInQueryString()])
            ->statePath('data');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('save')->label('حفظ التغييرات')->submit('save')->keyBindings(['mod+s']),
                    ])->sticky(),
                ]),
        ]);
    }

    public function save(): void
    {
        $state = $this->form->getState();
        $settings = app(Settings::class);
        $changed = 0;

        DB::transaction(function () use ($state, $settings, &$changed) {
            foreach (SettingsRegistry::definitions() as $key => $def) {
                $field = self::field($key);
                if (! array_key_exists($field, $state)) {
                    continue;
                }

                $value = $def['type']->cast($state[$field]);

                if ($value !== $settings->get($key)) {
                    $settings->set($key, $value);
                    $changed++;
                }
            }
        });

        Notification::make()
            ->title($changed ? "تم حفظ $changed تغيير" : 'لا توجد تغييرات')
            ->body($changed ? 'تُطبَّق التغييرات فوراً على العمليات الجديدة.' : null)
            ->success()
            ->send();
    }

    private function component(string $key, array $def): Field
    {
        $name = self::field($key);

        $field = match ($def['type']) {
            SettingType::Integer => TextInput::make($name)->integer()->required(),
            SettingType::Decimal => TextInput::make($name)->numeric()->step(0.01)->required(),
            SettingType::Boolean => Toggle::make($name),
            SettingType::String => TextInput::make($name)->maxLength(255),
            SettingType::Text => Textarea::make($name)->rows(8)->columnSpanFull(),
            SettingType::Select => Select::make($name)->options($def['options'])->required()->native(false)->selectablePlaceholder(false),
            SettingType::List => TagsInput::make($name)->reorderable()->placeholder('اكتب عنصراً ثم Enter')
                ->nestedRecursiveRules(['max:200'])->columnSpanFull(),
        };

        if (isset($def['min']) && method_exists($field, 'minValue')) {
            $field->minValue($def['min']);
        }
        if (isset($def['max']) && method_exists($field, 'maxValue')) {
            $field->maxValue($def['max']);
        }

        return $field->label($def['label'])->helperText($def['help'] ?? null);
    }

    /** Settings keys contain dots, which would nest the form state. */
    private static function field(string $key): string
    {
        return str_replace('.', '__', $key);
    }
}
