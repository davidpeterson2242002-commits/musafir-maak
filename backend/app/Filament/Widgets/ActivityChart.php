<?php

namespace App\Filament\Widgets;

use App\Models\Booking;
use App\Models\Parcel;
use App\Models\Trip;
use App\Models\User;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Cache;

/** Daily sign-ups, trips posted, bookings and parcels — last 14 days. */
class ActivityChart extends ChartWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected static bool $isDiscovered = false;

    protected ?string $heading = 'النشاط اليومي — آخر 14 يوم';

    protected ?string $maxHeight = '280px';

    protected function getData(): array
    {
        return Cache::remember('dashboard.activity14.v1', 300, function () {
            $tz = config('app.display_timezone');
            $from = now($tz)->subDays(13)->startOfDay();
            $days = collect(range(0, 13))->map(fn ($i) => $from->copy()->addDays($i)->toDateString());
            // Group in PHP (dates are few) so it works the same on MySQL and Postgres.
            $count = fn ($model) => $model::where('created_at', '>=', $from->copy()->utc())->pluck('created_at')
                ->countBy(fn ($t) => local_time($t)->toDateString());
            $series = fn ($counts) => $days->map(fn ($d) => $counts[$d] ?? 0)->all();

            return [
                'datasets' => [
                    ['label' => 'مستخدمين جدد', 'data' => $series($count(User::class)), 'borderColor' => '#6366f1', 'backgroundColor' => '#6366f1'],
                    ['label' => 'رحلات منشورة', 'data' => $series($count(Trip::class)), 'borderColor' => '#059669', 'backgroundColor' => '#059669'],
                    ['label' => 'طلبات حجز', 'data' => $series($count(Booking::class)), 'borderColor' => '#d97706', 'backgroundColor' => '#d97706'],
                    ['label' => 'ارساليات', 'data' => $series($count(Parcel::class)), 'borderColor' => '#0ea5e9', 'backgroundColor' => '#0ea5e9'],
                ],
                'labels' => $days->map(fn ($d) => substr($d, 5))->all(),
            ];
        });
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
