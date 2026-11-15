<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\ActivityChart;
use App\Filament\Widgets\ClosedRouteDemand;
use App\Filament\Widgets\FinanceStats;
use App\Filament\Widgets\LiveHealth;
use App\Filament\Widgets\OverviewStats;
use App\Filament\Widgets\TopRoutes;
use App\Filament\Widgets\TrafficChart;
use App\Filament\Widgets\TrustAlerts;
use App\Filament\Widgets\ZeroResultSearches;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Dashboard split into tabs instead of one long page. Widgets are lazy, and a widget inside
 * a hidden tab only loads when its tab is opened — so the page opens fast and only the
 * numbers you look at are computed.
 */
class Dashboard extends BaseDashboard
{
    protected static ?string $title = 'لوحة التحكم';

    /** @return array<string, array{label: string, icon: Heroicon, widgets: array<int, class-string>}> */
    public static function tabs(): array
    {
        return [
            'live' => ['label' => 'الآن', 'icon' => Heroicon::OutlinedSignal, 'widgets' => [LiveHealth::class, TrafficChart::class]],
            'overview' => ['label' => 'نظرة عامة', 'icon' => Heroicon::OutlinedSquares2x2, 'widgets' => [OverviewStats::class, ActivityChart::class]],
            'routes' => ['label' => 'الطرق والطلب', 'icon' => Heroicon::OutlinedMap, 'widgets' => [ClosedRouteDemand::class, ZeroResultSearches::class, TopRoutes::class]],
            'trust' => ['label' => 'الدعم والسلامة', 'icon' => Heroicon::OutlinedShieldCheck, 'widgets' => [TrustAlerts::class]],
            'finance' => ['label' => 'المالية', 'icon' => Heroicon::OutlinedBanknotes, 'widgets' => [FinanceStats::class]],
        ];
    }

    public function content(Schema $schema): Schema
    {
        $tabs = [];
        foreach (static::tabs() as $key => $tab) {
            $visible = array_values(array_filter($tab['widgets'], fn (string $w) => $w::canView()));
            if ($visible === []) {
                continue;
            }
            $tabs[] = Tab::make($tab['label'])
                ->id($key)
                ->icon($tab['icon'])
                ->schema(fn (): array => $this->getWidgetsSchemaComponents($visible))
                ->columns($this->getColumns());
        }

        return $schema->components([
            Tabs::make('dashboard')->tabs($tabs)->persistTabInQueryString('tab')->contained(false),
        ]);
    }
}
