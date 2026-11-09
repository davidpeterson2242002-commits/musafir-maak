<?php

namespace App\Support;

/**
 * Sudanese mobile number normalization to E.164 (+249XXXXXXXXX).
 *
 * Accepts: 0912345678, 912345678, 249912345678, +249912345678, 00249912345678,
 * with spaces/dashes and Arabic-Indic digits (٠١٢…).
 * Sudanese mobile numbers are 9 digits after the country code and start with 9 or 1.
 */
final class Phone
{
    public static function normalize(?string $input): ?string
    {
        if ($input === null) {
            return null;
        }

        $digits = strtr($input, [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        ]);
        $digits = preg_replace('/\D+/', '', $digits);

        if (str_starts_with($digits, '00249')) {
            $digits = substr($digits, 5);
        } elseif (str_starts_with($digits, '249')) {
            $digits = substr($digits, 3);
        } elseif (str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        if (! preg_match('/^[19]\d{8}$/', $digits)) {
            return null;
        }

        return '+249'.$digits;
    }

    public static function isValid(?string $input): bool
    {
        return self::normalize($input) !== null;
    }

    /** For RTL (Arabic) screens: wrapped in a left-to-right isolate so "+249" stays on the left. */
    public static function displayLtr(?string $e164): ?string
    {
        return $e164 === null ? null : "\u{2066}".self::display($e164)."\u{2069}";
    }

    /** +249912345678 → +249 91 234 5678 */
    public static function display(string $e164): string
    {
        return preg_replace('/^\+249(\d{2})(\d{3})(\d{4})$/', '+249 $1 $2 $3', $e164) ?? $e164;
    }
}
