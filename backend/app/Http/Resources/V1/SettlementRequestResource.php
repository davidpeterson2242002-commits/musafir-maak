<?php

namespace App\Http\Resources\V1;

use App\Models\SettlementRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SettlementRequest */
class SettlementRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'purpose' => $this->purpose->value,
            'purpose_label' => $this->purpose->label(),
            'amount' => $this->amount,
            'payment_method' => $this->paymentMethod->name,
            'reference_number' => $this->reference_number,
            'receipt_url' => $this->receiptUrl(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'reject_reason' => $this->reject_reason,
            'created_at' => local_iso($this->created_at),
            'reviewed_at' => local_iso($this->reviewed_at),
        ];
    }
}
