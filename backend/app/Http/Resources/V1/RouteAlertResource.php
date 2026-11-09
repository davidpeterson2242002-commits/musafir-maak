<?php

namespace App\Http\Resources\V1;

use App\Models\RouteAlert;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin RouteAlert */
class RouteAlertResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'origin' => ['id' => $this->origin->id, 'name' => $this->origin->name_ar],
            'destination' => ['id' => $this->destination->id, 'name' => $this->destination->name_ar],
            'date' => $this->date?->toDateString(),
            'active' => $this->active,
            'last_notified_at' => local_iso($this->last_notified_at),
        ];
    }
}
