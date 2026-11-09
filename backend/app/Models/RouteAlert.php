<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RouteAlert extends Model
{
    protected $fillable = ['user_id', 'origin_city_id', 'destination_city_id', 'date', 'active'];

    protected function casts(): array
    {
        return ['date' => 'date:Y-m-d', 'active' => 'boolean', 'last_notified_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
