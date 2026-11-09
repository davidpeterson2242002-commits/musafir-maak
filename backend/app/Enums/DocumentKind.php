<?php

namespace App\Enums;

enum DocumentKind: string
{
    // Identity (owner: user) — shared by passenger ID and driver registration
    case Selfie = 'selfie';
    case Passport = 'passport';
    case NationalIdFront = 'national_id_front';
    case NationalIdBack = 'national_id_back';

    // Driver (owner: driver_profile)
    case DrivingLicence = 'driving_licence';

    // Vehicle (owner: vehicle)
    case VehicleFront = 'vehicle_front';
    case VehicleBack = 'vehicle_back';
    case VehicleSide = 'vehicle_side';
    case VehicleInterior = 'vehicle_interior';
    case VehicleRegistration = 'vehicle_registration';

    public function label(): string
    {
        return match ($this) {
            self::Selfie => 'صورة شخصية (سيلفي)',
            self::Passport => 'الجواز',
            self::NationalIdFront => 'البطاقة القومية — الأمام',
            self::NationalIdBack => 'البطاقة القومية — الخلف',
            self::DrivingLicence => 'رخصة القيادة',
            self::VehicleFront => 'العربية من الأمام (اللوحة ظاهرة)',
            self::VehicleBack => 'العربية من الخلف',
            self::VehicleSide => 'العربية من الجنب',
            self::VehicleInterior => 'العربية من الداخل',
            self::VehicleRegistration => 'شهادة البحث (ترخيص العربية)',
        };
    }

    /** @return array<int, self> */
    public static function identityKinds(): array
    {
        return [self::Selfie, self::Passport, self::NationalIdFront, self::NationalIdBack];
    }

    /** @return array<int, self> */
    public static function driverKinds(): array
    {
        return [self::DrivingLicence];
    }

    /** @return array<int, self> */
    public static function vehicleKinds(): array
    {
        return [self::VehicleFront, self::VehicleBack, self::VehicleSide, self::VehicleInterior, self::VehicleRegistration];
    }

    public static function values(array $kinds): array
    {
        return array_map(fn (self $k) => $k->value, $kinds);
    }
}
