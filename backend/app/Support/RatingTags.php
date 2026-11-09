<?php

namespace App\Support;

/** Quick tags on ratings (spec P9, D15). */
final class RatingTags
{
    public const FOR_DRIVER = [
        'punctual' => 'ملتزم بالموعد',
        'safe_driving' => 'قيادة آمنة',
        'clean_car' => 'عربية نظيفة',
        'polite' => 'محترم',
        'late' => 'متأخر',
        'unsafe' => 'قيادة خطرة',
        'rude' => 'غير محترم',
        'parcel_care' => 'حافظ على الارسالية',
    ];

    public const FOR_PASSENGER = [
        'punctual' => 'ملتزم بالموعد',
        'polite' => 'محترم',
        'easy_pickup' => 'سهل الوصول إليه',
        'late' => 'متأخر',
        'rude' => 'غير محترم',
        'too_much_luggage' => 'شنط أكثر من المتفق',
    ];
}
