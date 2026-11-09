<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\SettlementPurpose;
use App\Enums\SettlementStatus;
use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\BalanceResource;
use App\Http\Resources\V1\LedgerEntryResource;
use App\Http\Resources\V1\PaymentMethodResource;
use App\Http\Resources\V1\SettlementRequestResource;
use App\Models\PaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/** Driver balance, ledger and payments (D13, D14). */
class BalanceController extends Controller
{
    /** GET /driver/balance */
    public function show(Request $request): BalanceResource
    {
        return new BalanceResource(DriverController::driverOf($request));
    }

    /** GET /driver/ledger */
    public function ledger(Request $request): AnonymousResourceCollection
    {
        return LedgerEntryResource::collection(
            DriverController::driverOf($request)->ledgerEntries()->latest('id')->paginate(30)
        );
    }

    /** GET /payment-methods — enabled methods with account details / agent locations. */
    public function paymentMethods(): AnonymousResourceCollection
    {
        return PaymentMethodResource::collection(PaymentMethod::where('enabled', true)->orderBy('sort')->get());
    }

    /** GET /driver/settlements */
    public function settlements(Request $request): AnonymousResourceCollection
    {
        return SettlementRequestResource::collection(
            DriverController::driverOf($request)->settlementRequests()->with('paymentMethod')->latest('id')->paginate(20)
        );
    }

    /**
     * POST /driver/settlements (multipart)
     * {payment_method_id, purpose: settlement|wallet_topup, amount, reference_number?, receipt (image)}
     */
    public function submit(Request $request): SettlementRequestResource
    {
        $driver = DriverController::driverOf($request);
        $method = PaymentMethod::where('enabled', true)->find($request->input('payment_method_id'));

        $data = $request->validate([
            'payment_method_id' => ['required', Rule::exists('payment_methods', 'id')->where('enabled', true)],
            'purpose' => ['required', Rule::enum(SettlementPurpose::class)],
            'amount' => ['required', 'integer', 'min:1', 'max:100000000'],
            'reference_number' => [$method?->requires_reference ? 'required' : 'nullable', 'string', 'max:100'],
            'receipt' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.settings()->int('verification.max_upload_kb')],
        ], [], [
            'payment_method_id' => 'طريقة الدفع', 'purpose' => 'الغرض', 'amount' => 'المبلغ',
            'reference_number' => 'رقم العملية', 'receipt' => 'صورة الإشعار',
        ]);

        $purpose = SettlementPurpose::from($data['purpose']);

        if ($purpose === SettlementPurpose::Settlement && $driver->balance_owed === 0) {
            throw new BusinessRuleException('لا يوجد رصيد مستحق للسداد.', 'nothing_to_settle');
        }
        if ($driver->settlementRequests()->where('status', SettlementStatus::Pending)->where('purpose', $purpose)->exists()) {
            throw new BusinessRuleException('عندك طلب دفع قيد المراجعة. انتظر تأكيده قبل إرسال طلب جديد.', 'payment_pending');
        }
        if ($purpose === SettlementPurpose::Settlement && filled($data['reference_number'] ?? null)
            && $driver->settlementRequests()->where('reference_number', $data['reference_number'])->where('status', '!=', SettlementStatus::Rejected)->exists()) {
            throw new BusinessRuleException('رقم العملية هذا مستخدم في طلب سابق.', 'duplicate_reference');
        }

        $disk = config('filesystems.private_disk');
        $path = $request->file('receipt')->store("receipts/{$driver->id}", ['disk' => $disk]);

        $settlement = $driver->settlementRequests()->create([
            'payment_method_id' => $method->id,
            'purpose' => $purpose,
            'amount' => $data['amount'],
            'reference_number' => $data['reference_number'] ?? null,
            'receipt_disk' => $disk,
            'receipt_path' => $path,
            'status' => SettlementStatus::Pending,
        ]);

        return new SettlementRequestResource($settlement->load('paymentMethod'));
    }
}
