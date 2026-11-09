<?php

namespace App\Http\Resources\V1;

use App\Models\TravelRoute;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TravelRoute */
class RouteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $open = $this->isOpen();

        return [
            'id' => $this->id,
            'origin' => new CityResource($this->whenLoaded('origin')),
            'destination' => new CityResource($this->whenLoaded('destination')),
            'open' => $open,
            'closure_message' => $open ? null : ($this->closure_message ?: 'هذا الطريق موقوف مؤقتاً'),
            'max_price_per_seat' => $this->max_price_per_seat,
            'is_popular' => $this->is_popular,
        ];
    }
}
