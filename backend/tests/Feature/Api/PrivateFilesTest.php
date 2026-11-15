<?php

namespace Tests\Feature\Api;

use App\Support\PrivateFiles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PrivateFilesTest extends TestCase
{
    use RefreshDatabase;

    public function test_temporary_urls_use_encrypted_links_that_serve_the_file(): void
    {
        Storage::disk('local')->put('documents/1/selfie.jpg', 'JPEGDATA');

        $url = Storage::disk('local')->temporaryUrl('documents/1/selfie.jpg', now()->addMinutes(5));

        $this->assertStringContainsString('/api/v1/files/', $url);
        $this->assertStringNotContainsString('selfie', $url);
        $this->assertStringNotContainsString('?', $url);
        $response = $this->get($url)->assertOk();
        $this->assertSame('JPEGDATA', $response->streamedContent());
        $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));

        Storage::disk('local')->delete('documents/1/selfie.jpg');
    }

    public function test_expired_tampered_and_traversal_links_are_404(): void
    {
        Storage::disk('local')->put('documents/1/selfie.jpg', 'JPEGDATA');

        $expired = PrivateFiles::url('local', 'documents/1/selfie.jpg', now()->subSecond());
        $this->get($expired)->assertNotFound();

        $good = PrivateFiles::url('local', 'documents/1/selfie.jpg', now()->addMinute());
        $this->get(substr($good, 0, -6).'AAAAAA')->assertNotFound();
        $this->get('/api/v1/files/not-a-token')->assertNotFound();

        $this->get(PrivateFiles::url('local', '../.env', now()->addMinute()))->assertNotFound();
        $this->get(PrivateFiles::url('nope', 'documents/1/selfie.jpg', now()->addMinute()))->assertNotFound();

        Storage::disk('local')->delete('documents/1/selfie.jpg');
    }
}
