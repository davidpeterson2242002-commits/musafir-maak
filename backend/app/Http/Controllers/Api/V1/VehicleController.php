<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\VehicleStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UploadVehicleDocumentRequest;
use App\Http\Requests\Api\V1\VehicleRequest;
use App\Http\Resources\V1\VehicleResource;
use App\Models\DriverProfile;
use App\Models\Vehicle;
use App\Services\DocumentStore;
use App\Services\VerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** Driver vehicles (screens D3, D16). */
class VehicleController extends Controller
{
    public const MAX_VEHICLES = 5;

    /** POST /driver/vehicles */
    public function store(VehicleRequest $request): VehicleResource
    {
        $driver = DriverController::driverOf($request);
        $this->assertDriverCanManage($driver);

        if ($driver->vehicles()->count() >= self::MAX_VEHICLES) {
            throw ValidationException::withMessages(['vehicle' => 'وصلت للحد الأقصى من العربيات ('.self::MAX_VEHICLES.').']);
        }

        $vehicle = $driver->vehicles()->create($request->validated() + ['status' => VehicleStatus::Draft]);

        return $this->resource($vehicle);
    }

    /** PUT /driver/vehicles/{vehicle} */
    public function update(VehicleRequest $request, Vehicle $vehicle): VehicleResource
    {
        $this->assertEditable($request, $vehicle);
        $vehicle->update($request->validated());

        return $this->resource($vehicle);
    }

    /** DELETE /driver/vehicles/{vehicle} — only vehicles not yet approved. */
    public function destroy(Request $request, Vehicle $vehicle): JsonResponse
    {
        $this->assertEditable($request, $vehicle);
        $vehicle->documents->each->delete();
        $vehicle->delete();

        return response()->json(['message' => 'تم حذف العربية.']);
    }

    /** POST /driver/vehicles/{vehicle}/documents — vehicle_front | vehicle_back | vehicle_side | vehicle_interior | vehicle_registration */
    public function upload(UploadVehicleDocumentRequest $request, Vehicle $vehicle, DocumentStore $store): VehicleResource
    {
        $this->assertEditable($request, $vehicle);
        $store->put($vehicle, $request->kind(), $request->file('file'));

        return $this->resource($vehicle);
    }

    /** POST /driver/vehicles/{vehicle}/submit — only for drivers already approved. */
    public function submit(Request $request, Vehicle $vehicle, VerificationService $verification): VehicleResource
    {
        $this->assertOwned($request, $vehicle);
        $verification->submitVehicle($vehicle);

        return $this->resource($vehicle->fresh());
    }

    private function assertOwned(Request $request, Vehicle $vehicle): DriverProfile
    {
        $driver = DriverController::driverOf($request);
        abort_unless($vehicle->driver_profile_id === $driver->id, 404);
        $vehicle->setRelation('driverProfile', $driver);

        return $driver;
    }

    private function assertEditable(Request $request, Vehicle $vehicle): void
    {
        $driver = $this->assertOwned($request, $vehicle);
        $this->assertDriverCanManage($driver);

        if (! $vehicle->status->isEditable()) {
            throw ValidationException::withMessages(['vehicle' => $vehicle->status === VehicleStatus::Pending
                ? 'العربية قيد المراجعة ولا يمكن تعديلها الآن.'
                : 'العربية معتمدة. لتعديل بياناتها تواصل مع الدعم.']);
        }
    }

    private function assertDriverCanManage(DriverProfile $driver): void
    {
        if (! $driver->status->isEditable() && ! $driver->isActive()) {
            throw ValidationException::withMessages(['vehicle' => 'لا يمكن تعديل العربيات في وضع حسابك الحالي.']);
        }
    }

    private function resource(Vehicle $vehicle): VehicleResource
    {
        return new VehicleResource($vehicle->load(['documents', 'latestReview']));
    }
}
