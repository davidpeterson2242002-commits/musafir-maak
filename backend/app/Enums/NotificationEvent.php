<?php

namespace App\Enums;

/**
 * Notification events and their default channels (spec section 10).
 * Push always; WhatsApp only for important events. M6 makes this matrix editable in admin.
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

    public function sendsWhatsApp(): bool
    {
        return match ($this) {
            self::IdApproved, self::IdRejected, self::DriverApproved, self::DriverRejected,
            self::BookingRequested, self::BookingAccepted, self::TripCancelled, self::AccountWarning,
            self::AccountFrozen => true,
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
