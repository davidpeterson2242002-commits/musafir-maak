<?php

namespace App\Enums;

enum SettingType: string
{
    case Integer = 'integer';
    case Decimal = 'decimal';
    case Boolean = 'boolean';
    case String = 'string';
    case Text = 'text';     // long text (markdown)
    case Select = 'select'; // one of fixed options
    case List = 'list';     // list of strings

    public function cast(mixed $value): mixed
    {
        if ($value === null) {
            // Empty form fields come back as null; keep text and list settings non-null.
            return match ($this) {
                self::String, self::Text => '',
                self::List => [],
                default => null,
            };
        }

        return match ($this) {
            self::Integer => (int) $value,
            self::Decimal => (float) $value,
            self::Boolean => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            self::String, self::Text, self::Select => (string) $value,
            self::List => array_values(array_filter(array_map('strval', (array) $value), fn ($v) => trim($v) !== '')),
        };
    }
}
