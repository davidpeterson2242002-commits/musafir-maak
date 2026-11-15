<?php

namespace Tests\Feature\Api;

use App\Support\PrivateFiles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PrivateFilesTest extends TestCase
{
    use RefreshDatabase;

    private function jpeg(int $w = 1200, int $h = 900): string
    {
        $im = imagecreatetruecolor($w, $h);
        imagefill($im, 0, 0, imagecolorallocate($im, 11, 110, 91));
        ob_start();
        imagejpeg($im);

        return (string) ob_get_clean();
    }

    protected function tearDown(): void
    {
        Storage::disk('local')->deleteDirectory('documents/test');
        Storage::disk('local')->deleteDirectory('thumbs');
        parent::tearDown();
    }

    public function test_temporary_urls_are_stable_path_links_that_serve_the_file(): void
    {
        Storage::disk('local')->put('documents/test/selfie_ab12.jpg', $this->jpeg());

        $url = Storage::disk('local')->temporaryUrl('documents/test/selfie_ab12.jpg', now()->addMinutes(30));
        $again = Storage::disk('local')->temporaryUrl('documents/test/selfie_ab12.jpg', now()->addMinutes(31));

        $this->assertStringContainsString('/api/v1/files/', $url);
        $this->assertStringNotContainsString('?', $url);
        $this->assertSame($url, $again, 'same URL within the hour, so phones can cache it');

        $response = $this->get($url)->assertOk();
        $this->assertSame(Storage::disk('local')->get('documents/test/selfie_ab12.jpg'), $response->streamedContent());
        $this->assertStringContainsString('max-age=86400', $response->headers->get('Cache-Control'));
    }

    public function test_thumbnails_are_resized_and_cached(): void
    {
        Storage::disk('local')->put('documents/test/car_front.jpg', $this->jpeg());
        $url = PrivateFiles::url('local', 'documents/test/car_front.jpg', now()->addHour());
        $thumb = preg_replace('#(/files/\d+/[^/]+/)#', '$1w400/', $url);

        $body = $this->get($thumb)->assertOk()->streamedContent();
        [$w, $h] = getimagesizefromstring($body);
        $this->assertSame([400, 300], [$w, $h]);
        Storage::disk('local')->assertExists('thumbs/w400/local/documents/test/car_front.jpg.jpg');

        $this->get(preg_replace('#(/files/\d+/[^/]+/)#', '$1w123/', $url))->assertNotFound();
    }

    public function test_expired_tampered_and_traversal_links_are_404(): void
    {
        Storage::disk('local')->put('documents/test/selfie_ab12.jpg', 'x');

        $this->get(PrivateFiles::url('local', 'documents/test/selfie_ab12.jpg', now()->subHours(2)))->assertNotFound();

        $good = PrivateFiles::url('local', 'documents/test/selfie_ab12.jpg', now()->addHour());
        $this->get(str_replace('selfie_ab12', 'selfie_ab13', $good))->assertNotFound();
        $this->get(preg_replace('#/files/(\d+)/#', '/files/9999999999/', $good))->assertNotFound();
        $this->get(PrivateFiles::url('local', '../.env', now()->addHour()))->assertNotFound();
        $this->get(PrivateFiles::url('nope', 'documents/test/selfie_ab12.jpg', now()->addHour()))->assertNotFound();
        $this->get('/api/v1/files/not-a-token')->assertNotFound();
    }
}
