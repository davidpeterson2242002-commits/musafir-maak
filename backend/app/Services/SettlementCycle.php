<?php

namespace App\Services;

use App\Enums\DriverStatus;
use App\Enums\LedgerType;
use App\Enums\NotificationEvent;
use App\Models\DriverProfile;
use App\Models\LedgerEntry;

/**
 * Weekly settlement (spec 5.5): reminder before the due date, freeze after due date + grace.
 * Unfreezing happens in LedgerService when the balance is fully paid. Idempotent; run by the scheduler.
 */
class SettlementCycle
{
    public function __construct(private Notifier $notifier) {}

    public function remindDue(): int
    {
        $count = 0;
        $before = now()->addDays(settings()->int('settlement.reminder_days_before'));

        DriverProfile::where('balance_owed', '>', 0)
            ->whereNotNull('settlement_due_at')->whereNull('settlement_reminded_at')
            ->where('settlement_due_at', '<=', $before)
            ->with('user')
            ->each(function (DriverProfile $driver) use (&$count) {
                $driver->forceFill(['settlement_reminded_at' => now()])->save();
                $this->notifier->send(
                    $driver->user,
                    NotificationEvent::SettlementDueSoon,
                    'موعد سداد الرصيد قريب',
                    sprintf('رصيدك المستحق %s جنيه، وآخر موعد للسداد %s. سدّد من شاشة الرصيد لتجنب تجميد الحساب.',
                        number_format($driver->balance_owed), local_time($driver->settlement_due_at)->format('Y-m-d H:i')),
                );
                $count++;
            });

        return $count;
    }

    public function freezeOverdue(): int
    {
        $count = 0;
        $cutoff = now()->subHours(settings()->int('settlement.grace_hours'));

        DriverProfile::where('status', DriverStatus::Active)
            ->where('balance_owed', '>', 0)
            ->where('settlement_due_at', '<=', $cutoff)
            ->with('user')
            ->each(function (DriverProfile $driver) use (&$count) {
                $driver->forceFill(['status' => DriverStatus::Frozen, 'frozen_at' => now()])->save();
                $this->notifier->send(
                    $driver->user,
                    NotificationEvent::AccountFrozen,
                    'تم تجميد حسابك كسائق',
                    sprintf('لم يتم سداد الرصيد المستحق (%s جنيه) في موعده. لا يمكنك قبول حجوزات أو نشر رحلات حتى السداد. الحجوزات المقبولة سابقاً سارية.',
                        number_format($driver->balance_owed)),
                );
                $count++;
            });

        return $count;
    }

    /** Weekly email statement to drivers with an email and some activity or a balance. */
    public function sendWeeklyStatements(): int
    {
        $count = 0;
        $since = now()->subDays(7);

        DriverProfile::whereHas('user', fn ($q) => $q->whereNotNull('email'))
            ->where(fn ($q) => $q->where('balance_owed', '>', 0)->orWhere('wallet_balance', '>', 0)
                ->orWhereHas('ledgerEntries', fn ($q) => $q->where('created_at', '>=', $since)))
            ->with('user')
            ->each(function (DriverProfile $driver) use ($since, &$count) {
                $entries = $driver->ledgerEntries()->where('created_at', '>=', $since)->orderBy('id')->get();
                $sum = fn (LedgerType $type) => abs($entries->where('type', $type)->sum('amount'));

                $lines = $entries->map(fn (LedgerEntry $e) => sprintf('%s — %s: %s جنيه%s',
                    local_time($e->created_at)->format('m-d'), $e->type->label(), number_format($e->amount), $e->note ? " ({$e->note})" : ''))->implode("\n");

                $this->notifier->send(
                    $driver->user,
                    NotificationEvent::WeeklyStatement,
                    'كشف حسابك الأسبوعي',
                    sprintf("العمولات: %s جنيه\nالمسدد: %s جنيه\nالرصيد المستحق: %s جنيه%s\nالمحفظة: %s جنيه\n\n%s",
                        number_format($sum(LedgerType::Fee)), number_format($sum(LedgerType::Payment)), number_format($driver->balance_owed),
                        $driver->settlement_due_at ? ' (آخر موعد '.local_time($driver->settlement_due_at)->format('Y-m-d').')' : '',
                        number_format($driver->wallet_balance), $lines ?: 'لا توجد حركات هذا الأسبوع.'),
                );
                $count++;
            });

        return $count;
    }
}
