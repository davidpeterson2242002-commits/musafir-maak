<?php

namespace App\Http\Resources\V1;

use App\Models\Booking;
use App\Services\ChatService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Conversation list item / header (P10). @mixin \App\Models\Conversation */
class ConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $me = $request->user();
        $other = $me->id === $this->customer_id ? $this->driverUser : $this->customer;
        $subject = $this->subject;
        $trip = $subject->trip;
        $chat = app(ChatService::class);

        return [
            'id' => $this->id,
            'subject' => [
                'type' => $this->subject_type,
                'id' => $this->subject_id,
                'status' => $subject->status->value,
                'status_label' => $subject->status->label(),
                'summary' => $trip->route->label().' — '.local_time($trip->departs_at)->format('Y-m-d H:i'),
            ],
            'my_role' => $me->id === $this->driver_user_id ? 'driver' : ($subject instanceof Booking ? 'passenger' : 'sender'),
            'other' => new PublicUserResource($other),
            'last_message' => $this->whenLoaded('latestMessage', fn () => $this->latestMessage ? [
                'body' => $this->latestMessage->body,
                'mine' => $this->latestMessage->sender_id === $me->id,
                'created_at' => local_iso($this->latestMessage->created_at),
            ] : null),
            'unread' => $chat->unreadCount($this->resource, $me),
            'open' => $chat->isOpen($subject),
            'last_message_at' => local_iso($this->last_message_at),
        ];
    }
}
