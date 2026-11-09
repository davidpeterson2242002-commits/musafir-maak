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

/** Safety and support alerts (spec 9.1). */
class TrustAlerts extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $heading = 'تنبيهات الدعم والسلامة';

    protected ?string $pollingInterval = '60s';

    public static function canView(): bool
    {
        return Auth::guard('admin')->user()?->hasRole(AdminRole::Support) ?? false;
    }

    protected function getStats(): array
    {
        $open = Report::where('status', '!=', ReportStatus::Resolved);
        $safety = (clone $open)->where('priority', '>', 0)->count();

        return [
            Stat::make('بلاغات مفتوحة', (clone $open)->count())
                ->description($safety ? "$safety منها بلاغات سلامة" : 'لا توجد بلاغات سلامة')
                ->color($safety ? 'danger' : 'gray')->url(ReportResource::getUrl()),
            Stat::make('نزاعات ارساليات', Parcel::where('status', ParcelStatus::Disputed)->count())
                ->url(ParcelResource::getUrl()),
            Stat::make('اعتراضات "لم يحضر"', Booking::where('status', BookingStatus::NoShow)->whereNotNull('no_show_disputed_at')->whereNull('no_show_resolution')->count())
                ->url(BookingResource::getUrl()),
            Stat::make('سائقون بتقييم منخفض', DriverProfile::whereNotNull('low_rating_flagged_at')->count())
                ->description('أقل من '.settings()->float('ratings.review_threshold').' بعد '.settings()->int('ratings.review_min_trips').' تقييمات')
                ->url(DriverResource::getUrl()),
            Stat::make('محادثات معلّمة (7 أيام)', Conversation::where('has_flagged', true)->where('last_message_at', '>=', now()->subDays(7))->count())
                ->url(ConversationResource::getUrl()),
        ];
    }
}
