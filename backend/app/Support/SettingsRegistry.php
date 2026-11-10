<?php

namespace App\Support;

use App\Enums\SettingType;

/**
 * The catalogue of every configurable business parameter (spec 1.6 / 9.9).
 *
 * Each entry defines the type, admin group, Arabic label, default value and
 * optional options/validation. Values live in the `settings` table; the default
 * here is only used to seed new keys and as a fallback if a row is missing.
 *
 * Adding a new parameter = add an entry here and run `php artisan settings:sync`.
 * Placeholder values marked "TBD" are open business decisions (spec 13).
 */
final class SettingsRegistry
{
    public const GROUPS = [
        'fees' => 'العمولات',
        'settlement' => 'التسوية والرصيد',
        'trips' => 'الرحلات والبحث',
        'booking' => 'الحجوزات',
        'cancellation' => 'الإلغاء',
        'verification' => 'التوثيق',
        'ratings' => 'التقييمات',
        'otp' => 'رمز التحقق',
        'routes' => 'الطرق',
        'parcels' => 'الارساليات',
        'trust' => 'المحادثات والبلاغات',
        'notifications' => 'الإشعارات',
        'content' => 'النصوص والمحتوى',
    ];

    public const VEHICLE_TYPES = [
        'car' => 'عربية خاصة',
        'boksi' => 'بوكسي',
        'minibus' => 'حافلة صغيرة (هايس)',
        'bus' => 'بص',
        'pickup' => 'دفار / بيك أب',
    ];

    public const PARCEL_SIZES = [
        'small' => 'صغيرة',
        'medium' => 'متوسطة',
        'large' => 'كبيرة',
    ];

    /** @return array<string, array{type: SettingType, group: string, label: string, default: mixed, options?: array, help?: string, min?: int|float, max?: int|float, public?: bool}> */
    public static function definitions(): array
    {
        $defs = [
            // ---------------- Fees ----------------
            'fees.model' => [
                'type' => SettingType::Select, 'group' => 'fees', 'label' => 'طريقة احتساب عمولة المقاعد',
                'default' => 'percentage',
                'options' => ['percentage' => 'نسبة من سعر المقعد', 'fixed_per_seat' => 'مبلغ ثابت لكل مقعد', 'fixed_per_trip' => 'مبلغ ثابت لكل رحلة'],
            ],
            'fees.min_per_booking' => [
                'type' => SettingType::Integer, 'group' => 'fees', 'label' => 'أقل عمولة للحجز (جنيه، 0 = بدون)',
                'default' => 0, 'min' => 0,
            ],
            'fees.max_per_booking' => [
                'type' => SettingType::Integer, 'group' => 'fees', 'label' => 'أعلى عمولة للحجز (جنيه، 0 = بدون)',
                'default' => 0, 'min' => 0,
            ],
            'fees.parcel_model' => [
                'type' => SettingType::Select, 'group' => 'fees', 'label' => 'طريقة احتساب عمولة الارساليات',
                'default' => 'percentage',
                'options' => ['percentage' => 'نسبة من سعر الارسالية', 'fixed_per_size' => 'مبلغ ثابت حسب الحجم'],
            ],
            'fees.parcel_percent' => [
                'type' => SettingType::Decimal, 'group' => 'fees', 'label' => 'نسبة عمولة الارسالية (%)',
                'default' => 10, 'min' => 0, 'max' => 100, 'help' => 'قيمة مبدئية — لم تُحدد بعد',
            ],
            'drivers.free_bookings' => [
                'type' => SettingType::Integer, 'group' => 'fees', 'label' => 'عدد الحجوزات المجانية للسائق الجديد',
                'default' => 3, 'min' => 0,
            ],
            'wallet.mode' => [
                'type' => SettingType::Select, 'group' => 'settlement', 'label' => 'المحفظة المسبقة',
                'default' => 'optional',
                'options' => ['optional' => 'اختيارية', 'mandatory' => 'إجبارية'],
            ],

            // ---------------- Settlement ----------------
            'settlement.cycle_days' => [
                'type' => SettingType::Integer, 'group' => 'settlement', 'label' => 'دورة التسوية (أيام)',
                'default' => 7, 'min' => 1,
            ],
            'settlement.balance_limit' => [
                'type' => SettingType::Integer, 'group' => 'settlement', 'label' => 'حد الرصيد المستحق (جنيه)',
                'default' => 50000, 'min' => 0, 'help' => 'قيمة مبدئية — لم تُحدد بعد. عند بلوغها يجب السداد قبل قبول حجوزات جديدة',
            ],
            'settlement.reminder_days_before' => [
                'type' => SettingType::Integer, 'group' => 'settlement', 'label' => 'تذكير السداد قبل الموعد بـ (أيام)',
                'default' => 2, 'min' => 0,
            ],
            'settlement.grace_hours' => [
                'type' => SettingType::Integer, 'group' => 'settlement', 'label' => 'مهلة إضافية قبل التجميد (ساعات)',
                'default' => 24, 'min' => 0, 'help' => 'قيمة مبدئية — لم تُحدد بعد',
            ],

            // ---------------- Booking ----------------
            'booking.response_timeout_hours' => [
                'type' => SettingType::Integer, 'group' => 'booking', 'label' => 'مهلة رد السائق على الطلب (ساعات)',
                'default' => 12, 'min' => 1, 'help' => 'أو حتى موعد الانطلاق، أيهما أقرب',
            ],
            'booking.reminder_interval_minutes' => [
                'type' => SettingType::Integer, 'group' => 'booking', 'label' => 'تذكير السائق بالطلب المعلق كل (دقيقة)',
                'default' => 60, 'min' => 5,
            ],
            'booking.no_show_dispute_hours' => [
                'type' => SettingType::Integer, 'group' => 'booking', 'label' => 'مهلة اعتراض الراكب على "لم يحضر" (ساعات)',
                'default' => 24, 'min' => 1,
            ],

            'booking.reminder_evening_hour' => [
                'type' => SettingType::Integer, 'group' => 'booking', 'label' => 'تذكير الرحلة مساء اليوم السابق — الساعة (بتوقيت السودان)',
                'default' => 20, 'min' => 0, 'max' => 23,
            ],
            'booking.reminder_soon_hours' => [
                'type' => SettingType::Integer, 'group' => 'booking', 'label' => 'تذكير قبل الانطلاق بـ (ساعات)',
                'default' => 2, 'min' => 1,
            ],

            // ---------------- Trips & search ----------------
            'trips.min_minutes_ahead' => [
                'type' => SettingType::Integer, 'group' => 'trips', 'label' => 'أقل وقت قبل الانطلاق لنشر رحلة (دقائق)',
                'default' => 30, 'min' => 0,
            ],
            'trips.max_days_ahead' => [
                'type' => SettingType::Integer, 'group' => 'trips', 'label' => 'أقصى مدة مسبقة لنشر رحلة (أيام)',
                'default' => 60, 'min' => 1,
            ],
            'trips.depart_early_minutes' => [
                'type' => SettingType::Integer, 'group' => 'trips', 'label' => 'يمكن للسائق تسجيل الانطلاق قبل الموعد بـ (دقائق)',
                'default' => 60, 'min' => 0,
            ],
            'trips.auto_complete_hours' => [
                'type' => SettingType::Integer, 'group' => 'trips', 'label' => 'إكمال الرحلة تلقائياً بعد الانطلاق بـ (ساعات) إذا نسي السائق',
                'default' => 48, 'min' => 6,
            ],
            'search.nearby_days' => [
                'type' => SettingType::Integer, 'group' => 'trips', 'label' => 'عرض أيام قريبة عند عدم وجود رحلات (± أيام)',
                'default' => 3, 'min' => 0, 'max' => 14,
            ],
            'route_alerts.max_per_user' => [
                'type' => SettingType::Integer, 'group' => 'trips', 'label' => 'أقصى عدد تنبيهات طرق للمستخدم',
                'default' => 10, 'min' => 1,
            ],

            // ---------------- Cancellation ----------------
            'cancellation.late_window_hours' => [
                'type' => SettingType::Integer, 'group' => 'cancellation', 'label' => 'الإلغاء يُعتبر متأخراً إذا كان قبل الانطلاق بأقل من (ساعات)',
                'default' => 3, 'min' => 0,
            ],
            'cancellation.passenger_limit' => [
                'type' => SettingType::Integer, 'group' => 'cancellation', 'label' => 'عدد الإلغاءات المتأخرة المسموح للراكب',
                'default' => 3, 'min' => 1,
            ],
            'cancellation.passenger_window_days' => [
                'type' => SettingType::Integer, 'group' => 'cancellation', 'label' => 'خلال فترة (أيام)',
                'default' => 30, 'min' => 1,
            ],
            'cancellation.passenger_restriction_days' => [
                'type' => SettingType::Integer, 'group' => 'cancellation', 'label' => 'مدة منع الراكب من الحجز بعد تجاوز الحد (أيام)',
                'default' => 7, 'min' => 0,
            ],
            'cancellation.driver_limit' => [
                'type' => SettingType::Integer, 'group' => 'cancellation', 'label' => 'عدد إلغاءات السائق قبل المراجعة',
                'default' => 3, 'min' => 1,
            ],
            'cancellation.driver_window_days' => [
                'type' => SettingType::Integer, 'group' => 'cancellation', 'label' => 'خلال فترة (أيام)',
                'default' => 30, 'min' => 1,
            ],

            // ---------------- Verification ----------------
            'verification.allow_first_booking_while_pending' => [
                'type' => SettingType::Boolean, 'group' => 'verification', 'label' => 'السماح بأول حجز والهوية قيد المراجعة',
                'default' => true,
            ],

            'verification.rejection_reasons' => [
                'type' => SettingType::List, 'group' => 'verification', 'label' => 'أسباب رفض التوثيق (تظهر للمراجع)',
                'default' => [
                    'صورة غير واضحة',
                    'السيلفي لا يطابق صورة الهوية',
                    'مستند ناقص',
                    'المستند منتهي الصلاحية',
                    'شهادة البحث لا تطابق العربية أو اللوحة',
                    'اللوحة غير ظاهرة في صورة العربية',
                    'بيانات العربية غير صحيحة',
                    'الاسم لا يطابق الهوية',
                ],
            ],
            'verification.max_upload_kb' => [
                'type' => SettingType::Integer, 'group' => 'verification', 'label' => 'أقصى حجم للصورة المرفوعة (كيلوبايت)',
                'default' => 8192, 'min' => 512,
            ],

            // ---------------- Ratings ----------------
            'ratings.review_threshold' => [
                'type' => SettingType::Decimal, 'group' => 'ratings', 'label' => 'تقييم السائق الذي يستدعي المراجعة (أقل من)',
                'default' => 3.0, 'min' => 1, 'max' => 5,
            ],
            'ratings.review_min_trips' => [
                'type' => SettingType::Integer, 'group' => 'ratings', 'label' => 'بعد عدد رحلات لا يقل عن',
                'default' => 10, 'min' => 1,
            ],

            // ---------------- OTP ----------------
            'otp.length' => [
                'type' => SettingType::Integer, 'group' => 'otp', 'label' => 'عدد أرقام رمز التحقق',
                'default' => 6, 'min' => 4, 'max' => 8,
            ],
            'otp.ttl_minutes' => [
                'type' => SettingType::Integer, 'group' => 'otp', 'label' => 'صلاحية الرمز (دقائق)',
                'default' => 10, 'min' => 1,
            ],
            'otp.max_attempts' => [
                'type' => SettingType::Integer, 'group' => 'otp', 'label' => 'أقصى محاولات إدخال للرمز الواحد',
                'default' => 5, 'min' => 1,
            ],
            'otp.resend_seconds' => [
                'type' => SettingType::Integer, 'group' => 'otp', 'label' => 'الانتظار قبل إعادة الإرسال (ثواني)',
                'default' => 60, 'min' => 10, 'public' => true,
            ],
            'otp.max_per_hour' => [
                'type' => SettingType::Integer, 'group' => 'otp', 'label' => 'أقصى عدد رموز لنفس الرقم في الساعة',
                'default' => 5, 'min' => 1,
            ],

            // ---------------- Routes ----------------
            'routes.auto_create' => [
                'type' => SettingType::Boolean, 'group' => 'routes', 'label' => 'إنشاء الطريق تلقائياً عند أول رحلة بين مدينتين مفعّلتين',
                'default' => true, 'help' => 'إذا أُوقف، يمكن نشر الرحلات فقط على الطرق التي أضافتها الإدارة',
            ],

            // ---------------- Parcels ----------------
            'parcels.code_max_attempts' => [
                'type' => SettingType::Integer, 'group' => 'parcels', 'label' => 'أقصى محاولات لإدخال رمز التسليم',
                'default' => 5, 'min' => 1,
            ],
            'parcels.dispute_days' => [
                'type' => SettingType::Integer, 'group' => 'parcels', 'label' => 'مهلة فتح نزاع بعد التسليم (أيام)',
                'default' => 3, 'min' => 0,
            ],

            // ---------------- Chat, ratings, reports ----------------
            'chat.flag_keywords' => [
                'type' => SettingType::List, 'group' => 'trust', 'label' => 'كلمات تُعلّم الرسالة للمراجعة',
                'default' => ['حيوان', 'كلب', 'حمار', 'غبي', 'وسخ', 'حرامي', 'برا التطبيق', 'بره التطبيق', 'كاش برا'],
                'help' => 'الرسائل التي تحتوي هذه الكلمات تظهر في قائمة المراجعة ولا تُحجب',
            ],
            'chat.days_after_trip' => [
                'type' => SettingType::Integer, 'group' => 'trust', 'label' => 'تبقى المحادثة مفتوحة بعد الرحلة (أيام)',
                'default' => 3, 'min' => 0,
            ],
            'ratings.window_days' => [
                'type' => SettingType::Integer, 'group' => 'ratings', 'label' => 'مهلة التقييم بعد الرحلة (أيام)',
                'default' => 14, 'min' => 1,
            ],
            'reports.max_per_day' => [
                'type' => SettingType::Integer, 'group' => 'trust', 'label' => 'أقصى عدد بلاغات للمستخدم في اليوم',
                'default' => 10, 'min' => 1,
            ],

            // ---------------- Notifications ----------------
            'notifications.log_retention_days' => [
                'type' => SettingType::Integer, 'group' => 'notifications', 'label' => 'مدة حفظ سجل الإشعارات (أيام)',
                'default' => 60, 'min' => 7, 'help' => 'تُحذف السجلات الأقدم تلقائياً كل يوم.',
            ],
            'notifications.broadcast_search_days' => [
                'type' => SettingType::Integer, 'group' => 'notifications', 'label' => 'الإشعار الجماعي: ركاب بحثوا عن الطريق خلال (أيام)',
                'default' => 30, 'min' => 1,
            ],

            // ---------------- Content ----------------
            'content.prohibited_items' => [
                'type' => SettingType::List, 'group' => 'content', 'label' => 'قائمة الممنوعات في الارساليات', 'public' => true,
                'default' => [
                    'أسلحة وذخيرة',
                    'مخدرات ومواد ممنوعة',
                    'مبالغ نقدية كبيرة',
                    'مستندات أو أوراق رسمية تخص أشخاص آخرين',
                    'مواد قابلة للاشتعال أو الانفجار',
                    'مواد غذائية سريعة التلف',
                    'حيوانات حية',
                ],
            ],
            'content.terms' => [
                'type' => SettingType::Text, 'group' => 'content', 'label' => 'الشروط والأحكام', 'public' => true,
                'default' => "الشروط والأحكام — نص مبدئي يُستبدل قبل الإطلاق.\n\nباستخدامك للتطبيق توافق على أن المحادثات قد تُراجع لأغراض السلامة عند وجود بلاغ.",
            ],
            'content.privacy' => [
                'type' => SettingType::Text, 'group' => 'content', 'label' => 'سياسة الخصوصية', 'public' => true,
                'default' => 'سياسة الخصوصية — نص مبدئي يُستبدل قبل الإطلاق.',
            ],
            'content.driver_terms' => [
                'type' => SettingType::Text, 'group' => 'content', 'label' => 'شروط السائقين', 'public' => true,
                'default' => 'شروط السائقين (العمولة والسلوك) — نص مبدئي يُستبدل قبل الإطلاق.',
            ],
            'content.support_whatsapp' => [
                'type' => SettingType::String, 'group' => 'content', 'label' => 'رقم واتساب الدعم', 'public' => true,
                'default' => '',
            ],
        ];

        // Per-vehicle-type seat fee rates (percentage and fixed amounts).
        foreach (self::VEHICLE_TYPES as $type => $label) {
            $defs["fees.percent.$type"] = [
                'type' => SettingType::Decimal, 'group' => 'fees', 'label' => "نسبة العمولة — $label (%)",
                'default' => 10, 'min' => 0, 'max' => 100, 'help' => 'قيمة مبدئية — لم تُحدد بعد',
            ];
            $defs["fees.fixed.$type"] = [
                'type' => SettingType::Integer, 'group' => 'fees', 'label' => "المبلغ الثابت — $label (جنيه)",
                'default' => 0, 'min' => 0, 'help' => 'يُستخدم في وضع "مبلغ ثابت لكل مقعد/رحلة"',
            ];
        }

        foreach (self::PARCEL_SIZES as $size => $label) {
            $defs["fees.parcel_fixed.$size"] = [
                'type' => SettingType::Integer, 'group' => 'fees', 'label' => "عمولة ثابتة للارسالية — $label (جنيه)",
                'default' => 0, 'min' => 0,
            ];
        }

        return $defs;
    }

    public static function get(string $key): ?array
    {
        return self::definitions()[$key] ?? null;
    }
}
