<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

/**
 * Post (POST) or edit (PUT) a trip. Times may be sent as "2026-11-12 07:30" (Sudan time)
 * or ISO-8601 with an offset; they are stored in UTC.
 */
class TripRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $req = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'origin_city_id' => [$this->isMethod('post') ? 'required' : 'prohibited', 'integer', 'exists:cities,id'],
            'destination_city_id' => [$this->isMethod('post') ? 'required' : 'prohibited', 'integer', 'exists:cities,id'],
            'vehicle_id' => [$req, 'integer'],
            'departs_at' => [$req, 'date'],
            'est_arrival_at' => ['sometimes', 'nullable', 'date', 'after:departs_at'],
            'seats_total' => [$req, 'integer', 'min:1', 'max:70'],
            'price_per_seat' => [$req, 'integer', 'min:1', 'max:10000000'],
            'pickup_points' => [$req, 'array', 'min:1', 'max:10'],
            'pickup_points.*.label' => ['required', 'string', 'max:150'],
            'pickup_points.*.lat' => ['nullable', 'numeric', 'between:-90,90'],
            'pickup_points.*.lng' => ['nullable', 'numeric', 'between:-180,180'],
            'dropoff_points' => [$req, 'array', 'min:1', 'max:10'],
            'dropoff_points.*.label' => ['required', 'string', 'max:150'],
            'dropoff_points.*.lat' => ['nullable', 'numeric', 'between:-90,90'],
            'dropoff_points.*.lng' => ['nullable', 'numeric', 'between:-180,180'],
            'luggage_large' => ['sometimes', 'integer', 'min:0', 'max:5'],
            'luggage_small' => ['sometimes', 'integer', 'min:0', 'max:5'],
            'extra_luggage_price' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'accepts_parcels' => ['sometimes', 'boolean'],
            'parcel_price_small' => ['required_if_accepted:accepts_parcels', 'nullable', 'integer', 'min:1'],
            'parcel_price_medium' => ['required_if_accepted:accepts_parcels', 'nullable', 'integer', 'min:1'],
            'parcel_price_large' => ['required_if_accepted:accepts_parcels', 'nullable', 'integer', 'min:1'],
            'max_parcels' => ['required_if_accepted:accepts_parcels', 'nullable', 'integer', 'min:0', 'max:50'],
            'women_only' => ['sometimes', 'boolean'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'origin_city_id' => 'مدينة الانطلاق', 'destination_city_id' => 'مدينة الوصول', 'vehicle_id' => 'العربية',
            'departs_at' => 'موعد الانطلاق', 'est_arrival_at' => 'الوصول المتوقع', 'seats_total' => 'عدد المقاعد',
            'price_per_seat' => 'سعر المقعد', 'pickup_points' => 'نقاط التحرك', 'dropoff_points' => 'نقاط النزول',
            'pickup_points.*.label' => 'اسم نقطة التحرك', 'dropoff_points.*.label' => 'اسم نقطة النزول',
            'parcel_price_small' => 'سعر الارسالية الصغيرة', 'parcel_price_medium' => 'سعر الارسالية المتوسطة',
            'parcel_price_large' => 'سعر الارسالية الكبيرة', 'max_parcels' => 'أقصى عدد ارساليات',
        ];
    }

    /** Validated data with times converted to UTC Carbon instances. */
    public function tripData(): array
    {
        $data = $this->validated();
        $tz = config('app.display_timezone');

        foreach (['departs_at', 'est_arrival_at'] as $field) {
            if (! empty($data[$field])) {
                $data[$field] = Carbon::parse($data[$field], $tz)->utc();
            }
        }

        if (array_key_exists('accepts_parcels', $data) && ! $data['accepts_parcels']) {
            $data['max_parcels'] = 0;
        }

        return $data;
    }
}
