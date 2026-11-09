<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\DriverStatus;
use App\Enums\IdDocumentType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UploadDriverDocumentRequest;
use App\Http\Resources\V1\DriverResource;
use App\Models\DriverProfile;
use App\Services\DocumentStore;
use App\Services\VerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Driver registration and account (spec 5.1, screens D1–D5, D16). */
class DriverController extends Controller
{
    /** GET /driver — {data: null} if the user never started driver registration. */
    public function show(Request $request): JsonResponse|DriverResource
    {
        $driver = $request->user()->driverProfile()->first();

        return $driver ? $this->resource($driver) : response()->json(['data' => null]);
    }

    /** POST /driver — start registration ("سجّل كسائق"). Idempotent. */
    public function store(Request $request): DriverResource
    {
        $user = $request->user();

        if (! $user->profileCompleted()) {
            throw ValidationException::withMessages(['profile' => 'أكمل ملفك الشخصي أولاً.']);
        }
        if ($user->isSuspended()) {
            throw ValidationException::withMessages(['profile' => 'حسابك موقوف مؤقتاً.']);
        }

        $driver = $user->driverProfile()->firstOrCreate([], ['status' => DriverStatus::Draft]);

        return $this->resource($driver);
    }

    /** POST /driver/documents — kind: driving_licence */
    public function upload(UploadDriverDocumentRequest $request, DocumentStore $store): DriverResource
    {
        $driver = $this->driver($request);

        if (! $driver->status->isEditable()) {
            throw ValidationException::withMessages(['file' => 'لا يمكن تغيير المستندات أثناء المراجعة. لتحديث رخصة منتهية تواصل مع الدعم.']);
        }

        $store->put($driver, $request->kind(), $request->file('file'));

        return $this->resource($driver);
    }

    /** POST /driver/submit {id_document_type?: passport|national_id, accept_terms: true} */
    public function submit(Request $request, VerificationService $verification): DriverResource
    {
        $data = $request->validate([
            'id_document_type' => ['nullable', Rule::enum(IdDocumentType::class)],
            'accept_terms' => ['accepted'],
        ], ['accept_terms.accepted' => 'يجب الموافقة على شروط السائقين.'], ['id_document_type' => 'نوع الهوية']);

        $driver = $this->driver($request);
        $verification->submitDriver($driver, isset($data['id_document_type']) ? IdDocumentType::from($data['id_document_type']) : null);

        return $this->resource($driver->fresh());
    }

    public static function driverOf(Request $request): DriverProfile
    {
        $driver = $request->user()->driverProfile()->first();
        abort_if($driver === null, 404, 'لم تبدأ التسجيل كسائق بعد.');

        return $driver;
    }

    private function driver(Request $request): DriverProfile
    {
        return self::driverOf($request);
    }

    private function resource(DriverProfile $driver): DriverResource
    {
        return new DriverResource($driver->load([
            'user.documents', 'user.latestReview', 'documents', 'latestReview',
            'vehicles' => fn ($q) => $q->orderBy('id'),
            'vehicles.documents', 'vehicles.latestReview',
        ]));
    }
}
