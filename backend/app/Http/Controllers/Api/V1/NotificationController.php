<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\UserNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** The in-app notifications list behind the bell icon. */
class NotificationController extends Controller
{
    /** GET /me/notifications?before_id= — newest first, 30 per page. */
    public function index(Request $request): JsonResponse
    {
        $request->validate(['before_id' => ['nullable', 'integer']]);
        $user = $request->user();

        $items = UserNotification::where('user_id', $user->id)
            ->when($request->integer('before_id'), fn ($q, $id) => $q->where('id', '<', $id))
            ->orderByDesc('id')->limit(30)->get();

        return response()->json([
            'data' => $items->map(fn (UserNotification $n) => [
                'id' => $n->id,
                'event' => $n->event,
                'title' => $n->title,
                'body' => $n->body,
                'data' => ['event' => $n->event, ...($n->data ?? [])],
                'read' => $n->read_at !== null,
                'created_at' => local_iso($n->created_at),
            ])->values(),
            'unread' => UserNotification::where('user_id', $user->id)->whereNull('read_at')->count(),
            'has_more' => $items->count() === 30,
        ]);
    }

    /** POST /me/notifications/read {ids?: int[]} — no ids = mark everything read. */
    public function read(Request $request): JsonResponse
    {
        $data = $request->validate(['ids' => ['nullable', 'array', 'max:200'], 'ids.*' => ['integer']]);

        $count = UserNotification::where('user_id', $request->user()->id)->whereNull('read_at')
            ->when(! empty($data['ids']), fn ($q) => $q->whereIn('id', $data['ids']))
            ->update(['read_at' => now()]);

        return response()->json(['marked' => $count]);
    }
}
