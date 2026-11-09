<?php

namespace App\Services;

use App\Enums\VehicleType;
use App\Support\Settings;

/**
 * Commission amounts (spec 5.5). Pure calculation from current settings; the result is
 * snapshotted on the booking at acceptance, so later setting changes never touch it.
 *
 *  - percentage:      seats × price × rate[vehicle type] %
 *  - fixed_per_seat:  seats × fixed[vehicle type]
 *  - fixed_per_trip:  fixed[vehicle type], charged once per trip (LedgerService decides which booking)
 *
 * Min/max per booking apply in every mode (0 = no limit). Whole SDG, rounded half up.
 */
class FeeCalculator
{
    public function __construct(private Settings $settings) {}

    /** @return array{amount: int, model: string} */
    public function bookingFee(VehicleType $type, int $pricePerSeat, int $seats): array
    {
        $model = $this->settings->string('fees.model');

        $raw = match ($model) {
            'fixed_per_seat' => $seats * $this->settings->int("fees.fixed.{$type->value}"),
            'fixed_per_trip' => $this->settings->int("fees.fixed.{$type->value}"),
            default => $seats * $pricePerSeat * $this->settings->float("fees.percent.{$type->value}") / 100,
        };

        return ['amount' => $this->clamp((int) round($raw)), 'model' => $model];
    }

    /** Parcel commission (used from M5). @return array{amount: int, model: string} */
    public function parcelFee(string $size, int $price): array
    {
        $model = $this->settings->string('fees.parcel_model');

        $raw = match ($model) {
            'fixed_per_size' => $this->settings->int("fees.parcel_fixed.$size"),
            default => $price * $this->settings->float('fees.parcel_percent') / 100,
        };

        return ['amount' => $this->clamp((int) round($raw)), 'model' => $model];
    }

    private function clamp(int $amount): int
    {
        $min = $this->settings->int('fees.min_per_booking');
        $max = $this->settings->int('fees.max_per_booking');

        if ($min > 0 && $amount < $min) {
            $amount = $min;
        }
        if ($max > 0 && $amount > $max) {
            $amount = $max;
        }

        return max(0, $amount);
    }
}
