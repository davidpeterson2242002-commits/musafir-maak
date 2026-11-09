<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class BookTripRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'seats' => ['required', 'integer', 'min:1', 'max:20'],
            'pickup_point_id' => ['nullable', 'integer'],
            'extra_bags' => ['sometimes', 'integer', 'min:0', 'max:10'],
            'includes_family' => ['sometimes', 'boolean'],
            'message' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }

    public function attributes(): array
    {
        return ['seats' => 'عدد المقاعد', 'pickup_point_id' => 'نقطة التحرك', 'extra_bags' => 'الشنط الإضافية', 'message' => 'الرسالة'];
    }
}
