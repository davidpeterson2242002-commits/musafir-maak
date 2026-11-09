<?php

namespace App\Models;

use App\Enums\SettlementPurpose;
use App\Enums\SettlementStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/** A driver's payment (settlement or wallet top-up) awaiting admin confirmation. */
class SettlementRequest extends Model
{
    protected $fillable = [
        'driver_profile_id', 'payment_method_id', 'purpose', 'amount', 'reference_number',
        'receipt_disk', 'receipt_path', 'status',
    ];

    protected function casts(): array
    {
        return [
            'purpose' => SettlementPurpose::class,
            'status' => SettlementStatus::class,
            'amount' => 'integer',
            'reviewed_at' => 'datetime',
        ];
    }

    public function driverProfile(): BelongsTo
    {
        return $this->belongsTo(DriverProfile::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'reviewed_by');
    }

    public function receiptUrl(): ?string
    {
        return $this->receipt_path
            ? Storage::disk($this->receipt_disk)->temporaryUrl($this->receipt_path, now()->addMinutes(config('filesystems.signed_url_minutes')))
            : null;
    }

    public function isPending(): bool
    {
        return $this->status === SettlementStatus::Pending;
    }
}
