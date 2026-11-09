<?php

namespace App\Models;

use App\Enums\PointKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TripPoint extends Model
{
    protected $fillable = ['trip_id', 'kind', 'label', 'lat', 'lng', 'sort'];

    protected function casts(): array
    {
        return ['kind' => PointKind::class, 'lat' => 'float', 'lng' => 'float'];
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }
}
