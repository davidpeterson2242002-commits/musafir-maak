<?php

namespace Database\Seeders;

use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;

/** Launch payment methods (spec 1.5). Account details are placeholders — fill them in admin before launch. */
class PaymentMethodSeeder extends Seeder
{
    public function run(): void
    {
        $methods = [
            ['name' => 'بنكك (بنك الخرطوم)', 'instructions' => 'حوّل المبلغ لحساب الشركة من تطبيق بنكك، ثم أدخل رقم العملية وارفع صورة الإشعار.'],
            ['name' => 'فوري', 'instructions' => 'حوّل المبلغ عبر فوري، ثم أدخل رقم العملية وارفع صورة الإشعار.'],
            ['name' => 'أوكاش', 'instructions' => 'حوّل المبلغ عبر أوكاش، ثم أدخل رقم العملية وارفع صورة الإشعار.'],
            ['name' => 'كاش عند وكيل / مكتب', 'requires_reference' => false, 'agent_locations' => [],
                'instructions' => 'ادفع نقداً عند أحد وكلائنا واستلم إيصالاً، ثم ارفع صورة الإيصال.'],
        ];

        foreach ($methods as $i => $method) {
            PaymentMethod::firstOrCreate(['name' => $method['name']], $method + [
                'account_name' => 'مسافر معاك (يُحدّث قبل الإطلاق)',
                'account_number' => null,
                'sort' => $i,
                'enabled' => true,
            ]);
        }
    }
}
