<?php

namespace App\Http\Resources\V1;

use App\Enums\DocumentKind;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Vehicle */
class VehicleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'make' => $this->make,
            'model' => $this->model,
            'color' => $this->color,
            'year' => $this->year,
            'plate_number' => $this->plate_number,
            'seats' => $this->seats,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'editable' => $this->status->isEditable(),
            'documents' => DocumentResource::collection($this->documents),
            'missing_documents' => DocumentKind::values($this->missingDocuments(DocumentKind::vehicleKinds())),
            'review' => $this->whenLoaded('latestReview', fn () => $this->latestReview ? new ReviewResource($this->latestReview) : null),
        ];
    }
}
