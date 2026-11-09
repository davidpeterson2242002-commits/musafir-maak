<?php

namespace App\Http\Resources\V1;

use App\Models\LedgerEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin LedgerEntry */
class LedgerEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'bucket' => $this->bucket,
            'amount' => $this->amount,
            'balance_after' => $this->balance_after,
            'wallet_after' => $this->wallet_after,
            'reference' => $this->reference_type ? ['type' => $this->reference_type, 'id' => $this->reference_id] : null,
            'note' => $this->note,
            'created_at' => local_iso($this->created_at),
        ];
    }
}
