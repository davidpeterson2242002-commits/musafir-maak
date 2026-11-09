<?php

namespace Tests\Feature\Api;

use App\Enums\IdDocumentType;
use App\Models\Admin;
use App\Models\User;
use App\Models\VerificationReview;
use App\Services\VerificationService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsApplications;
use Tests\TestCase;

class IdentityVerificationTest extends TestCase
{
    use BuildsApplications;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeStorage();
    }

    public function test_user_uploads_id_documents_privately(): void
    {
        Sanctum::actingAs($user = User::factory()->create());

        $this->post('/api/v1/me/identity/documents', ['kind' => 'selfie', 'file' => $this->photo()], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.status', 'none')
            ->assertJsonPath('data.documents.0.kind', 'selfie')
            ->assertJsonPath('data.documents.0.url', fn ($url) => filled($url));

        $doc = $user->documents()->first();
        $this->assertStringStartsWith('documents/user/'.$user->id.'/', $doc->path);
        Storage::disk('local')->assertExists($doc->path);
    }

    public function test_reupload_replaces_old_file(): void
    {
        Sanctum::actingAs($user = User::factory()->create());

        $this->post('/api/v1/me/identity/documents', ['kind' => 'selfie', 'file' => $this->photo()], ['Accept' => 'application/json'])->assertOk();
        $old = $user->documents()->first()->path;
        $this->post('/api/v1/me/identity/documents', ['kind' => 'selfie', 'file' => $this->photo()], ['Accept' => 'application/json'])->assertOk();

        $this->assertSame(1, $user->documents()->count());
        Storage::disk('local')->assertMissing($old);
    }

    public function test_identity_endpoint_rejects_driver_document_kinds_and_non_images(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->post('/api/v1/me/identity/documents', ['kind' => 'driving_licence', 'file' => $this->photo()], ['Accept' => 'application/json'])
            ->assertJsonValidationErrors('kind');
        $this->post('/api/v1/me/identity/documents', ['kind' => 'selfie', 'file' => UploadedFile::fake()->create('x.pdf', 100, 'application/pdf')], ['Accept' => 'application/json'])
            ->assertJsonValidationErrors('file');
    }

    public function test_submit_requires_documents_for_chosen_type(): void
    {
        Sanctum::actingAs($user = User::factory()->create());
        $this->giveIdentityDocs($user, IdDocumentType::Passport); // selfie + passport

        $this->postJson('/api/v1/me/identity/submit', ['document_type' => 'national_id'])->assertJsonValidationErrors('documents');
        $this->postJson('/api/v1/me/identity/submit', ['document_type' => 'passport'])
            ->assertOk()->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('verification_reviews', ['subject_type' => 'user', 'subject_id' => $user->id, 'status' => 'pending', 'id_document_type' => 'passport']);
    }

    public function test_documents_are_locked_while_pending_and_after_verification(): void
    {
        Sanctum::actingAs($user = User::factory()->create());
        $this->giveIdentityDocs($user);
        $this->postJson('/api/v1/me/identity/submit', ['document_type' => 'national_id'])->assertOk();

        $this->post('/api/v1/me/identity/documents', ['kind' => 'selfie', 'file' => $this->photo()], ['Accept' => 'application/json'])
            ->assertJsonValidationErrors('file');
        $this->postJson('/api/v1/me/identity/submit', ['document_type' => 'national_id'])->assertJsonValidationErrors('identity');
    }

    public function test_admin_approval_verifies_and_notifies_on_whatsapp(): void
    {
        $user = User::factory()->create();
        $this->giveIdentityDocs($user);
        $review = app(VerificationService::class)->submitIdentity($user, IdDocumentType::NationalId);

        app(VerificationService::class)->approve($review, Admin::factory()->create());

        $this->assertSame('verified', $user->fresh()->id_verification_status->value);
        $this->assertSame('approved', $review->fresh()->status->value);
        $this->whatsapp()->assertSentTo($user->phone, 'id_approved');
        $this->assertDatabaseHas('audit_logs', ['action' => 'verification.approved', 'subject_type' => 'verification_review', 'subject_id' => $review->id]);
    }

    public function test_rejection_shows_reasons_and_allows_resubmission(): void
    {
        $user = User::factory()->create();
        $this->giveIdentityDocs($user);
        $review = app(VerificationService::class)->submitIdentity($user, IdDocumentType::NationalId);
        app(VerificationService::class)->reject($review, Admin::factory()->create(), ['صورة غير واضحة'], 'صوّر البطاقة في ضوء جيد');

        Sanctum::actingAs($user->fresh());
        $this->getJson('/api/v1/me/identity')
            ->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath('data.can_upload', true)
            ->assertJsonPath('data.review.reasons', ['صورة غير واضحة'])
            ->assertJsonPath('data.review.note', 'صوّر البطاقة في ضوء جيد');
        $this->whatsapp()->assertSentTo($user->phone, 'id_rejected');

        $this->post('/api/v1/me/identity/documents', ['kind' => 'national_id_front', 'file' => $this->photo()], ['Accept' => 'application/json'])->assertOk();
        $this->postJson('/api/v1/me/identity/submit', ['document_type' => 'national_id'])->assertOk()->assertJsonPath('data.status', 'pending');
        $this->assertSame(2, VerificationReview::where('subject_id', $user->id)->where('subject_type', 'user')->count());
    }

    public function test_booking_allowance_follows_setting(): void
    {
        $user = User::factory()->create(['id_verification_status' => 'pending']);
        $this->assertTrue($user->idAllowsBooking());

        settings()->set('verification.allow_first_booking_while_pending', false);
        $this->assertFalse($user->idAllowsBooking());

        $this->assertTrue(User::factory()->create(['id_verification_status' => 'verified'])->idAllowsBooking());
        $this->assertFalse(User::factory()->create(['id_verification_status' => 'rejected'])->idAllowsBooking());
        $this->assertFalse(User::factory()->create(['id_verification_status' => 'none'])->idAllowsBooking());
    }

    public function test_a_review_cannot_be_decided_twice(): void
    {
        $user = User::factory()->create();
        $this->giveIdentityDocs($user);
        $review = app(VerificationService::class)->submitIdentity($user, IdDocumentType::NationalId);
        $service = app(VerificationService::class);

        $service->approve($review, Admin::factory()->create());

        $this->expectException(\RuntimeException::class);
        $service->reject($review, Admin::factory()->create(), ['صورة غير واضحة']);
    }
}
