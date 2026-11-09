<?php

namespace App\Enums;

enum LedgerType: string
{
    case Fee = 'fee';                 // commission on an accepted booking/parcel
    case FeeReversal = 'fee_reversal';
    case Payment = 'payment';         // settlement confirmed by admin
    case WalletTopup = 'wallet_topup';
    case Adjustment = 'adjustment';   // manual, by admin, with reason

    public function label(): string
    {
        return match ($this) {
            self::Fee => 'عمولة',
            self::FeeReversal => 'إلغاء عمولة',
            self::Payment => 'سداد',
            self::WalletTopup => 'شحن محفظة',
            self::Adjustment => 'تسوية يدوية',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Fee => 'danger',
            self::FeeReversal, self::Payment, self::WalletTopup => 'success',
            self::Adjustment => 'gray',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all();
    }
}
