<?php

namespace App\Enums;

enum DriverStatus: string
{
    case Draft = 'draft';                  // registration started, not submitted
    case PendingReview = 'pending_review';
    case Rejected = 'rejected';            // can fix and resubmit (spec 5.1)
    case Active = 'active';
    case Suspended = 'suspended';
    case Frozen = 'frozen';                // unpaid balance (M4)

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'لم يُرسل الطلب',
            self::PendingReview => 'قيد المراجعة',
            self::Rejected => 'يحتاج تعديل',
            self::Active => 'نشط',
            self::Suspended => 'موقوف',
            self::Frozen => 'مجمّد',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::PendingReview => 'warning',
            self::Rejected => 'danger',
            self::Active => 'success',
            self::Suspended, self::Frozen => 'danger',
        };
    }

    /** Can the driver change documents/vehicles of the application? */
    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::Rejected], true);
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all();
    }
}
