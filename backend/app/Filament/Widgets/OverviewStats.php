<?php

namespace App\Filament\Widgets;

use App\Enums\BookingStatus;
use App\Enums\DriverStatus;
use App\Enums\TripStatus;
use App\Filament\Resources\Verifications\VerificationResource;
use App\Models\Booking;
use App\Models\DriverProfile;
use App\Models\Trip;
use App\Models\User;
use App\Models\VerificationReview;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/** Dashboard numbers (spec 9.1). Numbers are computed once per 30 s and shared by all admins. */
class OverviewStats extends StatsOverviewWidget
{
    protected static ?int $sort = -1;

    protected static bool $isDiscovered = false;

    protected ?string $pollingInterval = '30s';

    public static function numbers(): array
    {
        return Cache::remember('dashboard.overview.v2', 30, function () {
            $tz = config('app.display_timezone');
            $week = now()->subDays(7);
            $pending = VerificationReview::pending()->selectRaw('subject_type, count(*) as c')->groupBy('subject_type')->pluck('c', 'subject_type');
            $drivers = DriverProfile::selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');
            $bookings = Booking::where('created_at', '>=', $week)->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');
            $sum = fn (array $statuses) => collect($statuses)->sum(fn (BookingStatus $s) => (int) ($bookings[$s->value] ?? 0));

            return [
                'users' => User::count(),
                'users_week' => User::where('created_at', '>=', $week)->count(),
                'drivers_active' => (int) ($drivers[DriverStatus::Active->value] ?? 0),
                'drivers_draft' => (int) ($drivers[DriverStatus::Draft->value] ?? 0),
                'pending_total' => (int) $pending->sum(),
                'pending_driver' => (int) ($pending['driver_profile'] ?? 0),
                'pending_vehicle' => (int) ($pending['vehicle'] ?? 0),
                'pending_user' => (int) ($pending['user'] ?? 0),
                'oldest_pending' => VerificationReview::pending()->min('created_at'),
                'trips_today' => Trip::whereIn('status', [TripStatus::Scheduled, TripStatus::Departed, TripStatus::Completed])
                    ->whereBetween('departs_at', [now($tz)->startOfDay()->utc(), now($tz)->endOfDay()->utc()])->count(),
                'trips_upcoming' => Trip::where('status', TripStatus::Scheduled)->where('departs_at', '>', now())->count(),
                'bookings_week' => (int) $bookings->sum(),
                'bookings_accepted' => $sum([BookingStatus::Accepted, BookingStatus::Completed]),
                'bookings_expired' => $sum([BookingStatus::Expired]),
                'bookings_cancelled' => $sum([BookingStatus::CancelledByPassenger, BookingStatus::CancelledByDriver]),
            ];
        });
    }

    protected function getStats(): array
    {
        $n = static::numbers();

        return [
            Stat::make('المستخدمون', number_format($n['users']))->description('+'.$n['users_week'].' هذا الأسبوع'),
            Stat::make('السائقون النشطون', $n['drivers_active'])->description($n['drivers_draft'].' بدأوا التسجيل ولم يرسلوا'),
            Stat::make('بانتظار التوثيق', $n['pending_total'])
                ->description(sprintf('سائقين %d · عربيات %d · هويات %d', $n['pending_driver'], $n['pending_vehicle'], $n['pending_user']))
                ->color($n['pending_total'] ? 'warning' : 'success')
                ->url(VerificationResource::getUrl()),
            Stat::make('رحلات اليوم', $n['trips_today'])->description($n['trips_upcoming'].' رحلة قادمة'),
            Stat::make('حجوزات هذا الأسبوع', $n['bookings_week'])
                ->description(sprintf('مقبولة %d · انتهت مهلتها %d · ملغية %d', $n['bookings_accepted'], $n['bookings_expired'], $n['bookings_cancelled'])),
            Stat::make('أقدم طلب معلّق', $n['oldest_pending'] ? Carbon::parse($n['oldest_pending'])->diffForHumans(short: true) : '—')
                ->description('توثيق — الهدف: خلال 24–48 ساعة'),
        ];
    }
}
