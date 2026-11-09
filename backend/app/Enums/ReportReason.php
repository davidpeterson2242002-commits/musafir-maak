<?php

namespace App\Enums;

enum ReportReason: string
{
    case UnsafeDriving = 'unsafe_driving';
    case Harassment = 'harassment';
    case Fraud = 'fraud';
    case NoShow = 'no_show';
    case WrongPerson = 'wrong_vehicle_or_person';
    case Overcharging = 'overcharging';
    case ParcelIssue = 'parcel_issue';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::UnsafeDriving => 'قيادة غير آمنة',
            self::Harassment => 'تحرش أو إساءة',
            self::Fraud => 'احتيال',
            self::NoShow => 'لم يحضر',
            self::WrongPerson => 'شخص أو عربية مختلفة',
            self::Overcharging => 'طلب مبلغ أكبر',
            self::ParcelIssue => 'مشكلة في ارسالية',
            self::Other => 'أخرى',
        };
    }

    /** Safety-critical reasons jump the queue (spec 5.10). */
    public function isSafetyCritical(): bool
    {
        return in_array($this, [self::Harassment, self::WrongPerson, self::UnsafeDriving], true);
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all();
    }
}
