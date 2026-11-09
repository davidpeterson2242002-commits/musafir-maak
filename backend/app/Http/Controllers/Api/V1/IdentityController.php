<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\IdDocumentType;
use App\Enums\IdVerificationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UploadIdentityDocumentRequest;
use App\Http\Resources\V1\IdentityResource;
use App\Services\DocumentStore;
use App\Services\VerificationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Passenger ID verification (spec 5.12, screen P6 ID step). */
class IdentityController extends Controller
{
    public function show(Request $request): IdentityResource
    {
        return new IdentityResource($request->user()->load(['documents', 'latestReview']));
    }

    /** POST /me/identity/documents — kind: selfie | passport | national_id_front | national_id_back */
    public function upload(UploadIdentityDocumentRequest $request, DocumentStore $store): IdentityResource
    {
        $user = $request->user();

        if (! in_array($user->id_verification_status, [IdVerificationStatus::None, IdVerificationStatus::Rejected], true)) {
            throw ValidationException::withMessages(['file' => $user->id_verification_status === IdVerificationStatus::Pending
                ? 'لا يمكن تغيير المستندات أثناء المراجعة.'
                : 'هويتك موثّقة. لتحديثها تواصل مع الدعم.']);
        }

        $store->put($user, $request->kind(), $request->file('file'));

        return new IdentityResource($user->load(['documents', 'latestReview']));
    }

    /** POST /me/identity/submit {document_type: passport|national_id} */
    public function submit(Request $request, VerificationService $verification): IdentityResource
    {
        $data = $request->validate(['document_type' => ['required', Rule::enum(IdDocumentType::class)]], [], ['document_type' => 'نوع الهوية']);

        $verification->submitIdentity($request->user(), IdDocumentType::from($data['document_type']));

        return new IdentityResource($request->user()->fresh()->load(['documents', 'latestReview']));
    }
}
