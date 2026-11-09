<?php

namespace Tests\Feature\Admin;

use App\Enums\AdminRole;
use App\Filament\Resources\Drivers\Pages\ViewDriver;
use App\Filament\Resources\Ledger\Pages\ListLedger;
use App\Filament\Resources\PaymentMethods\Pages\ManagePaymentMethods;
use App\Filament\Resources\Settlements\Pages\ListSettlements;
use App\Filament\Resources\Settlements\Pages\ReviewSettlement;
use App\Filament\Widgets\FinanceStats;
use App\Models\Admin;
use App\Models\DriverProfile;
use App\Models\PaymentMethod;
use App\Models\SettlementRequest;
use App\Services\LedgerService;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class FinanceAdminTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('admin');
        Storage::fake('local');
    }

    private function pendingPayment(int $owed = 3000, int $amount = 3000): SettlementRequest
    {
        $driver = DriverProfile::factory()->active()->create();
        app(LedgerService::class)->adjust($driver, 'balance', $owed, 'seed', Admin::factory()->create());
        $path = UploadedFile::fake()->image('r.jpg')->store('receipts', ['disk' => 'local']);

        return $driver->settlementRequests()->create([
            'payment_method_id' => PaymentMethod::factory()->create()->id, 'purpose' => 'settlement', 'amount' => $amount,
            'reference_number' => 'TRX-1', 'receipt_disk' => 'local', 'receipt_path' => $path, 'status' => 'pending',
        ]);
    }

    public function test_finance_role_sees_finance_pages_others_dont(): void
    {
        $payment = $this->pendingPayment();
        $this->actingAs(Admin::factory()->role(AdminRole::Finance)->create(), 'admin');

        foreach (['/admin/payments', "/admin/payments/{$payment->id}", '/admin/payment-methods', '/admin/ledger', '/admin/drivers/'.$payment->driver_profile_id] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_reviewer_cannot_see_finance(): void
    {
        $this->actingAs(Admin::factory()->role(AdminRole::Reviewer)->create(), 'admin');
        $this->get('/admin/payments')->assertForbidden();
        $this->get('/admin/ledger')->assertForbidden();
        $this->assertFalse(FinanceStats::canView());
    }

    public function test_confirm_payment_from_queue(): void
    {
        $payment = $this->pendingPayment();
        $other = $this->pendingPayment();
        $this->actingAs(Admin::factory()->role(AdminRole::Finance)->create(), 'admin');

        Livewire::test(ListSettlements::class)->assertCanSeeTableRecords([$payment, $other]);

        Livewire::test(ReviewSettlement::class, ['record' => $payment->id])
            ->callAction('confirm')->assertHasNoActionErrors();

        $this->assertSame('confirmed', $payment->fresh()->status->value);
        $this->assertSame(0, $payment->driverProfile->fresh()->balance_owed);
        Livewire::test(ListSettlements::class)->assertCanNotSeeTableRecords([$payment])->assertCanSeeTableRecords([$other]);
    }

    public function test_reject_payment_requires_reason(): void
    {
        $payment = $this->pendingPayment();
        $this->actingAs(Admin::factory()->role(AdminRole::Finance)->create(), 'admin');

        Livewire::test(ReviewSettlement::class, ['record' => $payment->id])
            ->callAction('reject', ['reason' => ''])->assertHasActionErrors(['reason' => 'required']);
        Livewire::test(ReviewSettlement::class, ['record' => $payment->id])
            ->callAction('reject', ['reason' => 'رقم العملية غير صحيح'])->assertHasNoActionErrors();

        $this->assertSame('rejected', $payment->fresh()->status->value);
        $this->assertSame(3000, $payment->driverProfile->fresh()->balance_owed);
    }

    public function test_adjust_and_grant_free_bookings_from_driver_page(): void
    {
        $driver = DriverProfile::factory()->active()->create(['free_bookings_remaining' => 0]);
        $this->actingAs(Admin::factory()->role(AdminRole::Finance)->create(), 'admin');

        Livewire::test(ViewDriver::class, ['record' => $driver->id])
            ->callAction('adjust', ['bucket' => 'wallet', 'amount' => 5000, 'reason' => 'رصيد ترحيبي'])
            ->assertHasNoActionErrors()
            ->callAction('grant_free', ['count' => 2, 'reason' => 'سائق مميز'])
            ->assertHasNoActionErrors();

        $this->assertSame(5000, $driver->fresh()->wallet_balance);
        $this->assertSame(2, $driver->fresh()->free_bookings_remaining);

        Livewire::test(ViewDriver::class, ['record' => $driver->id])
            ->callAction('adjust', ['bucket' => 'wallet', 'amount' => -9999, 'reason' => 'خطأ'])
            ->assertNotified('التسوية تجعل الرصيد بالسالب.');
        $this->assertSame(5000, $driver->fresh()->wallet_balance);
    }

    public function test_support_cannot_adjust_money(): void
    {
        $driver = DriverProfile::factory()->active()->create();
        $this->actingAs(Admin::factory()->role(AdminRole::Support)->create(), 'admin');

        Livewire::test(ViewDriver::class, ['record' => $driver->id])->assertActionHidden('adjust')->assertActionHidden('grant_free');
    }

    public function test_payment_methods_can_be_added_and_disabled(): void
    {
        $this->actingAs(Admin::factory()->role(AdminRole::Finance)->create(), 'admin');

        Livewire::test(ManagePaymentMethods::class)
            ->callAction('create', ['name' => 'بنك فيصل', 'account_number' => '999', 'requires_reference' => true, 'enabled' => true, 'agent_locations' => []])
            ->assertHasNoActionErrors();

        $method = PaymentMethod::where('name', 'بنك فيصل')->sole();
        $method->update(['enabled' => false]);
        $this->assertDatabaseHas('audit_logs', ['subject_type' => 'payment_method', 'subject_id' => $method->id, 'action' => 'created']);
    }

    public function test_ledger_csv_export(): void
    {
        $this->pendingPayment();
        $this->actingAs(Admin::factory()->role(AdminRole::Finance)->create(), 'admin');

        $page = Livewire::test(ListLedger::class)->instance();
        $response = $page->exportCsv();
        ob_start();
        $response->sendContent();
        $csv = ob_get_clean();

        $this->assertStringStartsWith("\xEF\xBB\xBFid,created_at,driver", $csv);
        $this->assertStringContainsString('adjustment,balance,3000', $csv);
        $this->assertDatabaseHas('audit_logs', ['action' => 'ledger.exported']);
    }

    public function test_finance_widget(): void
    {
        $this->pendingPayment();
        $this->actingAs(Admin::factory()->role(AdminRole::Finance)->create(), 'admin');

        Livewire::test(FinanceStats::class)->assertSee('أرصدة مستحقة')->assertSee('3,000');
    }
}
