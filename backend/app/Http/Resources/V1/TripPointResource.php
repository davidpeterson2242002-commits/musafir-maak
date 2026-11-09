<?php

namespace App\Http\Resources\V1;

use App\Models\TripPoint;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TripPoint */
class TripPointResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'label' => $this->label, 'lat' => $this->lat, 'lng' => $this->lng];
    }
}
