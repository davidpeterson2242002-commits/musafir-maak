<?php

namespace App\Filament\Widgets;

use App\Models\MetricMinute;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Cache;

/** Requests and online users per hour, last 24 hours. */
class TrafficChart extends ChartWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected static bool $isDiscovered = false;

    protected ?string $heading = 'آخر 24 ساعة — الطلبات والمستخدمين في الساعة';

    protected ?string $pollingInterval = '60s';

    protected ?string $maxHeight = '260px';

    protected function getData(): array
    {
        return Cache::remember('dashboard.traffic24.v1', 60, function () {
            $rows = MetricMinute::where('minute', '>=', now()->subDay())->orderBy('minute')->get()
                ->groupBy(fn (MetricMinute $m) => local_time($m->minute)->format('m-d H:00'));

            return [
                'datasets' => [
                    ['label' => 'طلبات', 'data' => $rows->map->sum('requests')->values()->all(), 'borderColor' => '#059669', 'backgroundColor' => '#05966922', 'fill' => true, 'yAxisID' => 'y'],
                    ['label' => 'أعلى مستخدمين متصلين', 'data' => $rows->map->max('online')->values()->all(), 'borderColor' => '#d97706', 'yAxisID' => 'y1'],
                ],
                'labels' => $rows->keys()->map(fn ($k) => substr($k, 6))->values()->all(),
            ];
        });
    }

    protected function getOptions(): array
    {
        return ['scales' => ['y' => ['beginAtZero' => true, 'position' => 'left'], 'y1' => ['beginAtZero' => true, 'position' => 'right', 'grid' => ['drawOnChartArea' => false]]]];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
