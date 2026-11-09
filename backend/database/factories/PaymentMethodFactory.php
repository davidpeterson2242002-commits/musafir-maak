<?php

namespace Database\Factories;

use App\Models\PaymentMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PaymentMethod> */
class PaymentMethodFactory extends Factory
{
    public function definition(): array
    {
        return ['name' => 'بنكك', 'account_name' => 'مسافر معاك', 'account_number' => '1234567', 'requires_reference' => true, 'enabled' => true, 'sort' => 0];
    }
}
