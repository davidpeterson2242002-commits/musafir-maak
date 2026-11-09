<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\DocumentKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Multipart upload: `kind` + `file` (photo). Subclasses define which kinds the endpoint accepts.
 */
abstract class UploadDocumentRequest extends FormRequest
{
    /** @return array<int, DocumentKind> */
    abstract protected function allowedKinds(): array;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'kind' => ['required', Rule::in(DocumentKind::values($this->allowedKinds()))],
            'file' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.settings()->int('verification.max_upload_kb')],
        ];
    }

    public function attributes(): array
    {
        return ['kind' => 'نوع المستند', 'file' => 'الصورة'];
    }

    public function kind(): DocumentKind
    {
        return DocumentKind::from($this->validated('kind'));
    }
}
