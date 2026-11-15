<?php

namespace App\Support;

use DateTimeInterface;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * Expiring links to private files, all in the URL path (Hostinger's web server broke Laravel's
 * signed query-string links):
 *
 *   /api/v1/files/{expires}/{sig}/{disk}/{path…}          the original
 *   /api/v1/files/{expires}/{sig}/w{width}/{disk}/{path…}  a resized copy (app thumbnails)
 *
 * The expiry is rounded up to the next hour, so a photo keeps the same URL for up to an hour
 * and phones can cache it; `sig` is an HMAC of disk + path + expiry with APP_KEY.
 */
final class PrivateFiles
{
    /** Widths the app may ask for (keeps the thumbnail cache small). */
    public const WIDTHS = [160, 400, 800];

    public static function url(string $disk, string $path, DateTimeInterface $expiresAt): string
    {
        $expires = (int) (ceil($expiresAt->getTimestamp() / 3600) * 3600);

        // The router encodes the segments (and keeps the slashes).
        return route('files.show', ['expires' => $expires, 'sig' => self::sign($disk, $path, $expires), 'file' => "$disk/$path"]);
    }

    /**
     * @return array{disk: string, path: string, width: int|null}|null null when tampered, expired or unsafe
     */
    public static function resolve(int $expires, string $sig, string $file): ?array
    {
        $width = null;
        if (preg_match('#^w(\d+)/(.+)$#', $file, $m)) {
            $width = (int) $m[1];
            $file = $m[2];
            if (! in_array($width, self::WIDTHS, true)) {
                return null;
            }
        }
        [$disk, $path] = array_pad(explode('/', $file, 2), 2, ''); // already decoded by the router

        if ($expires < time() || $path === '' || str_contains($path, '..')
            || ! array_key_exists($disk, config('filesystems.disks'))
            || ! hash_equals(self::sign($disk, $path, $expires), $sig)) {
            return null;
        }

        return ['disk' => $disk, 'path' => $path, 'width' => $width];
    }

    private static function sign(string $disk, string $path, int $expires): string
    {
        $mac = hash_hmac('sha256', "$disk|$path|$expires", (string) config('app.key'), true);

        return rtrim(strtr(base64_encode(substr($mac, 0, 18)), '+/', '-_'), '=');
    }

    /**
     * Links made by the previous version (one encrypted token) still work until they expire.
     *
     * @return array{disk: string, path: string}|null
     */
    public static function decodeLegacy(string $token): ?array
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
        if (! is_array($data) || ! isset($data['d'], $data['p'], $data['e']) || $data['e'] < time()
            || str_contains($data['p'], '..') || ! array_key_exists($data['d'], config('filesystems.disks'))) {
            return null;
        }

        return ['disk' => $data['d'], 'path' => $data['p']];
    }
}
