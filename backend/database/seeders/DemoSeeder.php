<?php

namespace Database\Seeders;

use App\Enums\DocumentKind;
use App\Enums\IdDocumentType;
use App\Enums\VehicleType;
use App\Models\DriverProfile;
use App\Models\User;
use App\Services\DocumentStore;
use App\Services\VerificationService;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;

/**
 * Local demo data: pending driver applications and passenger IDs with placeholder photos,
 * so the admin verification queue can be tried out. Run: php artisan db:seed --class=DemoSeeder
 */
class DemoSeeder extends Seeder
{
    public function run(DocumentStore $store, VerificationService $verification): void
    {
        if (app()->isProduction()) {
            return;
        }

        // Two passengers with pending IDs.
        foreach ([['سارة عثمان', 'female', IdDocumentType::NationalId], ['أحمد الطيب', 'male', IdDocumentType::Passport]] as [$name, $gender, $type]) {
            $user = User::factory()->create(['name' => $name, 'gender' => $gender]);
            foreach ($type->requiredKinds() as $kind) {
                $store->put($user, $kind, $this->image($kind->label(), $name));
            }
            $verification->submitIdentity($user, $type);
        }

        // Two driver applications (car + minibus).
        foreach ([['عثمان محمد', VehicleType::Car, 'تويوتا', 'كورولا', 4], ['الطاهر إبراهيم', VehicleType::Minibus, 'تويوتا', 'هايس', 14]] as [$name, $type, $make, $model, $seats]) {
            $user = User::factory()->create(['name' => $name, 'gender' => 'male']);
            foreach (IdDocumentType::NationalId->requiredKinds() as $kind) {
                $store->put($user, $kind, $this->image($kind->label(), $name));
            }

            $driver = DriverProfile::create(['user_id' => $user->id]);
            $store->put($driver, DocumentKind::DrivingLicence, $this->image('رخصة القيادة', $name));

            $vehicle = $driver->vehicles()->create([
                'type' => $type, 'make' => $make, 'model' => $model, 'color' => 'أبيض',
                'year' => 2015, 'plate_number' => 'خ ط '.random_int(1000, 9999), 'seats' => $seats,
            ]);
            foreach (DocumentKind::vehicleKinds() as $kind) {
                $store->put($vehicle, $kind, $this->image($kind->label(), "$make $model"));
            }

            $verification->submitDriver($driver->fresh(), IdDocumentType::NationalId);
        }
    }

    /** Placeholder photo (GD), labelled so the reviewer screen is readable. */
    private function image(string $title, string $subtitle): UploadedFile
    {
        $img = imagecreatetruecolor(800, 540);
        $hue = crc32($title) % 360;
        [$r, $g, $b] = $this->hsl($hue, 0.35, 0.82);
        imagefill($img, 0, 0, imagecolorallocate($img, $r, $g, $b));
        $ink = imagecolorallocate($img, 40, 40, 40);
        imagerectangle($img, 20, 20, 779, 519, $ink);
        imagestring($img, 5, 40, 40, 'DEMO / '.strtoupper(md5($title.$subtitle)), $ink);
        imagestring($img, 5, 40, 480, 'Musafir Maak placeholder image', $ink);

        $path = tempnam(sys_get_temp_dir(), 'demo').'.jpg';
        imagejpeg($img, $path, 80);
        imagedestroy($img);

        return new UploadedFile($path, 'demo.jpg', 'image/jpeg', null, true);
    }

    private function hsl(int $h, float $s, float $l): array
    {
        $c = (1 - abs(2 * $l - 1)) * $s;
        $x = $c * (1 - abs(fmod($h / 60, 2) - 1));
        $m = $l - $c / 2;
        [$r, $g, $b] = match (intdiv($h, 60)) {
            0 => [$c, $x, 0], 1 => [$x, $c, 0], 2 => [0, $c, $x], 3 => [0, $x, $c], 4 => [$x, 0, $c], default => [$c, 0, $x],
        };

        return [(int) (($r + $m) * 255), (int) (($g + $m) * 255), (int) (($b + $m) * 255)];
    }
}
