<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\BookingStatus;
use App\Enums\TripStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SearchTripsRequest;
use App\Http\Resources\V1\RouteResource;
use App\Http\Resources\V1\TripResource;
use App\Models\Trip;
use App\Services\SearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Passenger side: search and trip details (P4, P5, S1). */
class TripController extends Controller
{
    /** GET /trips/search?origin_city_id=&destination_city_id=&date=Y-m-d&seats=&kind=trip|parcel&... */
    public function search(SearchTripsRequest $request, SearchService $search): JsonResponse
    {
        $result = $search->search($request->validated(), $request->user());

        $result['trips']->load([
            'driverProfile' => fn ($q) => $q->withCount(['trips as completed_trips_count' => fn ($q) => $q->where('status', TripStatus::Completed)]),
            'driverProfile.user',
        ]);

        return response()->json([
            'data' => TripResource::collection($result['trips'])->resolve($request),
            'route' => $result['route'] ? (new RouteResource($result['route']))->resolve($request) : null,
            'closed' => $result['closed'],
            'closure_message' => $result['closure_message'],
            'nearby_dates' => $result['nearby_dates'],
        ]);
    }

    /** GET /trips/{trip} */
    public function show(Request $request, Trip $trip): TripResource
    {
        $isDriver = $trip->driverProfile->user_id === $request->user()->id;
        $hasBooking = $trip->bookings()->where('passenger_id', $request->user()->id)->exists();
        abort_if($trip->hidden_by_admin && ! $isDriver && ! $hasBooking, 404);

        $trip->load([
            'route.origin', 'route.destination', 'points', 'vehicle.documents',
            'driverProfile' => fn ($q) => $q->withCount(['trips as completed_trips_count' => fn ($q) => $q->where('status', TripStatus::Completed)]),
            'driverProfile.user',
        ]);

        return (new TripResource($trip))->withDetails()->additional([
            'bookable' => $trip->isBookable() && ! $isDriver && ! $request->user()->isBlockedWith($trip->driverProfile->user_id),
            'parcel_slots_left' => $trip->parcelSlotsLeft(),
            'my_booking_id' => $trip->bookings()->where('passenger_id', $request->user()->id)
                ->whereIn('status', BookingStatus::active())->value('id'),
        ]);
    }
}
