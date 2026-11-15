<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\RatingResource;
use App\Models\Booking;
use App\Models\Parcel;
use App\Services\RatingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Ratings (P9, D15). */
class RatingController extends Controller
{
    /** POST /ratings {subject_type: booking|parcel, subject_id, stars 1–5, comment?, tags[]?} */
    public function store(Request $request, RatingService $ratings): RatingResource
    {
        $data = $request->validate([
            'subject_type' => ['required', 'in:booking,parcel'],
            'subject_id' => ['required', 'integer'],
            'stars' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:1000'],
            'tags' => ['sometimes', 'array', 'max:6'],
            'tags.*' => ['string', 'max:30'],
        ], [], ['stars' => 'التقييم', 'comment' => 'التعليق']);

        $subject = ($data['subject_type'] === 'booking' ? Booking::class : Parcel::class)::findOrFail($data['subject_id']);

        return new RatingResource($ratings->rate($request->user(), $subject, $data['stars'], $data['comment'] ?? null, $data['tags'] ?? [])->load('rater'));
    }

    /** GET /ratings/pending — what the app should ask the user to rate. */
    public function pending(Request $request, RatingService $ratings): array
    {
        $user = $request->user();

        $dismissed = DB::table('rating_dismissals')->where('user_id', $user->id)->get(['subject_type', 'subject_id'])
            ->map(fn ($d) => "$d->subject_type:$d->subject_id")->flip();

        return ['data' => $ratings->pendingFor($user)->map(function ($subject) use ($user, $ratings, $dismissed) {
            [$customerId, $driverUserId] = $ratings->parties($subject);
            $iAmDriver = $user->id === $driverUserId;
            $other = $iAmDriver ? ($subject instanceof Booking ? $subject->passenger : $subject->sender) : $subject->trip->driverProfile->user;

            return [
                'subject_type' => $subject->getMorphClass(),
                'subject_id' => $subject->id,
                'rate_as' => $iAmDriver ? 'driver' : ($subject instanceof Booking ? 'passenger' : 'sender'),
                'ratee' => ['id' => $other->id, 'name' => $other->name],
                'summary' => $subject->trip->route->label().' — '.local_time($subject->trip->departs_at)->format('Y-m-d'),
                // Closed with ✕ on the home card: still rateable from the booking / trip page.
                'dismissed' => $dismissed->has($subject->getMorphClass().':'.$subject->id),
            ];
        })->values()];
    }

    /** POST ratings/dismiss {items: [{subject_type, subject_id}]} — the ✕ on the home rating card. */
    public function dismiss(Request $request): array
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'max:50'],
            'items.*.subject_type' => ['required', 'in:booking,parcel'],
            'items.*.subject_id' => ['required', 'integer'],
        ]);
        $rows = collect($data['items'])->map(fn ($i) => [
            'user_id' => $request->user()->id, 'subject_type' => $i['subject_type'], 'subject_id' => $i['subject_id'], 'created_at' => now(),
        ])->all();
        DB::table('rating_dismissals')->insertOrIgnore($rows);

        return ['message' => 'ok'];
    }
}
