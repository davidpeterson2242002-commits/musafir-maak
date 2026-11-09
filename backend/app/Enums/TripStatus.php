<?php

namespace App\Enums;

enum TripStatus: string
{
    case Scheduled = 'scheduled';
    case Departed = 'departed';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'مجدولة',
            self::Departed => 'انطلقت',
            self::Completed => 'وصلت',
            self::Cancelled => 'ملغية',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Scheduled => 'info',
            self::Departed => 'warning',
            self::Completed => 'success',
            self::Cancelled => 'danger',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all();
    }
}
