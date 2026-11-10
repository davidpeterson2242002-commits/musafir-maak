<?php

namespace App\Enums;

/**
 * Notification events and their default channels (spec section 10).
 * These are only the defaults: the live matrix is editable in admin (App\Support\ChannelMatrix).
 */
enum NotificationEvent: string
{
    case IdApproved = 'id_approved';
    case IdRejected = 'id_rejected';
    case DriverApproved = 'driver_approved';
    case DriverRejected = 'driver_rejected';
    case VehicleApproved = 'vehicle_approved';
    case VehicleRejected = 'vehicle_rejected';
    case BookingRequested = 'booking_requested';
    case BookingReminder = 'booking_reminder';
    case BookingAccepted = 'booking_accepted';
    case BookingRejected = 'booking_rejected';
    case BookingExpired = 'booking_expired';
    case BookingCancelledByPassenger = 'booking_cancelled_by_passenger';
    case TripCancelled = 'trip_cancelled';
    case TripReminder = 'trip_reminder';
    case RouteAlertMatch = 'route_alert_match';
    case NoShowMarked = 'no_show_marked';
    case AccountWarning = 'account_warning';
    case SettlementDueSoon = 'settlement_due_soon';
    case AccountFrozen = 'account_frozen';
    case AccountUnfrozen = 'account_unfrozen';
    case PaymentConfirmed = 'payment_confirmed';
    case PaymentRejected = 'payment_rejected';
    case WeeklyStatement = 'weekly_statement';
    case ParcelRequested = 'parcel_requested';
    case ParcelAccepted = 'parcel_accepted';
    case ParcelRejected = 'parcel_rejected';
    case ParcelExpired = 'parcel_expired';
    case ParcelPickedUp = 'parcel_picked_up';
    case ParcelDelivered = 'parcel_delivered';
    case ParcelRefused = 'parcel_refused';
    case ParcelCancelled = 'parcel_cancelled';
    case NewChatMessage = 'new_chat_message';
    case RateTrip = 'rate_trip';
    case Broadcast = 'broadcast'; // admin broadcast: channels are chosen per message, not from the matrix

    /** Arabic name shown in the admin channel matrix and the notification log. */
    public function label(): string
    {
        return match ($this) {
            self::IdApproved => 'قبول توثيق الهوية',
            self::IdRejected => 'رفض توثيق الهوية',
            self::DriverApproved => 'قبول طلب السائق',
            self::DriverRejected => 'رفض طلب السائق',
            self::VehicleApproved => 'قبول عربية جديدة',
            self::VehicleRejected => 'رفض عربية جديدة',
            self::BookingRequested => 'طلب حجز جديد (للسائق)',
            self::BookingReminder => 'تذكير بطلب بانتظار الرد (للسائق)',
            self::BookingAccepted => 'قبول الحجز (للراكب)',
            self::BookingRejected => 'رفض الحجز (للراكب)',
            self::BookingExpired => 'انتهاء مهلة الحجز (للراكب)',
            self::BookingCancelledByPassenger => 'إلغاء الراكب للحجز (للسائق)',
            self::TripCancelled => 'إلغاء الرحلة (للركاب)',
            self::TripReminder => 'تذكير بالرحلة',
            self::RouteAlertMatch => 'رحلة مطابقة لتنبيه طريق',
            self::NoShowMarked => 'تسجيل عدم حضور (للراكب)',
            self::AccountWarning => 'تنبيه / إيقاف / حظر',
            self::SettlementDueSoon => 'اقتراب موعد السداد',
            self::AccountFrozen => 'تجميد الحساب',
            self::AccountUnfrozen => 'رفع التجميد',
            self::PaymentConfirmed => 'تأكيد الدفع',
            self::PaymentRejected => 'رفض الدفع',
            self::WeeklyStatement => 'كشف الحساب الأسبوعي',
            self::ParcelRequested => 'طلب ارسالية جديد (للسائق)',
            self::ParcelAccepted => 'قبول الارسالية (للمرسل)',
            self::ParcelRejected => 'رفض الارسالية (للمرسل)',
            self::ParcelExpired => 'انتهاء مهلة طلب الارسالية',
            self::ParcelPickedUp => 'استلام السائق للارسالية',
            self::ParcelDelivered => 'تسليم الارسالية',
            self::ParcelRefused => 'رفض السائق استلام الارسالية',
            self::ParcelCancelled => 'إلغاء الارسالية',
            self::NewChatMessage => 'رسالة جديدة في المحادثة',
            self::RateTrip => 'طلب التقييم',
            self::Broadcast => 'إشعار جماعي',
        };
    }

    /** Label for any logged event name, including the non-matrix ones (OTP, parcel receiver code). */
    public static function labelFor(string $event): string
    {
        return match ($event) {
            'otp_code' => 'رمز التحقق',
            'parcel_delivery_code' => 'رمز تسليم الارسالية (للمستلم)',
            default => self::tryFrom($event)?->label() ?? $event,
        };
    }

    /** Events listed in the admin channel matrix. */
    public static function configurable(): array
    {
        return array_values(array_filter(self::cases(), fn (self $e) => $e !== self::Broadcast));
    }

    /** @return array{push: bool, whatsapp: bool, email: bool} */
    public function defaultChannels(): array
    {
        return ['push' => $this->sendsPush(), 'whatsapp' => $this->sendsWhatsApp(), 'email' => $this->sendsEmail()];
    }

    public function sendsWhatsApp(): bool
    {
        return match ($this) {
            self::IdApproved, self::IdRejected, self::DriverApproved, self::DriverRejected,
            self::BookingRequested, self::BookingAccepted, self::TripCancelled, self::AccountWarning,
            self::AccountFrozen, self::ParcelRequested => true,
            default => false,
        };
    }

    public function sendsPush(): bool
    {
        return $this !== self::WeeklyStatement;
    }

    /** Email goes only to users who added an email address. */
    public function sendsEmail(): bool
    {
        return in_array($this, [
            self::SettlementDueSoon, self::PaymentConfirmed, self::PaymentRejected, self::WeeklyStatement,
        ], true);
    }
}
