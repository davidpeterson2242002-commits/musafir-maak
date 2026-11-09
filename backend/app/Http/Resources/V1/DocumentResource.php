<?php

namespace App\Http\Resources\V1;

use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Document */
class DocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'kind' => $this->kind->value,
            'label' => $this->kind->label(),
            'url' => $this->temporaryUrl(), // signed, expires (filesystems.signed_url_minutes)
            'uploaded_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
