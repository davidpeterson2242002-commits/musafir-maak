<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\Gender;
use App\Support\Phone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('whatsapp_phone')) {
            $this->merge(['whatsapp_phone' => Phone::normalize($this->input('whatsapp_phone')) ?? $this->input('whatsapp_phone')]);
        }
    }

    public function rules(): array
    {
        $user = $this->user();
        $firstSetup = ! $user->profileCompleted();
        // Gender can be set once by the user; later changes go through support
        // (women-only trips depend on it).
        $genderLocked = $user->gender !== null;

        return [
            'name' => [$firstSetup ? 'required' : 'sometimes', 'string', 'min:2', 'max:80'],
            'gender' => $genderLocked
                ? ['sometimes', function ($attr, $value, $fail) use ($user) {
                    if ($value !== $user->gender->value) {
                        $fail('لا يمكن تغيير الجنس من التطبيق. تواصل مع الدعم.');
                    }
                }]
                : [$firstSetup ? 'required' : 'sometimes', Rule::enum(Gender::class)],
            'city_id' => [$firstSetup ? 'required' : 'sometimes', 'integer', Rule::exists('cities', 'id')->where('enabled', true)],
            'email' => ['sometimes', 'nullable', 'email:rfc', 'max:150'],
            'whatsapp_phone' => ['sometimes', 'nullable', 'string', function ($attr, $value, $fail) {
                if ($value !== null && ! Phone::isValid($value)) {
                    $fail('رقم الواتساب غير صحيح.');
                }
            }],
            'accept_terms' => [$user->terms_accepted_at ? 'sometimes' : 'required', 'accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'accept_terms.required' => 'يجب الموافقة على الشروط وسياسة الخصوصية.',
            'accept_terms.accepted' => 'يجب الموافقة على الشروط وسياسة الخصوصية.',
        ];
    }
}
