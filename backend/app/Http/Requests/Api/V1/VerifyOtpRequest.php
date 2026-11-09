<?php

namespace App\Http\Requests\Api\V1;

class VerifyOtpRequest extends RequestOtpRequest
{
    public function rules(): array
    {
        return parent::rules() + [
            'code' => ['required', 'string', 'regex:/^\d{4,8}$/'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return ['code.regex' => 'الرمز يجب أن يكون أرقاماً فقط.'];
    }
}
