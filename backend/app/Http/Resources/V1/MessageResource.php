<?php

namespace App\Http\Resources\V1;

use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Message */
class MessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'mine' => $this->sender_id === $request->user()?->id,
            'body' => $this->body,
            'was_masked' => $this->was_masked,
            'created_at' => local_iso($this->created_at),
        ];
    }
}
