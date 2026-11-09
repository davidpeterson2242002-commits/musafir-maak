<?php

namespace App\Http\Resources\V1;

use App\Enums\IdVerificationStatus;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * What other users may see about someone (spec P13). Never includes phone numbers —
 * those are added by booking resources only after acceptance.
 *
 * @mixin User
 */
class PublicUserResource extends JsonResource
{
    public function __construct($resource, private ?float $rating = null, private ?int $ratingCount = null, private ?int $trips = null)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'photo_url' => $this->photo_path
                ? Storage::disk(config('filesystems.private_disk'))->temporaryUrl($this->photo_path, now()->addMinutes(config('filesystems.signed_url_minutes')))
                : null,
            'gender' => $this->gender?->value,
            'rating_avg' => $this->rating ?? (float) $this->rating_avg,
            'rating_count' => $this->ratingCount ?? $this->rating_count,
            'trips_count' => $this->trips,
            'verified' => $this->id_verification_status === IdVerificationStatus::Verified,
            'member_since' => $this->created_at?->toDateString(),
        ];
    }
}
