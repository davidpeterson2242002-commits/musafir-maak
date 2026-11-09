<?php

namespace App\Models;

use App\Enums\VehicleStatus;
use App\Enums\VehicleType;
use App\Models\Concerns\HasDocuments;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vehicle extends Model
{
    use HasDocuments, HasFactory, SoftDeletes;

    protected $fillable = [
        'driver_profile_id', 'type', 'make', 'model', 'color', 'year', 'plate_number', 'seats',
        'status', 'submitted_at', 'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => VehicleType::class,
            'status' => VehicleStatus::class,
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Vehicle $v) {
            $v->plate_number = trim(preg_replace('/\s+/u', ' ', $v->plate_number));
            $v->plate_normalized = self::normalizePlate($v->plate_number);
        });
    }

    /** Compare plates ignoring spaces, dashes and letter case; Arabic-Indic digits → ASCII. */
    public static function normalizePlate(string $plate): string
    {
        $plate = strtr($plate, ['٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9']);

        return mb_strtoupper(preg_replace('/[\s\-_.\/]+/u', '', $plate));
    }

    public function driverProfile(): BelongsTo
    {
        return $this->belongsTo(DriverProfile::class);
    }

    public function label(): string
    {
        return "{$this->make} {$this->model} — {$this->plate_number}";
    }
}
