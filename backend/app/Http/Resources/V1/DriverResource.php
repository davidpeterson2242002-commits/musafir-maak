<?php

namespace App\Http\Resources\V1;

use App\Enums\DocumentKind;
use App\Enums\IdVerificationStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** The driver's own registration / account state. @mixin \App\Models\DriverProfile */
class DriverResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $this->user;
        $identityDone = $user->id_verification_status === IdVerificationStatus::Verified
            || $user->missingDocuments([DocumentKind::Selfie]) === [] && (
                $user->missingDocuments([DocumentKind::Passport]) === []
                || $user->missingDocuments([DocumentKind::NationalIdFront, DocumentKind::NationalIdBack]) === []
            );

        return [
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'editable' => $this->status->isEditable(),
            'can_operate' => $this->canOperate(),
            'free_bookings_remaining' => $this->free_bookings_remaining,
            'balance_owed' => $this->balance_owed,
            'wallet_balance' => $this->wallet_balance,
            'rating_avg' => (float) $this->rating_avg,
            'rating_count' => $this->rating_count,
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'approved_at' => $this->approved_at?->toIso8601String(),
            // Registration checklist for screens D2–D4.
            'checklist' => [
                'identity' => $identityDone,
                'identity_verified' => $user->id_verification_status === IdVerificationStatus::Verified,
                'driving_licence' => $this->missingDocuments(DocumentKind::driverKinds()) === [],
                'vehicle' => $this->vehicles->contains(fn ($v) => $v->missingDocuments(DocumentKind::vehicleKinds()) === []),
            ],
            'documents' => DocumentResource::collection($this->documents),
            'identity' => new IdentityResource($user),
            'vehicles' => VehicleResource::collection($this->vehicles),
            'review' => $this->latestReview ? new ReviewResource($this->latestReview) : null,
        ];
    }
}
