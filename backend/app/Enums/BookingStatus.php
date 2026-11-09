<?php

namespace App\Enums;

enum BookingStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Expired = 'expired';
    case CancelledByPassenger = 'cancelled_by_passenger';
    case CancelledByDriver = 'cancelled_by_driver';
    case Completed = 'completed';
    case NoShow = 'no_show';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'بانتظار السائق',
            self::Accepted => 'مقبول',
            self::Rejected => 'مرفوض',
            self::Expired => 'انتهت المهلة',
            self::CancelledByPassenger => 'ألغاه الراكب',
            self::CancelledByDriver => 'ألغاه السائق',
            self::Completed => 'مكتمل',
            self::NoShow => 'لم يحضر',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Accepted, self::Completed => 'success',
            self::Rejected, self::Expired => 'gray',
            self::CancelledByPassenger, self::CancelledByDriver, self::NoShow => 'danger',
        };
    }

    /** Holds (or may hold) a seat: pending or accepted. */
    public function isActive(): bool
    {
        return in_array($this, [self::Pending, self::Accepted], true);
    }

    /** @return array<int, self> */
    public static function active(): array
    {
        return [self::Pending, self::Accepted];
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all();
    }
}
