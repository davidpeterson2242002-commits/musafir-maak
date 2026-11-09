<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\VehicleType;
use App\Services\SearchService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SearchTripsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'origin_city_id' => ['required', 'integer', 'exists:cities,id'],
            'destination_city_id' => ['required', 'integer', 'exists:cities,id', 'different:origin_city_id'],
            'date' => ['required', 'date_format:Y-m-d'],
            'seats' => ['sometimes', 'integer', 'min:1', 'max:20'],
            'kind' => ['sometimes', 'in:trip,parcel'],
            'time_of_day' => ['sometimes', 'array'],
            'time_of_day.*' => [Rule::in(array_keys(SearchService::TIMES_OF_DAY))],
            'max_price' => ['sometimes', 'integer', 'min:1'],
            'vehicle_types' => ['sometimes', 'array'],
            'vehicle_types.*' => [Rule::enum(VehicleType::class)],
            'women_only' => ['sometimes', 'boolean'],
            'min_rating' => ['sometimes', 'numeric', 'between:1,5'],
            'sort' => ['sometimes', 'in:earliest,cheapest,rating'],
        ];
    }

    public function attributes(): array
    {
        return ['origin_city_id' => 'مدينة الانطلاق', 'destination_city_id' => 'مدينة الوصول', 'date' => 'التاريخ', 'seats' => 'عدد المقاعد'];
    }
}
