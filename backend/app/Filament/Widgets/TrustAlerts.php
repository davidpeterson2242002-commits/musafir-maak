<?php

namespace App\Filament\Widgets;

use App\Enums\AdminRole;
use App\Enums\BookingStatus;
use App\Enums\ParcelStatus;
use App\Enums\ReportStatus;
use App\Filament\Resources\Bookings\BookingResource;
use App\Filament\Resources\Conversations\ConversationResource;
use App\Filament\Resources\Drivers\DriverResource;
use App\Filament\Resources\Parcels\ParcelResource;
use App\Filament\Resources\Reports\ReportResource;
use App\Models\Booking;
use App\Models\Conversation;
use App\Models\DriverProfile;
use App\Models\Parcel;
use App\Models\Report;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

/** Safety and support alerts (spec 9.1). */
class TrustAlerts extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $heading = 'تنبيهات الدعم والسلامة';

    protected ?string $pollingInterval = '30s';

    protected static bool $isDiscovered = false;

    public static function canView(): bool
    {
        return Auth::guard('admin')->user()?->hasRole(AdminRole::Support) ?? false;
    }

    protected function getStats(): array
    {
        $n = Cache::remember('dashboard.trust.v1', 30, function () {
            $open = Report::where('status', '!=', ReportStatus::Resolved);

            return [
                'open' => (clone $open)->count(),
                'safety' => (clone $open)->where('priority', '>', 0)->count(),
                'parcel_disputes' => Parcel::where('status', ParcelStatus::Disputed)->count(),
                'no_show' => Booking::where('status', BookingStatus::NoShow)->whereNotNull('no_show_disputed_at')->whereNull('no_show_resolution')->count(),
                'low_rating' => DriverProfile::whereNotNull('low_rating_flagged_at')->count(),
                'flagged_chats' => Conversation::where('has_flagged', true)->where('last_message_at', '>=', now()->subDays(7))->count(),
            ];
        });
        $safety = $n['safety'];

        return [
            Stat::make('بلاغات مفتوحة', $n['open'])
                ->description($safety ? "$safety منها بلاغات سلامة" : 'لا توجد بلاغات سلامة')
                ->color($safety ? 'danger' : 'gray')->url(ReportResource::getUrl()),
            Stat::make('نزاعات ارساليات', $n['parcel_disputes'])
                ->url(ParcelResource::getUrl()),
            Stat::make('اعتراضات "لم يحضر"', $n['no_show'])
                ->url(BookingResource::getUrl()),
            Stat::make('سائقون بتقييم منخفض', $n['low_rating'])
                ->description('أقل من '.settings()->float('ratings.review_threshold').' بعد '.settings()->int('ratings.review_min_trips').' تقييمات')
                ->url(DriverResource::getUrl()),
            Stat::make('محادثات معلّمة (7 أيام)', $n['flagged_chats'])
                ->url(ConversationResource::getUrl()),
        ];
    }
}
