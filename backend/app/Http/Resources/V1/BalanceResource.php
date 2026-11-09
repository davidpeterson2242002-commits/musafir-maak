<?php

namespace App\Http\Resources\V1;

use App\Enums\SettlementStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Driver balance screen (D6 balance card, D13). @mixin \App\Models\DriverProfile */
class BalanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $limit = settings()->int('settlement.balance_limit');

        return [
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'balance_owed' => $this->balance_owed,
            'wallet_balance' => $this->wallet_balance,
            'wallet_mode' => settings()->string('wallet.mode'),
            'settlement_due_at' => local_iso($this->settlement_due_at),
            'freezes_at' => local_iso($this->freezesAt()),
            'overdue' => $this->settlement_due_at?->isPast() ?? false,
            'frozen_at' => local_iso($this->frozen_at),
            'balance_limit' => $limit ?: null,
            'balance_limit_reached' => $this->balanceLimitReached(),
            'free_bookings_remaining' => $this->free_bookings_remaining,
            'can_accept_bookings' => $this->isActive() && ! $this->balanceLimitReached(),
            'pending_payments' => (int) $this->settlementRequests()->where('status', SettlementStatus::Pending)->sum('amount'),
            // Fee terms shown on D1/D13 in plain words.
            'fee_terms' => [
                'model' => settings()->string('fees.model'),
                'cycle_days' => settings()->int('settlement.cycle_days'),
                'grace_hours' => settings()->int('settlement.grace_hours'),
            ],
        ];
    }
}
