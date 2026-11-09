<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ParcelStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\ParcelResource;
use App\Models\Parcel;
use App\Models\Trip;
use App\Services\ParcelService;
use App\Support\Phone;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/** Sender side of parcels (S1–S4). */
class ParcelController extends Controller
{
    public const WITH = [
        'trip.route.origin', 'trip.route.destination', 'trip.driverProfile.user', 'trip.vehicle', 'sender', 'conversation',
    ];

    /** POST /trips/{trip}/parcels (multipart) */
    public function store(Request $request, Trip $trip, ParcelService $parcels): ParcelResource
    {
        $request->merge(['receiver_phone' => Phone::normalize($request->input('receiver_phone')) ?? $request->input('receiver_phone')]);

        $data = $request->validate([
            'description' => ['required', 'string', 'min:3', 'max:500'],
            'size' => ['required', Rule::in(Parcel::SIZES)],
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.settings()->int('verification.max_upload_kb')],
            'receiver_name' => ['required', 'string', 'min:2', 'max:100'],
            'receiver_phone' => ['required', 'string', function ($attr, $value, $fail) {
                if (! Phone::isValid($value)) {
                    $fail('رقم المستلم غير صحيح.');
                }
            }],
            'payer' => ['required', 'in:sender,receiver'],
            'message' => ['nullable', 'string', 'max:500'],
            'accept_prohibited' => ['accepted'],
        ], ['accept_prohibited.accepted' => 'يجب تأكيد أن الارسالية لا تحتوي على ممنوعات وأنك مسؤول عن محتواها.'], [
            'description' => 'وصف الارسالية', 'size' => 'الحجم', 'photo' => 'صورة الارسالية', 'receiver_name' => 'اسم المستلم',
            'receiver_phone' => 'رقم المستلم', 'payer' => 'من يدفع',
        ]);

        $parcel = $parcels->request($request->user(), $trip, $data, $request->file('photo'));

        return new ParcelResource($parcel->load(self::WITH));
    }

    /** GET /parcels?tab=active|done */
    public function index(Request $request): AnonymousResourceCollection
    {
        $active = [ParcelStatus::Pending, ParcelStatus::Accepted, ParcelStatus::PickedUp, ParcelStatus::Disputed];

        return ParcelResource::collection(
            $request->user()->parcels()->with(self::WITH)
                ->when($request->query('tab', 'active') === 'active',
                    fn ($q) => $q->whereIn('status', $active),
                    fn ($q) => $q->whereNotIn('status', $active))
                ->latest('id')->paginate(20)
        );
    }

    public function show(Request $request, Parcel $parcel): ParcelResource
    {
        $this->authorizeSender($request, $parcel);

        return new ParcelResource($parcel->load(self::WITH));
    }

    public function cancel(Request $request, Parcel $parcel, ParcelService $parcels): ParcelResource
    {
        $this->authorizeSender($request, $parcel);
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        return new ParcelResource($parcels->cancelBySender($parcel->load(self::WITH), $data['reason'] ?? null)->load(self::WITH));
    }

    public function dispute(Request $request, Parcel $parcel, ParcelService $parcels): ParcelResource
    {
        $this->authorizeSender($request, $parcel);
        $data = $request->validate(['details' => ['required', 'string', 'min:5', 'max:1000']], [], ['details' => 'تفاصيل المشكلة']);

        return new ParcelResource($parcels->dispute($parcel, $data['details'])->load(self::WITH));
    }

    private function authorizeSender(Request $request, Parcel $parcel): void
    {
        abort_unless($parcel->sender_id === $request->user()->id, 404);
    }
}
