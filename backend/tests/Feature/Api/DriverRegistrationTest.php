<?php

namespace Tests\Feature\Api;

use App\Contracts\PushGateway;
use App\Enums\DocumentKind;
use App\Enums\IdDocumentType;
use App\Models\Admin;
use App\Models\DriverProfile;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VerificationReview;
use App\Services\DocumentStore;
use App\Services\VerificationService;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsApplications;
use Tests\TestCase;

class DriverRegistrationTest extends TestCase
{
    use BuildsApplications;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeStorage();
    }

    private function vehiclePayload(array $overrides = []): array
    {
        return $overrides + [
            'type' => 'car', 'make' => 'تويوتا', 'model' => 'كورولا', 'color' => 'أبيض',
            'year' => 2015, 'plate_number' => 'خ ط 1234', 'seats' => 4,
        ];
    }

    public function test_not_registered_returns_null(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/v1/driver')->assertOk()->assertJsonPath('data', null);
        $this->getJson('/api/v1/me')->assertJsonPath('data.driver_status', null)->assertJsonPath('data.is_driver', false);
    }

    public function test_starting_registration_requires_completed_profile(): void
    {
        Sanctum::actingAs(User::factory()->fresh()->create());
        $this->postJson('/api/v1/driver')->assertJsonValidationErrors('profile');
    }

    public function test_full_registration_flow_through_the_api(): void
    {
        Sanctum::actingAs($user = User::factory()->create());

        $this->postJson('/api/v1/driver')->assertSuccessful()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.checklist', ['identity' => false, 'identity_verified' => false, 'driving_licence' => false, 'vehicle' => false]);
        $this->postJson('/api/v1/driver')->assertSuccessful(); // idempotent
        $this->assertSame(1, DriverProfile::count());

        // Step 1: identity (shared endpoint) + licence
        foreach (['selfie', 'national_id_front', 'national_id_back'] as $kind) {
            $this->post('/api/v1/me/identity/documents', ['kind' => $kind, 'file' => $this->photo()], ['Accept' => 'application/json'])->assertOk();
        }
        $this->post('/api/v1/driver/documents', ['kind' => 'driving_licence', 'file' => $this->photo()], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('data.checklist.identity', true)->assertJsonPath('data.checklist.driving_licence', true);

        // Step 2: vehicle + photos
        $vehicleId = $this->postJson('/api/v1/driver/vehicles', $this->vehiclePayload())->assertSuccessful()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonCount(5, 'data.missing_documents')
            ->json('data.id');
        foreach (DocumentKind::vehicleKinds() as $kind) {
            $this->post("/api/v1/driver/vehicles/$vehicleId/documents", ['kind' => $kind->value, 'file' => $this->photo()], ['Accept' => 'application/json'])->assertOk();
        }

        // Step 3: submit
        $this->postJson('/api/v1/driver/submit', ['id_document_type' => 'national_id', 'accept_terms' => true])
            ->assertOk()
            ->assertJsonPath('data.status', 'pending_review')
            ->assertJsonPath('data.vehicles.0.status', 'pending')
            ->assertJsonPath('data.review.status', 'pending');

        $this->assertSame('pending', $user->fresh()->id_verification_status->value);
        $this->assertDatabaseHas('verification_reviews', ['subject_type' => 'driver_profile', 'status' => 'pending', 'id_document_type' => 'national_id']);
    }

    public function test_submit_lists_everything_missing(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/v1/driver')->assertSuccessful();

        $this->postJson('/api/v1/driver/submit', ['accept_terms' => true])
            ->assertJsonValidationErrors(['id_document_type', 'driving_licence', 'vehicles']);
        $this->postJson('/api/v1/driver/submit', ['id_document_type' => 'passport'])
            ->assertJsonValidationErrors(['accept_terms']);
    }

    public function test_vehicle_with_missing_photos_blocks_submit(): void
    {
        $driver = $this->completeDraftDriver();
        $driver->vehicles()->first()->documents()->where('kind', 'vehicle_interior')->first()->delete();
        Sanctum::actingAs($driver->user);

        $this->postJson('/api/v1/driver/submit', ['id_document_type' => 'national_id', 'accept_terms' => true])
            ->assertUnprocessable()
            ->assertJsonPath('errors', fn ($errors) => str_contains(json_encode(array_values($errors), JSON_UNESCAPED_UNICODE), 'العربية من الداخل'));
    }

    public function test_already_verified_user_does_not_resubmit_identity(): void
    {
        $user = User::factory()->create(['id_verification_status' => 'verified']);
        $driver = DriverProfile::factory()->for($user)->create();
        app(DocumentStore::class)->put($driver, DocumentKind::DrivingLicence, $this->photo());
        $this->giveVehicleDocs(Vehicle::factory()->for($driver)->create());
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/driver/submit', ['accept_terms' => true])->assertOk()->assertJsonPath('data.status', 'pending_review');
        $this->assertNull(VerificationReview::first()->id_document_type);
        $this->assertSame('verified', $user->fresh()->id_verification_status->value);
    }

    public function test_seat_limit_depends_on_vehicle_type_and_plate_must_be_unique(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/v1/driver')->assertSuccessful();

        $this->postJson('/api/v1/driver/vehicles', $this->vehiclePayload(['seats' => 14]))->assertJsonValidationErrors('seats');
        $this->postJson('/api/v1/driver/vehicles', $this->vehiclePayload(['type' => 'minibus', 'seats' => 14]))->assertSuccessful();

        // Same plate written differently (spaces, Arabic digits) is a duplicate.
        $this->postJson('/api/v1/driver/vehicles', $this->vehiclePayload(['plate_number' => 'خط ١٢٣٤']))->assertJsonValidationErrors('plate_number');
    }

    public function test_plate_of_rejected_vehicle_can_be_reused(): void
    {
        Vehicle::factory()->create(['plate_number' => 'خ ط 1234', 'status' => 'rejected']);
        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/v1/driver')->assertSuccessful();

        $this->postJson('/api/v1/driver/vehicles', $this->vehiclePayload())->assertSuccessful();
    }

    public function test_application_is_locked_while_pending(): void
    {
        $driver = $this->completeDraftDriver();
        app(VerificationService::class)->submitDriver($driver, IdDocumentType::NationalId);
        $vehicle = $driver->vehicles()->first();
        Sanctum::actingAs($driver->user);

        $this->post('/api/v1/driver/documents', ['kind' => 'driving_licence', 'file' => $this->photo()], ['Accept' => 'application/json'])->assertJsonValidationErrors('file');
        $this->putJson("/api/v1/driver/vehicles/{$vehicle->id}", ['color' => 'أحمر'])->assertJsonValidationErrors('vehicle');
        $this->deleteJson("/api/v1/driver/vehicles/{$vehicle->id}")->assertJsonValidationErrors('vehicle');
        $this->postJson('/api/v1/driver/vehicles', $this->vehiclePayload(['plate_number' => 'ب 999']))->assertJsonValidationErrors('vehicle');
    }

    public function test_users_cannot_touch_other_drivers_vehicles(): void
    {
        $other = $this->completeDraftDriver();
        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/v1/driver')->assertSuccessful();

        $vehicle = $other->vehicles()->first();
        $this->putJson("/api/v1/driver/vehicles/{$vehicle->id}", ['color' => 'أحمر'])->assertNotFound();
        $this->deleteJson("/api/v1/driver/vehicles/{$vehicle->id}")->assertNotFound();
    }

    public function test_approval_activates_driver_vehicles_and_identity(): void
    {
        $driver = $this->completeDraftDriver();
        settings()->set('drivers.free_bookings', 5);
        $review = app(VerificationService::class)->submitDriver($driver, IdDocumentType::NationalId);

        app(VerificationService::class)->approve($review, Admin::factory()->create());

        $driver->refresh();
        $this->assertSame('active', $driver->status->value);
        $this->assertSame(5, $driver->free_bookings_remaining);
        $this->assertTrue($driver->canOperate());
        $this->assertSame('approved', $driver->vehicles()->first()->status->value);
        $this->assertSame('verified', $driver->user->id_verification_status->value);
        $this->whatsapp()->assertSentTo($driver->user->phone, 'driver_approved');

        Sanctum::actingAs($driver->user);
        $this->getJson('/api/v1/me')->assertJsonPath('data.is_driver', true)->assertJsonPath('data.driver_status', 'active');
    }

    public function test_driver_approval_also_settles_a_separate_pending_passenger_id(): void
    {
        $driver = $this->completeDraftDriver();
        $idReview = app(VerificationService::class)->submitIdentity($driver->user, IdDocumentType::NationalId);
        $driver->user->refresh();
        $review = app(VerificationService::class)->submitDriver($driver->fresh(), IdDocumentType::NationalId);

        app(VerificationService::class)->approve($review, Admin::factory()->create());

        $this->assertSame('approved', $idReview->fresh()->status->value);
        $this->assertSame(0, VerificationReview::pending()->count());
    }

    public function test_rejection_lets_driver_fix_and_resubmit(): void
    {
        $driver = $this->completeDraftDriver();
        $review = app(VerificationService::class)->submitDriver($driver, IdDocumentType::NationalId);
        app(VerificationService::class)->reject($review, Admin::factory()->create(), ['اللوحة غير ظاهرة في صورة العربية']);

        $driver->refresh();
        $vehicle = $driver->vehicles()->first();
        $this->assertSame('rejected', $driver->status->value);
        $this->assertSame('rejected', $vehicle->status->value);
        $this->assertSame('rejected', $driver->user->id_verification_status->value);
        $this->whatsapp()->assertSentTo($driver->user->phone, 'driver_rejected');

        Sanctum::actingAs($driver->user);
        $this->getJson('/api/v1/driver')->assertJsonPath('data.review.reasons', ['اللوحة غير ظاهرة في صورة العربية']);
        $this->post("/api/v1/driver/vehicles/{$vehicle->id}/documents", ['kind' => 'vehicle_front', 'file' => $this->photo()], ['Accept' => 'application/json'])->assertOk();
        $this->postJson('/api/v1/driver/submit', ['id_document_type' => 'national_id', 'accept_terms' => true])
            ->assertOk()->assertJsonPath('data.status', 'pending_review');

        $this->assertSame(2, $driver->verificationReviews()->count());
    }

    public function test_rejection_can_keep_identity_verified(): void
    {
        $driver = $this->completeDraftDriver();
        $review = app(VerificationService::class)->submitDriver($driver, IdDocumentType::NationalId);
        app(VerificationService::class)->reject($review, Admin::factory()->create(), ['المستند منتهي الصلاحية'], identityOk: true);

        $this->assertSame('verified', $driver->user->fresh()->id_verification_status->value);
        $this->assertSame('rejected', $driver->fresh()->status->value);
    }

    public function test_free_bookings_are_granted_only_on_first_approval(): void
    {
        $driver = DriverProfile::factory()->active()->create(['free_bookings_remaining' => 1, 'approved_at' => now()->subMonth()]);
        $driver->forceFill(['status' => 'rejected'])->save();
        $review = $driver->verificationReviews()->create(['status' => 'pending']);

        app(VerificationService::class)->approve($review, Admin::factory()->create());

        $this->assertSame(1, $driver->fresh()->free_bookings_remaining);
    }

    public function test_active_driver_adds_a_vehicle_reviewed_on_its_own(): void
    {
        $driver = DriverProfile::factory()->active()->create();
        Vehicle::factory()->approved()->for($driver)->create();
        Sanctum::actingAs($driver->user);

        $id = $this->postJson('/api/v1/driver/vehicles', $this->vehiclePayload(['type' => 'minibus', 'seats' => 14, 'plate_number' => 'ك 5555']))->assertSuccessful()->json('data.id');
        $this->postJson("/api/v1/driver/vehicles/$id/submit")->assertJsonValidationErrors('documents');

        $this->giveVehicleDocs(Vehicle::find($id));
        $this->postJson("/api/v1/driver/vehicles/$id/submit")->assertOk()->assertJsonPath('data.status', 'pending');

        $this->assertTrue($driver->fresh()->isActive(), 'driver stays active while the new vehicle is reviewed');
        $review = VerificationReview::where('subject_type', 'vehicle')->sole();
        app(VerificationService::class)->approve($review, Admin::factory()->create());
        $this->assertSame('approved', Vehicle::find($id)->status->value);
        $this->assertSame(2, $driver->approvedVehicles()->count());
    }

    public function test_draft_driver_cannot_submit_a_vehicle_alone(): void
    {
        $driver = $this->completeDraftDriver();
        Sanctum::actingAs($driver->user);

        $this->postJson("/api/v1/driver/vehicles/{$driver->vehicles()->first()->id}/submit")->assertJsonValidationErrors('vehicle');
    }

    public function test_notification_failure_does_not_break_approval(): void
    {
        $driver = $this->completeDraftDriver();
        $review = app(VerificationService::class)->submitDriver($driver, IdDocumentType::NationalId);
        $this->whatsapp()->shouldFail = true;

        app(VerificationService::class)->approve($review, Admin::factory()->create());

        $this->assertSame('active', $driver->fresh()->status->value);
    }

    public function test_push_goes_to_registered_devices_and_invalid_tokens_are_pruned(): void
    {
        $driver = $this->completeDraftDriver();
        $driver->user->deviceTokens()->createMany([
            ['token' => 'good', 'platform' => 'android'],
            ['token' => 'dead', 'platform' => 'android'],
        ]);
        $push = app(PushGateway::class);
        $push->invalidTokens = ['dead'];

        $review = app(VerificationService::class)->submitDriver($driver, IdDocumentType::NationalId);
        app(VerificationService::class)->approve($review, Admin::factory()->create());

        $this->assertSame(['good', 'dead'], $push->sent[0]['tokens']);
        $this->assertSame('driver_approved', $push->sent[0]['data']['event']);
        $this->assertSame(['good'], $driver->user->deviceTokens()->pluck('token')->all());
    }
}
