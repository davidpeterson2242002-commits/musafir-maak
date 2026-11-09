<?php

namespace App\Filament\Widgets;

use App\Enums\AdminRole;
use App\Enums\DriverStatus;
use App\Enums\LedgerType;
use App\Enums\SettlementStatus;
use App\Filament\Resources\Settlements\SettlementResource;
use App\Models\DriverProfile;
use App\Models\LedgerEntry;
use App\Models\SettlementRequest;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

/** Revenue numbers (spec 9.1), visible to finance and super admins. */
class FinanceStats extends StatsOverviewWidget
{
    protected static ?int $sort = 0;

    protected ?string $heading = 'المالية';

    public static function canView(): bool
    {
        return Auth::guard('admin')->user()?->hasRole(AdminRole::Finance) ?? false;
    }

    protected function getStats(): array
    {
        $week = now()->subDays(7);
        // Wallet entries are negative for fees, so totals use absolute values.
        $total = fn (array $types) => (int) LedgerEntry::whereIn('type', $types)->where('created_at', '>=', $week)->sum(\DB::raw('abs(amount)'));
        $fees = $total([LedgerType::Fee]);
        $reversed = $total([LedgerType::FeeReversal]);
        $paid = $total([LedgerType::Payment, LedgerType::WalletTopup]);
        $pending = SettlementRequest::where('status', SettlementStatus::Pending);

        return [
            Stat::make('عمولات آخر 7 أيام', number_format($fees).' ج')
                ->description('أُلغي منها '.number_format($reversed).' ج'),
            Stat::make('مدفوعات مؤكدة (7 أيام)', number_format($paid).' ج'),
            Stat::make('أرصدة مستحقة', number_format(DriverProfile::sum('balance_owed')).' ج')
                ->description(DriverProfile::where('balance_owed', '>', 0)->where('settlement_due_at', '<', now())->count().' سائق متأخر'),
            Stat::make('حسابات مجمّدة', DriverProfile::where('status', DriverStatus::Frozen)->count())
                ->color('danger'),
            Stat::make('مدفوعات بانتظار التأكيد', (clone $pending)->count())
                ->description(number_format((clone $pending)->sum('amount')).' ج')
                ->color((clone $pending)->exists() ? 'warning' : 'success')
                ->url(SettlementResource::getUrl()),
        ];
    }
}
