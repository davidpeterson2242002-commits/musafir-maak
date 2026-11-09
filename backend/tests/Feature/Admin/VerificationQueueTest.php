<?php

namespace Tests\Feature\Admin;

use App\Enums\AdminRole;
use App\Enums\IdDocumentType;
use App\Filament\Resources\Verifications\Pages\ListVerifications;
use App\Filament\Resources\Verifications\Pages\ReviewVerification;
use App\Filament\Widgets\OverviewStats;
use App\Models\Admin;
use App\Models\User;
use App\Models\VerificationReview;
use App\Services\VerificationService;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Tests\Concerns\BuildsApplications;
use Tests\TestCase;

class VerificationQueueTest extends TestCase
{
    use BuildsApplications;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('admin');
        $this->fakeStorage();
    }

    private function pendingDriverReview(): VerificationReview
    {
        return app(VerificationService::class)->submitDriver($this->completeDraftDriver(), IdDocumentType::NationalId);
    }

    private function pendingIdReview(): VerificationReview
    {
        $user = User::factory()->create();
        $this->giveIdentityDocs($user);

        return app(VerificationService::class)->submitIdentity($user, IdDocumentType::NationalId);
    }

    public function test_reviewer_sees_queue_and_review_pages(): void
    {
        $driverReview = $this->pendingDriverReview();
        $idReview = $this->pendingIdReview();
        $this->actingAs(Admin::factory()->role(AdminRole::Reviewer)->create(), 'admin');

        $this->get('/admin/verifications')->assertOk();
        $this->get("/admin/verifications/{$driverReview->id}")->assertOk()->assertSee('رخصة القيادة')->assertSee($driverReview->subject->vehicles->first()->plate_number);
        $this->get("/admin/verifications/{$idReview->id}")->assertOk()->assertSee('البطاقة القومية');
        $this->get('/admin/drivers')->assertOk();
        $this->get('/admin/drivers/'.$driverReview->subject_id)->assertOk();
    }

    public function test_finance_cannot_open_the_queue(): void
    {
        $this->actingAs(Admin::factory()->role(AdminRole::Finance)->create(), 'admin');
        $this->get('/admin/verifications')->assertForbidden();
    }

    public function test_tabs_filter_by_type_and_priority_comes_first(): void
    {
        $driverReview = $this->pendingDriverReview();
        $older = $this->pendingIdReview();
        $urgent = $this->pendingIdReview();
        $urgent->update(['priority' => 10]);
        $this->actingAs(Admin::factory()->create(), 'admin');

        Livewire::test(ListVerifications::class)
            ->assertCanSeeTableRecords([$urgent, $driverReview, $older], inOrder: true);

        Livewire::test(ListVerifications::class)->set('activeTab', 'drivers')
            ->assertCanSeeTableRecords([$driverReview])->assertCanNotSeeTableRecords([$older, $urgent]);
    }

    public function test_opening_a_review_claims_it(): void
    {
        $review = $this->pendingIdReview();
        $first = Admin::factory()->role(AdminRole::Reviewer)->create();
        $this->actingAs($first, 'admin');
        Livewire::test(ReviewVerification::class, ['record' => $review->id]);
        $this->assertSame($first->id, $review->fresh()->assigned_to);

        // A second reviewer sees the warning and does not steal the claim.
        $this->actingAs(Admin::factory()->role(AdminRole::Reviewer)->create(), 'admin');
        Livewire::test(ReviewVerification::class, ['record' => $review->id])->assertNotified();
        $this->assertSame($first->id, $review->fresh()->assigned_to);
    }

    public function test_approve_from_the_review_page(): void
    {
        $review = $this->pendingDriverReview();
        $admin = Admin::factory()->role(AdminRole::Reviewer)->create();
        $this->actingAs($admin, 'admin');

        Livewire::test(ReviewVerification::class, ['record' => $review->id])
            ->callAction('approve', ['note' => null])
            ->assertHasNoActionErrors();

        $this->assertSame('approved', $review->fresh()->status->value);
        $this->assertSame($admin->id, $review->fresh()->reviewed_by);
        $this->assertSame('active', $review->subject->fresh()->status->value);
    }

    public function test_reject_requires_a_reason_and_uses_settings_list(): void
    {
        $review = $this->pendingIdReview();
        settings()->set('verification.rejection_reasons', ['سبب مخصص']);
        $this->actingAs(Admin::factory()->create(), 'admin');

        Livewire::test(ReviewVerification::class, ['record' => $review->id])
            ->callAction('reject', ['reasons' => []])
            ->assertHasActionErrors(['reasons' => 'required']);

        Livewire::test(ReviewVerification::class, ['record' => $review->id])
            ->callAction('reject', ['reasons' => ['سبب مخصص'], 'note' => 'أعد التصوير'])
            ->assertHasNoActionErrors();

        $this->assertSame(['سبب مخصص'], $review->fresh()->reasons);
        $this->assertSame('rejected', $review->subject->fresh()->id_verification_status->value);
    }

    public function test_request_info(): void
    {
        $review = $this->pendingIdReview();
        $this->actingAs(Admin::factory()->create(), 'admin');

        Livewire::test(ReviewVerification::class, ['record' => $review->id])
            ->callAction('request_info', ['note' => 'ارفع صورة أوضح للبطاقة'])
            ->assertHasNoActionErrors();

        $this->assertSame('info_requested', $review->fresh()->status->value);
        $this->assertSame('ارفع صورة أوضح للبطاقة', $review->fresh()->note);
    }

    public function test_decided_review_hides_decision_buttons(): void
    {
        $review = $this->pendingIdReview();
        app(VerificationService::class)->approve($review, Admin::factory()->create());
        $this->actingAs(Admin::factory()->create(), 'admin');

        Livewire::test(ReviewVerification::class, ['record' => $review->id])
            ->assertActionHidden('approve')->assertActionHidden('reject')->assertActionVisible('next');
    }

    public function test_dashboard_shows_pending_counts(): void
    {
        $this->pendingDriverReview();
        $this->actingAs(Admin::factory()->create(), 'admin');
        $this->get('/admin')->assertOk();
        Livewire::test(OverviewStats::class)->assertSee('بانتظار التوثيق')->assertSee('سائقين 1');
    }
}
