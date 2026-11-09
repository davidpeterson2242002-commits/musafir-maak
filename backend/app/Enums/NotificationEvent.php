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

    public function sendsWhatsApp(): bool
    {
        return match ($this) {
            self::IdApproved, self::IdRejected, self::DriverApproved, self::DriverRejected => true,
            self::VehicleApproved, self::VehicleRejected => false,
        };
    }
}
