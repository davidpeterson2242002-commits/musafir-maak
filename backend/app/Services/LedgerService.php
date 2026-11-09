<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\DriverStatus;
use App\Enums\LedgerType;
use App\Enums\NotificationEvent;
use App\Enums\SettlementPurpose;
use App\Enums\SettlementStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Admin;
use App\Models\Booking;
use App\Models\DriverProfile;
use App\Models\LedgerEntry;
use App\Models\SettlementRequest;
use App\Models\Trip;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Every change to a driver's money goes through here (spec 5.5).
 *
 *  balance_owed   — postpaid commissions the driver owes; settled weekly
 *  wallet_balance — optional prepaid credit; commissions are taken from it first
 *
 * All methods lock the driver row and write append-only ledger entries with running totals.
 */
class LedgerService
{
    public function __construct(
        private FeeCalculator $fees,
        private Notifier $notifier,
        private AuditLogger $audit,
    ) {}

    // ------------------------------------------------------------------ Commission

    /**
     * Before accepting: block when the balance limit is reached, or when the wallet is
     * mandatory and can't cover the fee. Cheap check usable for the UI too.
     */
    public function assertCanAccept(DriverProfile $driver, Trip $trip, int $seats): void
    {
        if ($driver->balanceLimitReached()) {
            throw new BusinessRuleException(
                'وصل رصيدك المستحق للحد الأقصى ('.number_format(settings()->int('settlement.balance_limit')).' جنيه). سدّد لتتمكن من قبول حجوزات جديدة.',
                'balance_limit_reached',
                ['balance_owed' => $driver->balance_owed],
            );
        }

        if (settings()->string('wallet.mode') === 'mandatory' && $driver->free_bookings_remaining === 0) {
            $fee = $this->previewFee($driver, $trip, $seats);
            if ($fee > $driver->wallet_balance) {
                throw new BusinessRuleException(
                    "رصيد المحفظة لا يكفي لعمولة هذا الحجز ($fee جنيه). اشحن المحفظة أولاً.",
                    'wallet_insufficient',
                    ['fee' => $fee, 'wallet_balance' => $driver->wallet_balance],
                );
            }
        }
    }

    /** Fee the driver would pay for accepting (0 when free or when the trip fee is already charged). */
    public function previewFee(DriverProfile $driver, Trip $trip, int $seats): int
    {
        if ($driver->free_bookings_remaining > 0) {
            return 0;
        }

        $fee = $this->fees->bookingFee($trip->vehicle->type, $trip->price_per_seat, $seats);

        if ($fee['model'] === 'fixed_per_trip' && $this->tripAlreadyCharged($trip)) {
            return 0;
        }

        return $fee['amount'];
    }

    /**
     * Snapshot and record the commission for a booking being accepted.
     * Must run inside the acceptance transaction (throws to roll it back).
     */
    public function chargeBooking(Booking $booking, Trip $trip): void
    {
        $driver = $this->lockDriver($trip->driver_profile_id);
        $this->assertCanAccept($driver, $trip->loadMissing('vehicle'), $booking->seats);

        $fee = $this->fees->bookingFee($trip->vehicle->type, $trip->price_per_seat, $booking->seats);

        if ($driver->free_bookings_remaining > 0) {
            $driver->decrement('free_bookings_remaining');
            $booking->forceFill(['fee_amount' => 0, 'fee_free' => true, 'fee_model' => $fee['model']])->save();

            return;
        }

        $amount = ($fee['model'] === 'fixed_per_trip' && $this->tripAlreadyCharged($trip, $booking)) ? 0 : $fee['amount'];

        $booking->forceFill(['fee_amount' => $amount, 'fee_free' => false, 'fee_model' => $fee['model']])->save();

        if ($amount > 0) {
            $this->applyCharge($driver, $amount, $booking, 'عمولة الحجز #'.$booking->id);
        }
    }

    /**
     * Undo a booking's commission (passenger cancelled an accepted booking, driver cancelled
     * the trip, or a no-show was confirmed). Idempotent.
     */
    public function reverseBooking(Booking $booking, string $reason): void
    {
        DB::transaction(function () use ($booking, $reason) {
            $booking = Booking::whereKey($booking->id)->lockForUpdate()->first();
            if ($booking->fee_model === null || $booking->fee_reversed_at !== null) {
                return; // never charged, or already reversed
            }

            $driver = $this->lockDriver($booking->trip()->value('driver_profile_id'));
            $booking->forceFill(['fee_reversed_at' => now()])->save();

            if ($booking->fee_free) {
                $driver->increment('free_bookings_remaining');

                return;
            }

            if ($booking->fee_amount > 0) {
                $this->refund($driver, $booking, $reason);

                // Per-trip fee: if other accepted bookings remain, one of them carries the trip fee now.
                if ($booking->fee_model === 'fixed_per_trip') {
                    $this->moveTripFee($driver, $booking);
                }
            }
        });
    }

    // ------------------------------------------------------------------ Payments

    public function confirmSettlement(SettlementRequest $request, Admin $admin): void
    {
        DB::transaction(function () use ($request, $admin) {
            $request = SettlementRequest::whereKey($request->id)->lockForUpdate()->first();
            if (! $request->isPending()) {
                throw new BusinessRuleException('تمت مراجعة هذا الطلب مسبقاً.', 'already_reviewed');
            }

            $driver = $this->lockDriver($request->driver_profile_id);

            if ($request->purpose === SettlementPurpose::Settlement) {
                $toBalance = min($request->amount, $driver->balance_owed);
                $toWallet = $request->amount - $toBalance; // overpayment becomes credit

                if ($toBalance > 0) {
                    $this->write($driver, LedgerType::Payment, LedgerEntry::BUCKET_BALANCE, -$toBalance, $request, 'سداد عبر '.$request->paymentMethod->name, $admin);
                }
                if ($toWallet > 0) {
                    $this->write($driver, LedgerType::Payment, LedgerEntry::BUCKET_WALLET, $toWallet, $request, 'زيادة في السداد أُضيفت للمحفظة', $admin);
                }
            } else {
                $this->write($driver, LedgerType::WalletTopup, LedgerEntry::BUCKET_WALLET, $request->amount, $request, 'شحن عبر '.$request->paymentMethod->name, $admin);
            }

            $request->forceFill(['status' => SettlementStatus::Confirmed, 'reviewed_by' => $admin->id, 'reviewed_at' => now()])->save();
            $this->audit->log('settlement.confirmed', $request, ['amount' => $request->amount, 'purpose' => $request->purpose->value], $admin);

            $this->afterBalanceChange($driver);

            $this->notifier->send(
                $driver->user,
                NotificationEvent::PaymentConfirmed,
                'تم تأكيد الدفع',
                sprintf('استلمنا %s جنيه (%s). الرصيد المستحق الآن %s جنيه، والمحفظة %s جنيه.',
                    number_format($request->amount), $request->purpose->label(), number_format($driver->balance_owed), number_format($driver->wallet_balance)),
                ['settlement_request_id' => $request->id],
            );
        });
    }

    public function rejectSettlement(SettlementRequest $request, Admin $admin, string $reason): void
    {
        DB::transaction(function () use ($request, $admin, $reason) {
            $request = SettlementRequest::whereKey($request->id)->lockForUpdate()->first();
            if (! $request->isPending()) {
                throw new BusinessRuleException('تمت مراجعة هذا الطلب مسبقاً.', 'already_reviewed');
            }

            $request->forceFill(['status' => SettlementStatus::Rejected, 'reviewed_by' => $admin->id, 'reviewed_at' => now(), 'reject_reason' => $reason])->save();
            $this->audit->log('settlement.rejected', $request, ['reason' => $reason], $admin);

            $this->notifier->send(
                $request->driverProfile->user,
                NotificationEvent::PaymentRejected,
                'لم يتم تأكيد الدفع',
                'لم نتمكن من تأكيد التحويل بقيمة '.number_format($request->amount)." جنيه. السبب: $reason. راجع البيانات وأرسل الطلب مرة أخرى.",
                ['settlement_request_id' => $request->id],
            );
        });
    }

    /** Manual correction by finance (mandatory reason, audited). $amount is signed. */
    public function adjust(DriverProfile $driver, string $bucket, int $amount, string $reason, Admin $admin): void
    {
        if (! in_array($bucket, [LedgerEntry::BUCKET_BALANCE, LedgerEntry::BUCKET_WALLET], true) || $amount === 0) {
            throw new InvalidArgumentException('Invalid adjustment.');
        }

        DB::transaction(function () use ($driver, $bucket, $amount, $reason, $admin) {
            $driver = $this->lockDriver($driver->id);
            $current = $bucket === LedgerEntry::BUCKET_BALANCE ? $driver->balance_owed : $driver->wallet_balance;

            if ($current + $amount < 0) {
                throw new BusinessRuleException('التسوية تجعل الرصيد بالسالب.', 'negative_balance');
            }

            $wasZero = $driver->balance_owed === 0;
            $this->write($driver, LedgerType::Adjustment, $bucket, $amount, null, $reason, $admin);

            if ($bucket === LedgerEntry::BUCKET_BALANCE && $wasZero && $driver->balance_owed > 0) {
                $this->startCycle($driver);
            }

            $this->audit->log('ledger.adjusted', $driver, ['bucket' => $bucket, 'amount' => $amount, 'reason' => $reason], $admin);
            $this->afterBalanceChange($driver);
        });
    }

    public function grantFreeBookings(DriverProfile $driver, int $count, string $reason, Admin $admin): void
    {
        DB::transaction(function () use ($driver, $count, $reason, $admin) {
            $driver = $this->lockDriver($driver->id);
            $driver->increment('free_bookings_remaining', $count);
            $this->audit->log('driver.free_bookings_granted', $driver, ['count' => $count, 'reason' => $reason], $admin);
        });
    }

    // ------------------------------------------------------------------ Internals

    /** Take the fee from the wallet first, the rest goes to balance owed. */
    private function applyCharge(DriverProfile $driver, int $amount, Model $reference, string $note): void
    {
        $fromWallet = min($amount, $driver->wallet_balance);
        $toBalance = $amount - $fromWallet;
        $wasZero = $driver->balance_owed === 0;

        if ($fromWallet > 0) {
            $this->write($driver, LedgerType::Fee, LedgerEntry::BUCKET_WALLET, -$fromWallet, $reference, $note.' (من المحفظة)');
        }
        if ($toBalance > 0) {
            $this->write($driver, LedgerType::Fee, LedgerEntry::BUCKET_BALANCE, $toBalance, $reference, $note);
            if ($wasZero) {
                $this->startCycle($driver);
            }
        }
    }

    /** Refund exactly what the booking's fee took from each bucket. */
    private function refund(DriverProfile $driver, Booking $booking, string $reason): void
    {
        $charges = LedgerEntry::where('reference_type', 'booking')->where('reference_id', $booking->id)
            ->whereIn('type', [LedgerType::Fee, LedgerType::FeeReversal])
            ->get()->groupBy('bucket')->map->sum('amount');

        $walletTaken = -1 * (int) ($charges[LedgerEntry::BUCKET_WALLET] ?? 0); // fee entries on wallet are negative
        $balanceAdded = (int) ($charges[LedgerEntry::BUCKET_BALANCE] ?? 0);

        if ($walletTaken > 0) {
            $this->write($driver, LedgerType::FeeReversal, LedgerEntry::BUCKET_WALLET, $walletTaken, $booking, $reason);
        }

        if ($balanceAdded > 0) {
            // If the driver already paid this fee, the refund becomes wallet credit.
            $fromBalance = min($balanceAdded, $driver->balance_owed);
            $toWallet = $balanceAdded - $fromBalance;

            if ($fromBalance > 0) {
                $this->write($driver, LedgerType::FeeReversal, LedgerEntry::BUCKET_BALANCE, -$fromBalance, $booking, $reason);
            }
            if ($toWallet > 0) {
                $this->write($driver, LedgerType::FeeReversal, LedgerEntry::BUCKET_WALLET, $toWallet, $booking, $reason.' (كانت مدفوعة — أُضيفت للمحفظة)');
            }
        }

        $this->afterBalanceChange($driver);
    }

    private function moveTripFee(DriverProfile $driver, Booking $reversed): void
    {
        $next = Booking::where('trip_id', $reversed->trip_id)
            ->whereKeyNot($reversed->id)
            ->whereIn('status', [BookingStatus::Accepted, BookingStatus::Completed])
            ->where('fee_free', false)->whereNull('fee_reversed_at')->where('fee_amount', 0)
            ->orderBy('accepted_at')->lockForUpdate()->first();

        if ($next) {
            $next->forceFill(['fee_amount' => $reversed->fee_amount, 'fee_model' => 'fixed_per_trip'])->save();
            $this->applyCharge($driver, $reversed->fee_amount, $next, 'عمولة الرحلة (انتقلت من الحجز #'.$reversed->id.')');
        }
    }

    private function tripAlreadyCharged(Trip $trip, ?Booking $except = null): bool
    {
        return Booking::where('trip_id', $trip->id)
            ->when($except, fn ($q) => $q->whereKeyNot($except->id))
            ->whereIn('status', [BookingStatus::Accepted, BookingStatus::Completed])
            ->where('fee_amount', '>', 0)->whereNull('fee_reversed_at')
            ->exists();
    }

    /** A new settlement cycle starts when the balance goes from zero to owing. */
    private function startCycle(DriverProfile $driver): void
    {
        $driver->forceFill([
            'settlement_due_at' => now()->addDays(settings()->int('settlement.cycle_days')),
            'settlement_reminded_at' => null,
        ])->save();
    }

    /** Fully settled → close the cycle and unfreeze. */
    private function afterBalanceChange(DriverProfile $driver): void
    {
        if ($driver->balance_owed > 0) {
            return;
        }

        $wasFrozen = $driver->status === DriverStatus::Frozen;
        $driver->forceFill([
            'settlement_due_at' => null,
            'settlement_reminded_at' => null,
            'status' => $wasFrozen ? DriverStatus::Active : $driver->status,
            'frozen_at' => $wasFrozen ? null : $driver->frozen_at,
        ])->save();

        if ($wasFrozen) {
            $this->notifier->send($driver->user, NotificationEvent::AccountUnfrozen, 'تم رفع التجميد عن حسابك', 'شكراً للسداد. يمكنك الآن قبول الحجوزات ونشر الرحلات.');
        }
    }

    private function write(DriverProfile $driver, LedgerType $type, string $bucket, int $amount, ?Model $reference, ?string $note, ?Admin $admin = null): LedgerEntry
    {
        if ($bucket === LedgerEntry::BUCKET_BALANCE) {
            $driver->balance_owed += $amount;
        } else {
            $driver->wallet_balance += $amount;
        }

        if ($driver->balance_owed < 0 || $driver->wallet_balance < 0) {
            throw new \LogicException('Ledger would go negative.');
        }

        $driver->save();

        return LedgerEntry::create([
            'driver_profile_id' => $driver->id,
            'type' => $type,
            'bucket' => $bucket,
            'amount' => $amount,
            'balance_after' => $driver->balance_owed,
            'wallet_after' => $driver->wallet_balance,
            'reference_type' => $reference?->getMorphClass(),
            'reference_id' => $reference?->getKey(),
            'note' => $note,
            'created_by_admin_id' => $admin?->id,
        ]);
    }

    private function lockDriver(int $id): DriverProfile
    {
        return DriverProfile::whereKey($id)->lockForUpdate()->with('user')->firstOrFail();
    }
}
