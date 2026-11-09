<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\VehicleStatus;
use App\Enums\VehicleType;
use App\Models\Vehicle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $creating = $this->isMethod('post');
        $req = $creating ? 'required' : 'sometimes';
        $type = VehicleType::tryFrom((string) $this->input('type', $this->route('vehicle')?->type?->value));

        return [
            'type' => [$req, Rule::enum(VehicleType::class)],
            'make' => [$req, 'string', 'max:60'],
            'model' => [$req, 'string', 'max:60'],
            'color' => [$req, 'string', 'max:40'],
            'year' => ['sometimes', 'nullable', 'integer', 'between:1970,'.(now()->year + 1)],
            'plate_number' => [$req, 'string', 'min:2', 'max:30', function ($attr, $value, $fail) {
                if ($this->plateTaken($value)) {
                    $fail('رقم اللوحة مسجّل بعربية أخرى. إذا كانت عربيتك تواصل مع الدعم.');
                }
            }],
            'seats' => [$req, 'integer', 'min:1', 'max:'.($type?->maxSeats() ?? 70)],
        ];
    }

    public function attributes(): array
    {
        return [
            'type' => 'نوع العربية', 'make' => 'الماركة', 'model' => 'الموديل', 'color' => 'اللون',
            'year' => 'سنة الصنع', 'plate_number' => 'رقم اللوحة', 'seats' => 'عدد المقاعد',
        ];
    }

    /** A plate may belong to one active vehicle only (rejected ones don't block). */
    private function plateTaken(string $plate): bool
    {
        return Vehicle::query()
            ->where('plate_normalized', Vehicle::normalizePlate($plate))
            ->where('status', '!=', VehicleStatus::Rejected)
            ->when($this->route('vehicle'), fn ($q, $v) => $q->whereKeyNot($v->id))
            ->exists();
    }
}
