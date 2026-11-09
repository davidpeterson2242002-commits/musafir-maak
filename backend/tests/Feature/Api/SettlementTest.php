<?php

namespace Tests\Feature\Api;

use App\Exceptions\BusinessRuleException;
use App\Mail\NotificationMail;
use App\Models\Admin;
use App\Models\Booking;
use App\Models\DriverProfile;
use App\Models\LedgerEntry;
use App\Models\PaymentMethod;
use App\Models\SettlementRequest;
use App\Models\Trip;
use App\Services\BookingService;
use App\Services\LedgerService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsTrips;
use Tests\TestCase;

/** Weekly settlement, payments and freezing (spec 5.5, D13, D14). */
class SettlementTest extends TestCase
{
    use BuildsTrips;

    private DriverProfile $driver;

    private PaymentMethod $method;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Mail::fake();
        $this->method = PaymentMethod::factory()->create();
        $this->driver = DriverProfile::factory()->active()->create(['free_bookings_remaining' => 0]);
        $this->driver->user->update(['email' => 'driver@example.com']);
    }

    /** Accept one 1-seat booking → 1500 owed, cycle starts now. */
    private function owe(): Trip
    {
        $trip = $this->trip(['driver_profile_id' => $this->driver->id, 'departs_at' => now()->addDays(10)]);
        app(BookingService::class)->accept(Booking::factory()->create(['trip_id' => $trip->id]));

        return $trip;
    }

    private function pay(array $data = []): TestResponse
    {
        return $this->post('/api/v1/driver/settlements', $data + [
            'payment_method_id' => $this->method->id,
            'purpose' => 'settlement',
            'amount' => 1500,
            'reference_number' => 'TRX-'.random_int(1000, 9999),
            'receipt' => UploadedFile::fake()->image('receipt.jpg'),
        ], ['Accept' => 'application/json']);
    }

    private function d(): DriverProfile
    {
        return $this->driver->fresh();
    }

    public function test_balance_screen(): void
    {
        $this->owe();
        Sanctum::actingAs($this->driver->user);

        $this->getJson('/api/v1/driver/balance')->assertOk()
            ->assertJsonPath('data.balance_owed', 1500)
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.can_accept_bookings', true)
            ->assertJsonPath('data.balance_limit', 50000)
            ->assertJsonPath('data.overdue', false)
            ->assertJsonPath('data.fee_terms.cycle_days', 7);

        $this->getJson('/api/v1/driver/ledger')->assertJsonCount(1, 'data')->assertJsonPath('data.0.type', 'fee');
        $this->getJson('/api/v1/payment-methods')->assertJsonPath('data.0.account_number', '1234567');
    }

    public function test_driver_submits_payment_with_receipt(): void
    {
        $this->owe();
        Sanctum::actingAs($this->driver->user);

        $this->pay()->assertCreated()->assertJsonPath('data.status', 'pending')->assertJsonPath('data.receipt_url', fn ($u) => filled($u));
        Storage::disk('local')->assertExists(SettlementRequest::first()->receipt_path);

        $this->pay()->assertJsonPath('code', 'payment_pending');
        $this->getJson('/api/v1/driver/balance')->assertJsonPath('data.pending_payments', 1500)->assertJsonPath('data.balance_owed', 1500);
    }

    public function test_payment_validation(): void
    {
        Sanctum::actingAs($this->driver->user);
        $this->pay()->assertJsonPath('code', 'nothing_to_settle');

        $this->owe();
        $this->pay(['receipt' => null])->assertJsonValidationErrors('receipt');
        $this->pay(['reference_number' => null])->assertJsonValidationErrors('reference_number');

        $cash = PaymentMethod::factory()->create(['name' => 'كاش', 'requires_reference' => false]);
        $this->pay(['payment_method_id' => $cash->id, 'reference_number' => null])->assertCreated();

        $disabled = PaymentMethod::factory()->create(['enabled' => false]);
        $this->pay(['payment_method_id' => $disabled->id, 'purpose' => 'wallet_topup'])->assertJsonValidationErrors('payment_method_id');
    }

    public function test_reference_number_cannot_be_reused(): void
    {
        $this->owe();
        Sanctum::actingAs($this->driver->user);
        $this->pay(['reference_number' => 'SAME-1'])->assertCreated();
        app(LedgerService::class)->confirmSettlement(SettlementRequest::first(), Admin::factory()->create());

        $this->owe();
        $this->pay(['reference_number' => 'SAME-1'])->assertJsonPath('code', 'duplicate_reference');
    }

    public function test_confirmed_payment_clears_balance_and_emails_receipt(): void
    {
        $this->owe();
        Sanctum::actingAs($this->driver->user);
        $this->pay();

        app(LedgerService::class)->confirmSettlement(SettlementRequest::first(), $admin = Admin::factory()->create());

        $this->assertSame(0, $this->d()->balance_owed);
        $this->assertNull($this->d()->settlement_due_at);
        $this->assertSame('confirmed', SettlementRequest::first()->status->value);
        Mail::assertSent(NotificationMail::class, fn ($m) => $m->hasTo('driver@example.com') && str_contains($m->title, 'تأكيد الدفع'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'settlement.confirmed', 'admin_id' => $admin->id]);

        $this->expectException(BusinessRuleException::class);
        app(LedgerService::class)->confirmSettlement(SettlementRequest::first(), $admin);
    }

    public function test_overpayment_goes_to_wallet_and_partial_keeps_due_date(): void
    {
        $this->owe();
        $this->owe(); // 3000 owed
        $due = $this->d()->settlement_due_at;
        Sanctum::actingAs($this->driver->user);

        $this->pay(['amount' => 1000]);
        app(LedgerService::class)->confirmSettlement(SettlementRequest::latest('id')->first(), Admin::factory()->create());
        $this->assertSame(2000, $this->d()->balance_owed);
        $this->assertEquals($due->timestamp, $this->d()->settlement_due_at->timestamp);

        $this->pay(['amount' => 2500]);
        app(LedgerService::class)->confirmSettlement(SettlementRequest::latest('id')->first(), Admin::factory()->create());
        $this->assertSame([0, 500], [$this->d()->balance_owed, $this->d()->wallet_balance]);
    }

    public function test_wallet_topup(): void
    {
        Sanctum::actingAs($this->driver->user);
        $this->pay(['purpose' => 'wallet_topup', 'amount' => 10000])->assertCreated();
        app(LedgerService::class)->confirmSettlement(SettlementRequest::first(), Admin::factory()->create());

        $this->assertSame(10000, $this->d()->wallet_balance);
        $this->owe();
        $this->assertSame([8500, 0], [$this->d()->wallet_balance, $this->d()->balance_owed]);
    }

    public function test_rejected_payment_notifies_driver(): void
    {
        $this->owe();
        Sanctum::actingAs($this->driver->user);
        $this->pay();

        app(LedgerService::class)->rejectSettlement(SettlementRequest::first(), Admin::factory()->create(), 'المبلغ لم يصل');

        $this->assertSame(1500, $this->d()->balance_owed);
        $this->getJson('/api/v1/driver/settlements')->assertJsonPath('data.0.status', 'rejected')->assertJsonPath('data.0.reject_reason', 'المبلغ لم يصل');
        Mail::assertSent(NotificationMail::class, fn ($m) => str_contains($m->body, 'المبلغ لم يصل'));
        $this->pay()->assertCreated(); // can try again
    }

    public function test_reminder_then_freeze_after_due_date_and_grace(): void
    {
        $this->owe();

        $this->travel(4)->days();
        $this->artisan('musafir:tick');
        Mail::assertNotSent(NotificationMail::class, fn ($m) => str_contains($m->title, 'موعد سداد'));

        $this->travel(25)->hours(); // within 2 days of due
        $this->artisan('musafir:tick');
        $this->artisan('musafir:tick');
        Mail::assertSent(NotificationMail::class, 1);
        $this->assertNotNull($this->d()->settlement_reminded_at);

        $this->travel(2)->days(); // past due, within 24h grace
        $this->artisan('musafir:tick');
        $this->assertSame('active', $this->d()->status->value);

        $this->travel(1)->days();
        $this->artisan('musafir:tick');
        $this->assertSame('frozen', $this->d()->status->value);
        $this->whatsapp()->assertSentTo($this->driver->user->phone, 'account_frozen');
    }

    public function test_frozen_driver_is_blocked_until_fully_paid(): void
    {
        $trip = $this->owe();
        $this->driver->forceFill(['status' => 'frozen', 'frozen_at' => now()])->save();
        $pending = Booking::factory()->create(['trip_id' => $trip->id]);
        Sanctum::actingAs($this->driver->user);

        $this->postJson("/api/v1/driver/bookings/{$pending->id}/accept")->assertJsonPath('code', 'driver_frozen');
        $this->getJson('/api/v1/driver/balance')->assertJsonPath('data.can_accept_bookings', false);

        // Partial payment: still frozen.
        $this->pay(['amount' => 1000]);
        app(LedgerService::class)->confirmSettlement(SettlementRequest::latest('id')->first(), Admin::factory()->create());
        $this->assertSame('frozen', $this->d()->status->value);

        // Full payment: unfrozen automatically.
        $this->pay(['amount' => 500]);
        app(LedgerService::class)->confirmSettlement(SettlementRequest::latest('id')->first(), Admin::factory()->create());
        $this->assertSame('active', $this->d()->status->value);
        $this->assertNull($this->d()->frozen_at);
        $this->postJson("/api/v1/driver/bookings/{$pending->id}/accept")->assertOk();
    }

    public function test_accepted_bookings_stay_valid_when_frozen(): void
    {
        $trip = $this->owe();
        $accepted = $trip->bookings()->first();
        $this->driver->forceFill(['status' => 'frozen'])->save();

        Sanctum::actingAs($accepted->passenger);
        $this->getJson("/api/v1/bookings/{$accepted->id}")->assertJsonPath('data.status', 'accepted')
            ->assertJsonPath('data.contact.driver_phone', $this->driver->user->phone);
    }

    public function test_admin_adjustment_and_free_bookings(): void
    {
        $admin = Admin::factory()->create();
        app(LedgerService::class)->adjust($this->driver, 'balance', 2000, 'رسوم سابقة', $admin);
        $this->assertSame(2000, $this->d()->balance_owed);
        $this->assertNotNull($this->d()->settlement_due_at, 'owing starts a cycle');

        $this->expectException(BusinessRuleException::class);
        try {
            app(LedgerService::class)->adjust($this->d(), 'wallet', -1, 'خطأ', $admin);
        } finally {
            app(LedgerService::class)->grantFreeBookings($this->d(), 2, 'تعويض', $admin);
            $this->assertSame(2, $this->d()->free_bookings_remaining);
            $this->assertDatabaseHas('audit_logs', ['action' => 'ledger.adjusted']);
        }
    }

    public function test_weekly_statement_email(): void
    {
        $this->owe();
        $this->artisan('musafir:weekly-statements')->assertSuccessful();

        Mail::assertSent(NotificationMail::class, fn ($m) => str_contains($m->title, 'كشف حسابك') && str_contains($m->body, '1,500'));
    }

    public function test_ledger_is_append_only(): void
    {
        $this->owe();
        $this->expectException(\LogicException::class);
        LedgerEntry::first()->update(['amount' => 1]);
    }
}
