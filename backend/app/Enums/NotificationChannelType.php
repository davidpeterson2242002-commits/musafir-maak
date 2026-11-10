<?php

namespace App\Enums;

enum NotificationChannelType: string
{
    case Push = 'push';
    case WhatsApp = 'whatsapp';
    case Email = 'email';

    public function label(): string
    {
        return match ($this) {
            self::Push => 'إشعار التطبيق',
            self::WhatsApp => 'واتساب',
            self::Email => 'بريد إلكتروني',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all();
    }
}
