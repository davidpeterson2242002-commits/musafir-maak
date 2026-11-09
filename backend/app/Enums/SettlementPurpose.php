<?php

namespace App\Enums;

enum SettlementPurpose: string
{
    case Settlement = 'settlement';
    case WalletTopup = 'wallet_topup';

    public function label(): string
    {
        return match ($this) {
            self::Settlement => 'سداد الرصيد',
            self::WalletTopup => 'شحن المحفظة',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all();
    }
}
