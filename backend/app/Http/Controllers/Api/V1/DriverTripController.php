<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\BookingStatus;
use App\Enums\TripStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\TripRequest;
use App\Http\Resources\V1\DriverBookingResource;
use App\Http\Resources\V1\DriverTripResource;
use App\Models\Booking;
use App\Models\Trip;
use App\Services\BookingService;
use App\Services\TripService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Driver mode: trips and booking requests (D6–D10). */
class DriverTripController extends Controller
{
    /** GET /driver/trips?tab=upcoming|ongoing|past|cancelled */
    public function index(Request $request): AnonymousResourceCollection
    {
        $driver = DriverController::driverOf($request);
        $tab = $request->query('tab', 'upcoming');

        $trips = $driver->trips()
            ->with(['route.origin', 'route.destination', 'vehicle'])
            ->withCount(['bookings as pending_requests_count' => fn ($q) => $q->where('status', BookingStatus::Pending)])
            ->when($tab === 'upcoming', fn ($q) => $q->where('status', TripStatus::Scheduled)->orderBy('departs_at'))
            ->when($tab === 'ongoing', fn ($q) => $q->where('status', TripStatus::Departed)->orderBy('departs_at'))
            ->when($tab === 'past', fn ($q) => $q->where('status', TripStatus::Completed)->orderByDesc('departs_at'))
            ->when($tab === 'cancelled', fn ($q) => $q->where('status', TripStatus::Cancelled)->orderByDesc('departs_at'))
            ->paginate(20);

        return DriverTripResource::collection($trips);
    }

    /** POST /driver/trips */
    public function store(TripRequest $request, TripService $trips): DriverTripResource
    {
        $trip = $trips->post(DriverController::driverOf($request), $request->tripData());

        return $this->resource($trip);
    }

    /** GET /driver/trips/{trip} */
    public function show(Request $request, Trip $trip): DriverTripResource
    {
        $this->authorizeTrip($request, $trip);

        return $this->resource($trip);
    }

    /** PUT /driver/trips/{trip} */
    public function update(TripRequest $request, Trip $trip, TripService $trips): DriverTripResource
    {
        $this->authorizeTrip($request, $trip);

        return $this->resource($trips->update($trip, $request->tripData()));
    }

    /** POST /driver/trips/{trip}/cancel {reason} */
    public function cancel(Request $request, Trip $trip, TripService $trips): DriverTripResource
    {
        $this->authorizeTrip($request, $trip);
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:500']], [], ['reason' => 'سبب الإلغاء']);

        return $this->resource($trips->cancel($trip, $data['reason']));
    }

    /** POST /driver/trips/{trip}/depart ("انطلقت") */
    public function depart(Request $request, Trip $trip, TripService $trips): DriverTripResource
    {
        $this->authorizeTrip($request, $trip);

        return $this->resource($trips->depart($trip));
    }

    /** POST /driver/trips/{trip}/complete ("وصلت") */
    public function complete(Request $request, Trip $trip, TripService $trips): DriverTripResource
    {
        $this->authorizeTrip($request, $trip);

        return $this->resource($trips->complete($trip));
    }

    /** GET /driver/requests — all pending booking requests across trips, soonest deadline first. */
    public function requests(Request $request): AnonymousResourceCollection
    {
        $driver = DriverController::driverOf($request);

        $bookings = Booking::query()
            ->whereIn('trip_id', $driver->trips()->select('id'))
            ->where('status', BookingStatus::Pending)
            ->where('expires_at', '>', now())
            ->with(['passenger', 'pickupPoint', 'trip.route.origin', 'trip.route.destination', 'trip.driverProfile', 'trip.vehicle'])
            ->orderBy('expires_at')
            ->get();

        return DriverBookingResource::collection($bookings);
    }

    /** POST /driver/bookings/{booking}/accept */
    public function accept(Request $request, Booking $booking, BookingService $bookings): DriverBookingResource
    {
        $this->authorizeBooking($request, $booking);

        return $this->bookingResource($bookings->accept($booking));
    }

    /** POST /driver/bookings/{booking}/reject {reason?} */
    public function reject(Request $request, Booking $booking, BookingService $bookings): DriverBookingResource
    {
        $this->authorizeBooking($request, $booking);
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        return $this->bookingResource($bookings->reject($booking, $data['reason'] ?? null));
    }

    /** POST /driver/bookings/{booking}/no-show */
    public function noShow(Request $request, Booking $booking, BookingService $bookings): DriverBookingResource
    {
        $this->authorizeBooking($request, $booking);

        return $this->bookingResource($bookings->markNoShow($booking));
    }

    private function authorizeTrip(Request $request, Trip $trip): void
    {
        abort_unless($trip->driver_profile_id === DriverController::driverOf($request)->id, 404);
    }

    private function authorizeBooking(Request $request, Booking $booking): void
    {
        abort_unless($booking->trip()->value('driver_profile_id') === DriverController::driverOf($request)->id, 404);
    }

    private function resource(Trip $trip): DriverTripResource
    {
        return new DriverTripResource($trip->load([
            'route.origin', 'route.destination', 'vehicle', 'points',
            'bookings' => fn ($q) => $q->whereNotIn('status', [BookingStatus::Expired]),
            'bookings.passenger', 'bookings.pickupPoint', 'bookings.trip.driverProfile', 'bookings.trip.vehicle',
        ]));
    }

    private function bookingResource(Booking $booking): DriverBookingResource
    {
        return new DriverBookingResource($booking->fresh(['passenger', 'pickupPoint', 'trip.route.origin', 'trip.route.destination', 'trip.driverProfile', 'trip.vehicle']));
    }
}
