<?php

namespace App\Http\Resources\V1;

use App\Support\Phone;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/** The authenticated user's own profile. @mixin \App\Models\User */
class MeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'phone_display' => Phone::display($this->phone),
            'whatsapp_phone' => $this->whatsappNumber(),
            'email' => $this->email,
            'gender' => $this->gender?->value,
            'city' => new CityResource($this->whenLoaded('city')),
            'photo_url' => $this->photo_path
                ? Storage::disk(config('filesystems.private_disk'))->temporaryUrl($this->photo_path, now()->addMinutes(config('filesystems.signed_url_minutes')))
                : null,
            'status' => $this->status->value,
            'suspended_until' => $this->suspended_until?->toIso8601String(),
            'id_verification_status' => $this->id_verification_status->value,
            'rating_avg' => (float) $this->rating_avg,
            'rating_count' => $this->rating_count,
            'profile_completed' => $this->profileCompleted(),
            'terms_accepted' => $this->terms_accepted_at !== null,
            'is_driver' => false, // driver profiles arrive in M2
            'member_since' => $this->created_at?->toDateString(),
        ];
    }
}
