<?php

namespace App\Http\Controllers;

use App\Support\PrivateFiles;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/** Serves private uploads (ID photos, car photos, avatars, receipts…) from PrivateFiles links. */
class PrivateFileController
{
    private const HEADERS = [
        'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; sandbox",
        'X-Content-Type-Options' => 'nosniff',
    ];

    public function show(int $expires, string $sig, string $file): Response
    {
        $resolved = PrivateFiles::resolve($expires, $sig, $file);
        abort_if($resolved === null, 404);

        return $this->serve($resolved['disk'], $resolved['path'], $resolved['width']);
    }

    public function legacy(string $token): Response
    {
        $resolved = PrivateFiles::decodeLegacy($token);
        abort_if($resolved === null, 404);

        return $this->serve($resolved['disk'], $resolved['path'], null);
    }

    private function serve(string $diskName, string $path, ?int $width): Response
    {
        $disk = Storage::disk($diskName);
        abort_unless($disk->exists($path), 404);

        // A file's content never changes for a given path (a new upload gets a new name),
        // so phones may keep it for a day.
        $headers = self::HEADERS + ['Cache-Control' => 'private, max-age=86400'];

        if ($width !== null && ($thumb = $this->thumbnail($diskName, $path, $width)) !== null) {
            return Storage::disk('local')->response($thumb, null, $headers + ['Content-Type' => 'image/jpeg']);
        }

        return $disk->response($path, null, $headers);
    }

    /** Resized JPEG kept under thumbs/ on the local disk; null when the file isn't an image GD can read. */
    private function thumbnail(string $diskName, string $path, int $width): ?string
    {
        $target = "thumbs/w{$width}/{$diskName}/{$path}.jpg";
        $local = Storage::disk('local');
        if ($local->exists($target)) {
            return $target;
        }
        if (! function_exists('imagecreatefromstring')) {
            return null;
        }

        try {
            $source = @imagecreatefromstring(Storage::disk($diskName)->get($path));
            if ($source === false) {
                return null;
            }
            $w = imagesx($source);
            $h = imagesy($source);
            if ($w > $width) {
                $resized = imagescale($source, $width, (int) round($h * $width / $w), IMG_BICUBIC);
                imagedestroy($source);
                $source = $resized;
            }
            ob_start();
            imagejpeg($source, null, 78);
            $local->put($target, (string) ob_get_clean());
            imagedestroy($source);

            return $target;
        } catch (Throwable) {
            return null;
        }
    }
}
