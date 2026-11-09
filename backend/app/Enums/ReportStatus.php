<?php

namespace App\Enums;

enum ReportStatus: string
{
    case Open = 'open';
    case InReview = 'in_review';
    case Resolved = 'resolved';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'جديد',
            self::InReview => 'قيد المراجعة',
            self::Resolved => 'مغلق',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Open => 'danger',
            self::InReview => 'warning',
            self::Resolved => 'success',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all();
    }
}
