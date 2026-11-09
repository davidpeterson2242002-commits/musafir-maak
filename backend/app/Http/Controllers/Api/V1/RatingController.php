<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\RatingResource;
use App\Models\Booking;
use App\Models\Parcel;
use App\Services\RatingService;
use Illuminate\Http\Request;

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

        return ['data' => $ratings->pendingFor($user)->map(function ($subject) use ($user, $ratings) {
            [$customerId, $driverUserId] = $ratings->parties($subject);
            $iAmDriver = $user->id === $driverUserId;
            $other = $iAmDriver ? ($subject instanceof Booking ? $subject->passenger : $subject->sender) : $subject->trip->driverProfile->user;

            return [
                'subject_type' => $subject->getMorphClass(),
                'subject_id' => $subject->id,
                'rate_as' => $iAmDriver ? 'driver' : ($subject instanceof Booking ? 'passenger' : 'sender'),
                'ratee' => ['id' => $other->id, 'name' => $other->name],
                'summary' => $subject->trip->route->label().' — '.local_time($subject->trip->departs_at)->format('Y-m-d'),
            ];
        })->values()];
    }
}
