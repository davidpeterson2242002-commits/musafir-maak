<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\DocumentKind;

class UploadVehicleDocumentRequest extends UploadDocumentRequest
{
    protected function allowedKinds(): array
    {
        return DocumentKind::vehicleKinds();
    }
}
