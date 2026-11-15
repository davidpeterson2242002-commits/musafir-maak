<?php

namespace App\Support;

use DateTimeInterface;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * Expiring links to private files. The disk, path and expiry are encrypted with APP_KEY into a
 * single URL segment, so nothing depends on how the web server rewrites query strings
 * (Laravel's signed /storage links were rejected behind Hostinger's LiteSpeed).
 */
final class PrivateFiles
{
    public static function url(string $disk, string $path, DateTimeInterface $expiresAt): string
    {
        $token = Crypt::encryptString(json_encode(['d' => $disk, 'p' => $path, 'e' => $expiresAt->getTimestamp()]));

        return route('files.show', ['token' => rtrim(strtr(base64_encode($token), '+/', '-_'), '=')]);
    }

    /** @return array{disk: string, path: string}|null null when tampered or expired */
    public static function decode(string $token): ?array
    {
        $raw = base64_decode(strtr($token, '-_', '+/'), true);
        if ($raw === false) {
            return null;
        }
        try {
            $data = json_decode(Crypt::decryptString($raw), true);
        } catch (DecryptException) {
            return null;
        }
        if (! is_array($data) || ! isset($data['d'], $data['p'], $data['e']) || $data['e'] < time()) {
            return null;
        }
        if (str_contains($data['p'], '..') || ! array_key_exists($data['d'], config('filesystems.disks'))) {
            return null;
        }

        return ['disk' => $data['d'], 'path' => $data['p']];
    }
}
