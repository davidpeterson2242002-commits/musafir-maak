<?php

namespace App\Enums;

enum ParcelStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case PickedUp = 'picked_up';
    case Delivered = 'delivered';
    case Rejected = 'rejected';     // driver declined the request
    case Expired = 'expired';
    case Refused = 'refused';       // driver refused at pickup (contents didn't match)
    case Cancelled = 'cancelled';   // by sender, or with the trip
    case Disputed = 'disputed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'بانتظار السائق',
            self::Accepted => 'مقبولة — بانتظار الاستلام',
            self::PickedUp => 'مع السائق',
            self::Delivered => 'تم التسليم',
            self::Rejected => 'مرفوضة',
            self::Expired => 'انتهت المهلة',
            self::Refused => 'رفضها السائق عند الاستلام',
            self::Cancelled => 'ملغية',
            self::Disputed => 'محل نزاع',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending, self::Accepted => 'warning',
            self::PickedUp => 'info',
            self::Delivered => 'success',
            self::Rejected, self::Expired => 'gray',
            self::Refused, self::Cancelled, self::Disputed => 'danger',
        };
    }

    /** Takes one of the trip's parcel slots. */
    public function holdsSlot(): bool
    {
        return in_array($this, [self::Accepted, self::PickedUp, self::Delivered, self::Disputed], true);
    }

    /** @return array<int, self> */
    public static function slotHolders(): array
    {
        return [self::Accepted, self::PickedUp, self::Delivered, self::Disputed];
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all();
    }
}
