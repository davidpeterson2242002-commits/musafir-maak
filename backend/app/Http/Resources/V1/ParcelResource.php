<?php

namespace App\Http\Resources\V1;

use App\Enums\ParcelStatus;
use App\Models\Parcel;
use App\Services\ParcelService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A parcel from the sender's side (S2, S3). The delivery code is shown to the sender
 * once accepted, as a fallback if the receiver has no WhatsApp (spec 5.6).
 *
 * @mixin Parcel
 */
class ParcelResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $trip = $this->trip;
        $driverUser = $trip->driverProfile->user;
        $reveal = $this->revealsContacts();

        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'description' => $this->description,
            'size' => $this->size,
            'size_label' => $this->sizeLabel(),
            'photo_url' => $this->photoUrl(),
            'receiver_name' => $this->receiver_name,
            'receiver_phone' => $this->receiver_phone,
            'payer' => $this->payer,
            'price' => $this->price,
            'message' => $this->message,
            'created_at' => local_iso($this->created_at),
            'expires_at' => $this->status === ParcelStatus::Pending ? local_iso($this->expires_at) : null,
            'accepted_at' => local_iso($this->accepted_at),
            'picked_up_at' => local_iso($this->picked_up_at),
            'pickup_photo_url' => $this->pickupPhotoUrl(),
            'delivered_at' => local_iso($this->delivered_at),
            'rejection_reason' => $this->rejection_reason,
            'refusal_reason' => $this->refusal_reason,
            'cancel_reason' => $this->cancel_reason,
            'delivery_code' => $reveal && $this->status !== ParcelStatus::Delivered ? $this->delivery_code : null,
            'receiver_notified' => $this->receiver_notified_at !== null,
            'share_text' => $reveal ? app(ParcelService::class)->receiverMessage($this->resource) : null,
            'can_cancel' => in_array($this->status, [ParcelStatus::Pending, ParcelStatus::Accepted], true),
            'can_dispute' => $this->status === ParcelStatus::PickedUp
                || ($this->status === ParcelStatus::Delivered && $this->delivered_at?->gt(now()->subDays(settings()->int('parcels.dispute_days')))),
            'conversation_id' => $this->conversation?->id,
            'trip' => new TripResource($trip),
            'contact' => $reveal ? [
                'driver_phone' => $driverUser->phone,
                'driver_whatsapp' => $driverUser->whatsappNumber(),
                'plate_number' => $trip->vehicle->plate_number,
            ] : null,
        ];
    }
}
