<?php

namespace App\Http\Controllers;

use App\Support\PrivateFiles;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * GET /files/{token} — serves a private upload (ID photo, car photo, avatar, receipt…)
 * from an encrypted, expiring token made by PrivateFiles::url().
 */
class PrivateFileController
{
    public function __invoke(string $token): Response
    {
        $file = PrivateFiles::decode($token);
        abort_if($file === null, 404);

        $disk = Storage::disk($file['disk']);
        abort_unless($disk->exists($file['path']), 404);

        return $disk->response($file['path'], null, [
            'Cache-Control' => 'private, max-age=600',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; sandbox",
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
