<?php

namespace App\Filament\Widgets;

use App\Enums\DriverStatus;
use App\Filament\Resources\Verifications\VerificationResource;
use App\Models\DriverProfile;
use App\Models\User;
use App\Models\VerificationReview;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

/** Dashboard numbers (spec 9.1). Grows with each milestone. */
class OverviewStats extends StatsOverviewWidget
{
    protected static ?int $sort = -1;

    protected ?string $pollingInterval = '60s';

    protected function getStats(): array
    {
        $pending = VerificationReview::pending()->selectRaw('subject_type, count(*) as c')->groupBy('subject_type')->pluck('c', 'subject_type');
        $oldest = VerificationReview::pending()->min('created_at');

        return [
            Stat::make('المستخدمون', User::count())
                ->description('+'.User::where('created_at', '>=', now()->subDays(7))->count().' هذا الأسبوع'),
            Stat::make('السائقون النشطون', DriverProfile::where('status', DriverStatus::Active)->count())
                ->description(DriverProfile::where('status', DriverStatus::Draft)->count().' بدأوا التسجيل ولم يرسلوا'),
            Stat::make('بانتظار التوثيق', $pending->sum())
                ->description(sprintf('سائقين %d · عربيات %d · هويات %d', $pending['driver_profile'] ?? 0, $pending['vehicle'] ?? 0, $pending['user'] ?? 0))
                ->color($pending->sum() ? 'warning' : 'success')
                ->url(VerificationResource::getUrl()),
            Stat::make('أقدم طلب معلّق', $oldest ? Carbon::parse($oldest)->diffForHumans(short: true) : '—')
                ->description('الهدف: خلال 24–48 ساعة'),
        ];
    }
}
