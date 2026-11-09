<?php

namespace Tests\Feature;

use App\Enums\VehicleType;
use App\Services\FeeCalculator;
use Tests\TestCase;

class FeeCalculatorTest extends TestCase
{
    private function fee(VehicleType $type = VehicleType::Car, int $price = 15000, int $seats = 1): array
    {
        return app(FeeCalculator::class)->bookingFee($type, $price, $seats);
    }

    public function test_default_is_ten_percent_of_seat_price(): void
    {
        $this->assertSame(['amount' => 1500, 'model' => 'percentage'], $this->fee());
        $this->assertSame(4500, $this->fee(seats: 3)['amount']);
    }

    public function test_rate_per_vehicle_type_and_rounding(): void
    {
        settings()->set('fees.percent.minibus', 7.5);
        $this->assertSame(1125, $this->fee(VehicleType::Minibus)['amount']);
        $this->assertSame(1500, $this->fee(VehicleType::Car)['amount']);

        $this->assertSame(1501, $this->fee(price: 15005)['amount'], '1500.5 rounds half up');
        settings()->set('fees.percent.car', 3.33);
        $this->assertSame(333, $this->fee(price: 10010)['amount']); // 333.333 → 333
    }

    public function test_fixed_per_seat_and_per_trip(): void
    {
        settings()->set('fees.model', 'fixed_per_seat');
        settings()->set('fees.fixed.boksi', 700);
        $this->assertSame(['amount' => 2100, 'model' => 'fixed_per_seat'], $this->fee(VehicleType::Boksi, seats: 3));

        settings()->set('fees.model', 'fixed_per_trip');
        settings()->set('fees.fixed.bus', 5000);
        $this->assertSame(['amount' => 5000, 'model' => 'fixed_per_trip'], $this->fee(VehicleType::Bus, seats: 10));
    }

    public function test_min_and_max_per_booking(): void
    {
        settings()->set('fees.min_per_booking', 2000);
        $this->assertSame(2000, $this->fee()['amount']);

        settings()->set('fees.min_per_booking', 0);
        settings()->set('fees.max_per_booking', 3000);
        $this->assertSame(3000, $this->fee(seats: 4)['amount']);
    }

    public function test_parcel_fees(): void
    {
        $calc = app(FeeCalculator::class);
        $this->assertSame(['amount' => 500, 'model' => 'percentage'], $calc->parcelFee('medium', 5000));

        settings()->set('fees.parcel_model', 'fixed_per_size');
        settings()->set('fees.parcel_fixed.large', 1200);
        $this->assertSame(1200, $calc->parcelFee('large', 99999)['amount']);
    }
}
