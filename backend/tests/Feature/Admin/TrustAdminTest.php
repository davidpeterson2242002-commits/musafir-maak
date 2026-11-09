<?php

namespace Tests\Feature\Admin;

use App\Enums\AdminRole;
use App\Enums\ReportReason;
use App\Filament\Resources\Parcels\Pages\ViewParcel;
use App\Filament\Resources\Ratings\Pages\ListRatings;
use App\Filament\Resources\Reports\Pages\ReviewReport;
use App\Filament\Widgets\TrustAlerts;
use App\Models\Admin;
use App\Models\Booking;
use App\Models\Parcel;
use App\Models\Report;
use App\Services\ChatService;
use App\Services\ParcelService;
use App\Services\RatingService;
use App\Services\ReportService;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class TrustAdminTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('admin');
        Storage::fake('local');
    }

    private function reportWithChat(): Report
    {
        $booking = Booking::factory()->create();
        $c = app(ChatService::class)->openFor($booking);
        app(ChatService::class)->send($c->load('subject.trip'), $booking->passenger, 'اتصل علي 0912345678');
        app(ChatService::class)->send($c->fresh()->load('subject.trip'), $booking->trip->driverProfile->user, 'السلام عليكم');

        return app(ReportService::class)->create($booking->trip->driverProfile->user, $booking->passenger, ReportReason::Harassment, 'رسائل مزعجة', $booking);
    }

    public function test_support_pages_render(): void
    {
        $report = $this->reportWithChat();
        $parcel = Parcel::factory()->create();
        Parcel::factory()->create(['trip_id' => $parcel->trip_id]);
        $this->actingAs(Admin::factory()->role(AdminRole::Support)->create(), 'admin');

        foreach (['/admin/reports', "/admin/reports/{$report->id}", '/admin/parcels', "/admin/parcels/{$parcel->id}",
            '/admin/flagged-chats', '/admin/ratings', '/admin/trips/'.$parcel->trip_id, '/admin/users/'.$report->reported_user_id] as $url) {
            $this->get($url)->assertOk();
        }

        // The chat log shows both the masked text and the original.
        $this->get("/admin/reports/{$report->id}")->assertSee('رقم مخفي')->assertSee('0912345678');
    }

    public function test_finance_cannot_open_reports_or_chats(): void
    {
        $this->actingAs(Admin::factory()->role(AdminRole::Finance)->create(), 'admin');
        $this->get('/admin/reports')->assertForbidden();
        $this->get('/admin/flagged-chats')->assertForbidden();
    }

    public function test_opening_a_report_starts_review_and_actions_work(): void
    {
        $report = $this->reportWithChat();
        $admin = Admin::factory()->role(AdminRole::Support)->create();
        $this->actingAs($admin, 'admin');

        Livewire::test(ReviewReport::class, ['record' => $report->id])
            ->callAction('warn', ['reason' => 'تنبيه أول'])->assertHasNoActionErrors();

        $this->assertSame('in_review', $report->fresh()->status->value);
        $this->assertSame($admin->id, $report->fresh()->handled_by);
        $this->assertSame('warn', $report->reportedUser->enforcementActions()->first()->action);
        $this->whatsapp()->assertSentTo($report->reportedUser->phone, 'account_warning');
    }

    public function test_enforcement_from_report_and_resolution(): void
    {
        $report = $this->reportWithChat();
        $admin = Admin::factory()->role(AdminRole::Support)->create();
        $this->actingAs($admin, 'admin');

        Livewire::test(ReviewReport::class, ['record' => $report->id])
            ->callAction('suspend', ['days' => 7, 'reason' => 'رسائل مسيئة'])
            ->assertHasNoActionErrors();
        $this->assertSame('suspended', $report->reportedUser->fresh()->status->value);
        $this->assertSame($report->id, $report->reportedUser->enforcementActions()->first()->report_id);

        Livewire::test(ReviewReport::class, ['record' => $report->id])
            ->callAction('resolve', ['note' => 'تم الإيقاف أسبوع'])
            ->assertHasNoActionErrors();
        $this->assertSame('resolved', $report->fresh()->status->value);
    }

    public function test_flagged_chat_is_viewable_and_audited_unflagged_is_not(): void
    {
        $booking = Booking::factory()->create();
        $flagged = app(ChatService::class)->openFor($booking);
        app(ChatService::class)->send($flagged->load('subject.trip'), $booking->passenger, 'رقمي 0912345678');

        $clean = app(ChatService::class)->openFor($other = Booking::factory()->create());
        app(ChatService::class)->send($clean->load('subject.trip'), $other->passenger, 'متى نتحرك؟');

        $this->actingAs($admin = Admin::factory()->role(AdminRole::Support)->create(), 'admin');

        $this->get("/admin/flagged-chats/{$flagged->id}")->assertOk();
        $this->assertDatabaseHas('audit_logs', ['action' => 'chat.viewed', 'subject_type' => 'conversation', 'subject_id' => $flagged->id, 'admin_id' => $admin->id]);
        $this->get("/admin/flagged-chats/{$clean->id}")->assertNotFound();
    }

    public function test_parcel_dispute_resolved_from_admin(): void
    {
        $parcel = app(ParcelService::class)->accept(Parcel::factory()->create());
        app(ParcelService::class)->pickUp($parcel->fresh(), UploadedFile::fake()->image('x.jpg'));
        app(ParcelService::class)->dispute($parcel->fresh(), 'لم تصل');
        $this->actingAs(Admin::factory()->role(AdminRole::Support)->create(), 'admin');

        Livewire::test(ViewParcel::class, ['record' => $parcel->id])
            ->callAction('resolve_delivered', ['note' => 'المستلم أكد'])->assertHasNoActionErrors();

        $this->assertSame('delivered', $parcel->fresh()->status->value);
        $this->assertSame('delivered', $parcel->fresh()->dispute_resolution);
    }

    public function test_hide_rating(): void
    {
        $b = Booking::factory()->create(['status' => 'completed', 'completed_at' => now()]);
        $rating = app(RatingService::class)->rate($b->passenger, $b, 1, 'كلام مسيء');
        $this->actingAs(Admin::factory()->role(AdminRole::Support)->create(), 'admin');

        Livewire::test(ListRatings::class)->callTableAction('toggle_hidden', $rating);

        $this->assertTrue($rating->fresh()->hidden);
        $this->assertSame(0, $b->trip->driverProfile->fresh()->rating_count);
    }

    public function test_trust_alerts_widget(): void
    {
        $this->reportWithChat();
        $this->actingAs(Admin::factory()->role(AdminRole::Support)->create(), 'admin');

        Livewire::test(TrustAlerts::class)->assertSee('بلاغات مفتوحة')->assertSee('1 منها بلاغات سلامة');
    }
}
