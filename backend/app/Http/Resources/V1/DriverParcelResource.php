<?php

namespace App\Http\Resources\V1;

use App\Enums\ParcelStatus;
use App\Services\LedgerService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** A parcel as the driver sees it (D9, D11, D12). Never contains the delivery code. @mixin \App\Models\Parcel */
class DriverParcelResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $reveal = $this->revealsContacts();
        $driver = $this->trip->driverProfile;

        return [
            'id' => $this->id,
            'trip_id' => $this->trip_id,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'sender' => new PublicUserResource($this->sender),
            'sender_phone' => $reveal ? $this->sender->phone : null,
            'description' => $this->description,
            'size' => $this->size,
            'size_label' => $this->sizeLabel(),
            'photo_url' => $this->photoUrl(),
            'receiver_name' => $this->receiver_name,
            'receiver_phone' => $reveal ? $this->receiver_phone : null,
            'receiver_city' => $this->trip->route->destination->name_ar,
            'payer' => $this->payer,
            'price' => $this->price,
            'message' => $this->message,
            'expires_at' => $this->status === ParcelStatus::Pending ? local_iso($this->expires_at) : null,
            'pickup_photo_url' => $this->pickupPhotoUrl(),
            'code_attempts_left' => $this->status === ParcelStatus::PickedUp
                ? max(0, settings()->int('parcels.code_max_attempts') - $this->code_attempts) : null,
            'fee' => $this->status === ParcelStatus::Pending
                ? ['amount' => app(LedgerService::class)->previewParcelFee($driver, $this->resource), 'free' => $driver->free_bookings_remaining > 0]
                : ['amount' => $this->fee_amount, 'free' => $this->fee_free, 'reversed' => $this->fee_reversed_at !== null],
            'conversation_id' => $this->conversation?->id,
        ];
    }
}
