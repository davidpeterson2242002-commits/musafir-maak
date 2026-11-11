<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\ConversationResource;
use App\Http\Resources\V1\MessageResource;
use App\Models\Booking;
use App\Models\Conversation;
use App\Models\Parcel;
use App\Services\ChatService;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** In-app chat (P10). Polling-based: the app refreshes; push notifies new messages. */
class ConversationController extends Controller
{
    private function with(): array
    {
        return [
            'customer', 'driverUser', 'latestMessage',
            'subject' => fn (MorphTo $morph) => $morph->morphWith([
                Booking::class => ['trip.route.origin', 'trip.route.destination'],
                Parcel::class => ['trip.route.origin', 'trip.route.destination'],
            ]),
        ];
    }

    /** GET /conversations */
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $conversations = Conversation::where(fn ($q) => $q->where('customer_id', $user->id)->orWhere('driver_user_id', $user->id))
            ->with($this->with())
            ->orderByDesc('last_message_at')->orderByDesc('id')
            ->paginate(30);

        return ConversationResource::collection($conversations);
    }

    /** GET /conversations/{conversation}/messages?after_id=&before_id= — newest 50; marks as read. */
    public function messages(Request $request, Conversation $conversation, ChatService $chat): AnonymousResourceCollection
    {
        $this->authorizeParticipant($request, $conversation);

        $messages = $conversation->messages()
            ->when($request->integer('after_id'), fn ($q, $id) => $q->where('id', '>', $id))
            ->when($request->integer('before_id'), fn ($q, $id) => $q->where('id', '<', $id))
            ->orderByDesc('id')->limit(50)->get()->reverse()->values();

        $chat->markRead($conversation, $request->user());
        $request->user()->touchSeen();

        return MessageResource::collection($messages)->additional([
            'conversation' => (new ConversationResource($conversation->load($this->with())))->resolve($request),
        ]);
    }

    /** POST /conversations/{conversation}/messages {body} */
    public function send(Request $request, Conversation $conversation, ChatService $chat): MessageResource
    {
        $this->authorizeParticipant($request, $conversation);
        $data = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
            'client_id' => ['nullable', 'string', 'max:64'],
        ], [], ['body' => 'الرسالة']);

        return new MessageResource($chat->send($conversation->load('subject.trip'), $request->user(), $data['body'], $data['client_id'] ?? null));
    }

    /** GET /conversations/unread-count — badge for the Messages tab. */
    public function unreadCount(Request $request, ChatService $chat): array
    {
        $user = $request->user();
        $total = Conversation::where(fn ($q) => $q->where('customer_id', $user->id)->orWhere('driver_user_id', $user->id))
            ->where('last_message_at', '>=', now()->subDays(60))->get()
            ->sum(fn (Conversation $c) => $chat->unreadCount($c, $user));

        return ['unread' => $total];
    }

    private function authorizeParticipant(Request $request, Conversation $conversation): void
    {
        abort_unless($conversation->isParticipant($request->user()), 404);
    }
}
