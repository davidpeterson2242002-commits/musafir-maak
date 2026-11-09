<?php

namespace App\Http\Resources\V1;

use App\Support\RatingTags;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** A review as shown on a public profile. @mixin \App\Models\Rating */
class RatingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $labels = $this->role === 'as_driver' ? RatingTags::FOR_DRIVER : RatingTags::FOR_PASSENGER;

        return [
            'id' => $this->id,
            'stars' => $this->stars,
            'comment' => $this->comment,
            'tags' => collect($this->tags ?? [])->map(fn ($t) => ['key' => $t, 'label' => $labels[$t] ?? $t])->values(),
            'role' => $this->role,
            'rater_name' => $this->rater?->name,
            'created_at' => local_iso($this->created_at),
        ];
    }
}
