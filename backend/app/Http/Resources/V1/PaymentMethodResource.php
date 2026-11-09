<?php

namespace App\Http\Resources\V1;

use App\Models\PaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PaymentMethod */
class PaymentMethodResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'account_name' => $this->account_name,
            'account_number' => $this->account_number,
            'instructions' => $this->instructions,
            'agent_locations' => $this->agent_locations ?? [],
            'requires_reference' => $this->requires_reference,
        ];
    }
}
