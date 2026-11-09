<?php

namespace App\Http\Requests\Api\V1;

use App\Support\Phone;
use Illuminate\Foundation\Http\FormRequest;

class RequestOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['phone' => Phone::normalize($this->input('phone')) ?? $this->input('phone')]);
    }

    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', function ($attr, $value, $fail) {
                if (! Phone::isValid($value)) {
                    $fail('رقم الهاتف غير صحيح. أدخل رقم سوداني مثل 0912345678.');
                }
            }],
        ];
    }
}
