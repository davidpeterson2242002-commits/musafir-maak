<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ParcelStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\DriverParcelResource;
use App\Models\Parcel;
use App\Services\ParcelService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Driver side of parcels (D11, D12). */
class DriverParcelController extends Controller
{
    private const WITH = ['sender', 'trip.route.origin', 'trip.route.destination', 'trip.driverProfile', 'conversation'];

    /** GET /driver/parcels?status=pending|active|done */
    public function index(Request $request): AnonymousResourceCollection
    {
        $driver = DriverController::driverOf($request);
        $status = $request->query('status', 'pending');

        $parcels = Parcel::whereIn('trip_id', $driver->trips()->select('id'))
            ->with(self::WITH)
            ->when($status === 'pending', fn ($q) => $q->where('status', ParcelStatus::Pending)->where('expires_at', '>', now())->orderBy('expires_at'))
            ->when($status === 'active', fn ($q) => $q->whereIn('status', [ParcelStatus::Accepted, ParcelStatus::PickedUp])->orderBy('id'))
            ->when($status === 'done', fn ($q) => $q->whereIn('status', [ParcelStatus::Delivered, ParcelStatus::Disputed])->latest('id'))
            ->limit(100)->get();

        return DriverParcelResource::collection($parcels);
    }

    public function accept(Request $request, Parcel $parcel, ParcelService $parcels): DriverParcelResource
    {
        $this->authorizeDriver($request, $parcel);

        return $this->resource($parcels->accept($parcel));
    }

    public function reject(Request $request, Parcel $parcel, ParcelService $parcels): DriverParcelResource
    {
        $this->authorizeDriver($request, $parcel);
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        return $this->resource($parcels->reject($parcel->load('sender'), $data['reason'] ?? null));
    }

    /** POST /driver/parcels/{parcel}/pickup (multipart photo) */
    public function pickup(Request $request, Parcel $parcel, ParcelService $parcels): DriverParcelResource
    {
        $this->authorizeDriver($request, $parcel);
        $request->validate(['photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.settings()->int('verification.max_upload_kb')]], [], ['photo' => 'صورة الارسالية']);

        return $this->resource($parcels->pickUp($parcel->load('sender'), $request->file('photo')));
    }

    public function refuse(Request $request, Parcel $parcel, ParcelService $parcels): DriverParcelResource
    {
        $this->authorizeDriver($request, $parcel);
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:500']], [], ['reason' => 'السبب']);

        return $this->resource($parcels->refuse($parcel->load('sender'), $data['reason']));
    }

    /** POST /driver/parcels/{parcel}/deliver {code} */
    public function deliver(Request $request, Parcel $parcel, ParcelService $parcels): DriverParcelResource
    {
        $this->authorizeDriver($request, $parcel);
        $data = $request->validate(['code' => ['required', 'digits:4']], [], ['code' => 'رمز التسليم']);

        return $this->resource($parcels->deliver($parcel->load('sender'), $data['code']));
    }

    private function authorizeDriver(Request $request, Parcel $parcel): void
    {
        abort_unless($parcel->trip()->value('driver_profile_id') === DriverController::driverOf($request)->id, 404);
    }

    private function resource(Parcel $parcel): DriverParcelResource
    {
        return new DriverParcelResource($parcel->fresh(self::WITH));
    }
}
