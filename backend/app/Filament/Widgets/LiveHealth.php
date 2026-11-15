<?php

namespace App\Filament\Widgets;

use App\Support\ApiMetrics;
use Filament\Widgets\Widget;

/** "Right now": who is using the app, how loaded the server is, and how far from its limit. */
class LiveHealth extends Widget
{
    protected string $view = 'filament.widgets.live-health';

    protected int|string|array $columnSpan = 'full';

    protected static bool $isDiscovered = false;

    protected function getViewData(): array
    {
        return ['m' => ApiMetrics::snapshot()];
    }
}
