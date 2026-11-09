<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\BookingStatus;
use App\Enums\TripStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\BookTripRequest;
use App\Http\Resources\V1\BookingResource;
use App\Http\Resources\V1\TripResource;
use App\Models\Booking;
use App\Models\Trip;
use App\Services\BookingService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Passenger bookings (P6–P8). */
class BookingController extends Controller
{
    private const WITH = [
        'trip.route.origin', 'trip.route.destination', 'trip.points', 'trip.vehicle.documents',
        'trip.driverProfile.user', 'pickupPoint',
    ];

    /** POST /trips/{trip}/bookings */
    public function store(BookTripRequest $request, Trip $trip, BookingService $bookings): BookingResource
    {
        $booking = $bookings->request($request->user(), $trip, $request->validated());

        return new BookingResource($booking->load(self::WITH));
    }

    /** GET /bookings?tab=upcoming|past|cancelled */
    public function index(Request $request): AnonymousResourceCollection
    {
        $tab = $request->query('tab', 'upcoming');

        $bookings = $request->user()->bookings()
            ->with(self::WITH)
            ->join('trips', 'trips.id', '=', 'bookings.trip_id')
            ->select('bookings.*')
            ->when($tab === 'upcoming', fn ($q) => $q->whereIn('bookings.status', BookingStatus::active())
                ->whereIn('trips.status', [TripStatus::Scheduled, TripStatus::Departed])->orderBy('trips.departs_at'))
            ->when($tab === 'past', fn ($q) => $q->whereIn('bookings.status', [BookingStatus::Completed, BookingStatus::NoShow])
                ->orderByDesc('trips.departs_at'))
            ->when($tab === 'cancelled', fn ($q) => $q->whereIn('bookings.status', [
                BookingStatus::CancelledByPassenger, BookingStatus::CancelledByDriver, BookingStatus::Rejected, BookingStatus::Expired,
            ])->orderByDesc('bookings.updated_at'))
            ->paginate(20);

        return BookingResource::collection($bookings);
    }

    /** GET /bookings/{booking} — rejected/expired/cancelled-by-driver also return alternatives (P7). */
    public function show(Request $request, Booking $booking): BookingResource
    {
        $this->authorizeOwner($request, $booking);
        $booking->load(self::WITH);

        $alternatives = [];
        if (in_array($booking->status, [BookingStatus::Rejected, BookingStatus::Expired, BookingStatus::CancelledByDriver], true)) {
            $tz = config('app.display_timezone');
            $day = local_time($booking->trip->departs_at)->startOfDay();
            $trips = Trip::bookable()
                ->where('route_id', $booking->trip->route_id)
                ->whereKeyNot($booking->trip_id)
                ->where('seats_available', '>=', $booking->seats)
                ->whereBetween('departs_at', [$day->copy()->utc(), $day->copy()->endOfDay()->utc()])
                ->with(['driverProfile.user', 'vehicle', 'route.origin', 'route.destination'])
                ->orderBy('departs_at')->limit(10)->get();
            $alternatives = TripResource::collection($trips)->resolve($request);
        }

        return (new BookingResource($booking))->additional(['alternatives' => $alternatives]);
    }

    /** POST /bookings/{booking}/cancel {reason?} */
    public function cancel(Request $request, Booking $booking, BookingService $bookings): BookingResource
    {
        $this->authorizeOwner($request, $booking);
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        $result = $bookings->cancelByPassenger($booking, $data['reason'] ?? null);

        return (new BookingResource($result['booking']->load(self::WITH)))->additional([
            'warning' => $result['warning'],
            'restricted_until' => $result['restricted_until'],
        ]);
    }

    /** POST /bookings/{booking}/dispute-no-show {details} */
    public function disputeNoShow(Request $request, Booking $booking, BookingService $bookings): BookingResource
    {
        $this->authorizeOwner($request, $booking);
        $data = $request->validate(['details' => ['required', 'string', 'min:5', 'max:1000']], [], ['details' => 'تفاصيل الاعتراض']);

        return new BookingResource($bookings->disputeNoShow($booking, $data['details'])->load(self::WITH));
    }

    private function authorizeOwner(Request $request, Booking $booking): void
    {
        abort_unless($booking->passenger_id === $request->user()->id, 404);
    }
}
