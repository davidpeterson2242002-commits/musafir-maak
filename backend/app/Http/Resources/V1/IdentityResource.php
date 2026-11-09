<?php

namespace App\Http\Resources\V1;

use App\Enums\DocumentKind;
use App\Enums\IdVerificationStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** The user's ID verification state. @mixin \App\Models\User */
class IdentityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'status' => $this->id_verification_status->value,
            'status_label' => $this->id_verification_status->label(),
            'can_upload' => in_array($this->id_verification_status, [IdVerificationStatus::None, IdVerificationStatus::Rejected], true),
            'allows_booking' => $this->idAllowsBooking(),
            'documents' => DocumentResource::collection($this->documents->whereIn('kind', DocumentKind::identityKinds())->values()),
            'review' => $this->latestReview ? new ReviewResource($this->latestReview) : null,
            'requirements' => [
                'passport' => ['selfie', 'passport'],
                'national_id' => ['selfie', 'national_id_front', 'national_id_back'],
            ],
        ];
    }
}
