<?php

namespace Tests\Concerns;

use App\Enums\DocumentKind;
use App\Enums\IdDocumentType;
use App\Models\DriverProfile;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\DocumentStore;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/** Helpers to build complete ID / driver / vehicle submissions in tests. */
trait BuildsApplications
{
    protected function fakeStorage(): void
    {
        Storage::fake('local');
    }

    protected function photo(): UploadedFile
    {
        return UploadedFile::fake()->image('doc.jpg', 800, 600);
    }

    protected function giveIdentityDocs(User $user, IdDocumentType $type = IdDocumentType::NationalId): void
    {
        foreach ($type->requiredKinds() as $kind) {
            app(DocumentStore::class)->put($user, $kind, $this->photo());
        }
    }

    protected function giveVehicleDocs(Vehicle $vehicle): void
    {
        foreach (DocumentKind::vehicleKinds() as $kind) {
            app(DocumentStore::class)->put($vehicle, $kind, $this->photo());
        }
    }

    /** Draft driver with identity, licence and one vehicle — ready to submit. */
    protected function completeDraftDriver(?User $user = null): DriverProfile
    {
        $user ??= User::factory()->create();
        $this->giveIdentityDocs($user);

        $driver = DriverProfile::factory()->for($user)->create();
        app(DocumentStore::class)->put($driver, DocumentKind::DrivingLicence, $this->photo());

        $vehicle = Vehicle::factory()->for($driver)->create();
        $this->giveVehicleDocs($vehicle);

        return $driver->fresh();
    }
}
