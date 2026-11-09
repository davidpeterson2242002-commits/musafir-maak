<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SearchLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['origin_city_id', 'destination_city_id', 'route_id', 'date', 'kind', 'results_count', 'user_id'];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    public function origin(): BelongsTo
    {
        return $this->belongsTo(City::class, 'origin_city_id');
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(City::class, 'destination_city_id');
    }
}
