<?php

namespace App\Enums;

use App\Support\SettingsRegistry;

enum VehicleType: string
{
    case Car = 'car';
    case Boksi = 'boksi';
    case Minibus = 'minibus';
    case Bus = 'bus';
    case Pickup = 'pickup';

    public function label(): string
    {
        return SettingsRegistry::VEHICLE_TYPES[$this->value];
    }

    /** Sensible upper bound for passenger seats (excluding the driver). */
    public function maxSeats(): int
    {
        return match ($this) {
            self::Car => 7,
            self::Boksi => 12,
            self::Minibus => 30,
            self::Bus => 70,
            self::Pickup => 20,
        };
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all();
    }
}
