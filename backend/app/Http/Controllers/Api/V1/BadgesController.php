<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\BookingStatus;
use App\Enums\DriverStatus;
use App\Enums\ParcelStatus;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Conversation;
use App\Models\Parcel;
use App\Services\ChatService;
use App\Services\RatingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * GET /me/badges — one small response the app polls every few seconds while open:
 * tab badges, the passenger's recent booking/parcel statuses (to spot "accepted" etc.)
 * and how many ratings are waiting. Also marks the user as online (chat ticks).
 */
class BadgesController extends Controller
{
    public function __invoke(Request $request, ChatService $chat, RatingService $ratings): array
    {
        $user = $request->user();
        $user->touchSeen();
        // App open on this phone: keep it trusted for login without a code.
        app(\App\Services\TrustedDeviceService::class)->touch($user->currentAccessToken());

        $unread = Conversation::where(fn ($q) => $q->where('customer_id', $user->id)->orWhere('driver_user_id', $user->id))
            ->where('last_message_at', '>=', now()->subDays(60))->get()
            ->sum(fn (Conversation $c) => $chat->unreadCount($c, $user));

        $driver = null;
        $profile = $user->driverProfile;
        if ($profile && in_array($profile->status, [DriverStatus::Active, DriverStatus::Frozen], true)) {
            $tripIds = $profile->trips()->select('id');
            $driver = [
                'pending_requests' => Booking::whereIn('trip_id', $tripIds)
                    ->where('status', BookingStatus::Pending)->where('expires_at', '>', now())->count(),
                'pending_parcels' => Parcel::whereIn('trip_id', $tripIds)
                    ->where('status', ParcelStatus::Pending)->where('expires_at', '>', now())->count(),
                // Changes whenever money moves or a payment is reviewed, so the app refreshes the
                // balance screens by itself (payment confirmed, freeze lifted…).
                'money_version' => implode(':', [
                    $profile->status->value,
                    (int) $profile->ledgerEntries()->max('id'),
                    (string) $profile->settlementRequests()->max('updated_at'),
                ]),
            ];
        }

        $since = now()->subDays(3);

        return [
            'unread_messages' => $unread,
            'driver' => $driver,
            'bookings' => Booking::where('passenger_id', $user->id)->where('updated_at', '>=', $since)
                ->orderByDesc('updated_at')->limit(20)->get(['id', 'status'])
                ->map(fn (Booking $b) => ['id' => $b->id, 'status' => $b->status->value])->values(),
            'parcels' => Parcel::where('sender_id', $user->id)->where('updated_at', '>=', $since)
                ->orderByDesc('updated_at')->limit(20)->get(['id', 'status'])
                ->map(fn (Parcel $p) => ['id' => $p->id, 'status' => $p->status->value])->values(),
            // Home rating cards: closed ones don't count.
            'pending_ratings' => $ratings->pendingFor($user)
                ->reject(fn ($s) => DB::table('rating_dismissals')->where(['user_id' => $user->id, 'subject_type' => $s->getMorphClass(), 'subject_id' => $s->id])->exists())
                ->count(),
        ];
    }
}
