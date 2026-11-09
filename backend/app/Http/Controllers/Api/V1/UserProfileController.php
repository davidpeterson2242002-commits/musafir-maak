<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\BookingStatus;
use App\Enums\TripStatus;
use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\PublicUserResource;
use App\Http\Resources\V1\RatingResource;
use App\Models\Rating;
use App\Models\User;
use App\Models\UserBlock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Public profiles and blocking (P13). */
class UserProfileController extends Controller
{
    /** GET /users/{user} — public info, driver stats, reviews. */
    public function show(Request $request, User $user): JsonResponse
    {
        abort_if($user->isBanned(), 404);
        $driver = $user->driverProfile()->first();

        $reviews = Rating::where('ratee_id', $user->id)->where('hidden', false)->with('rater')->latest('id')->limit(30)->get();

        return response()->json([
            'data' => (new PublicUserResource($user, trips: $user->bookings()->where('status', BookingStatus::Completed)->count()))->resolve($request),
            'driver' => $driver?->isActive() || $driver?->isFrozen() ? [
                'rating_avg' => (float) $driver->rating_avg,
                'rating_count' => $driver->rating_count,
                'completed_trips' => $driver->trips()->where('status', TripStatus::Completed)->count(),
                'driver_since' => $driver->approved_at?->toDateString(),
            ] : null,
            'reviews' => [
                'as_driver' => RatingResource::collection($reviews->where('role', Rating::AS_DRIVER)->values())->resolve($request),
                'as_passenger' => RatingResource::collection($reviews->where('role', Rating::AS_PASSENGER)->values())->resolve($request),
            ],
            'blocked_by_me' => UserBlock::where('blocker_id', $request->user()->id)->where('blocked_id', $user->id)->exists(),
        ]);
    }

    /** POST /users/{user}/block */
    public function block(Request $request, User $user): JsonResponse
    {
        if ($user->id === $request->user()->id) {
            throw new BusinessRuleException('لا يمكنك حظر نفسك.', 'self_block');
        }
        UserBlock::firstOrCreate(['blocker_id' => $request->user()->id, 'blocked_id' => $user->id]);

        return response()->json(['message' => 'تم الحظر. لن تظهر لك رحلاته ولن يستطيع مراسلتك.']);
    }

    /** DELETE /users/{user}/block */
    public function unblock(Request $request, User $user): JsonResponse
    {
        UserBlock::where('blocker_id', $request->user()->id)->where('blocked_id', $user->id)->delete();

        return response()->json(['message' => 'تم إلغاء الحظر.']);
    }

    /** GET /me/blocks */
    public function blocks(Request $request): JsonResponse
    {
        $blocks = $request->user()->blocks()->with('blocked')->latest('id')->get();

        return response()->json(['data' => $blocks->map(fn ($b) => ['id' => $b->blocked_id, 'name' => $b->blocked?->name, 'blocked_at' => local_iso($b->created_at)])]);
    }
}
