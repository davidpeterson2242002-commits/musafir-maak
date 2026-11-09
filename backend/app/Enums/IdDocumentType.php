<?php

namespace App\Enums;

enum IdDocumentType: string
{
    case Passport = 'passport';
    case NationalId = 'national_id';

    public function label(): string
    {
        return match ($this) {
            self::Passport => 'جواز سفر',
            self::NationalId => 'بطاقة قومية',
        };
    }

    /** Documents required for this identity type (always plus a selfie). */
    public function requiredKinds(): array
    {
        return match ($this) {
            self::Passport => [DocumentKind::Selfie, DocumentKind::Passport],
            self::NationalId => [DocumentKind::Selfie, DocumentKind::NationalIdFront, DocumentKind::NationalIdBack],
        };
    }
}
