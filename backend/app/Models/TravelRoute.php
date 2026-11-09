<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An intercity route A → B (table `routes`). Named TravelRoute to avoid
 * confusion with Laravel's Route facade.
 */
class TravelRoute extends Model
{
    use Auditable;

    protected $table = 'routes';

    protected $fillable = [
        'origin_city_id', 'destination_city_id', 'enabled', 'max_price_per_seat',
        'closure_message', 'is_popular', 'sort',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'is_popular' => 'boolean',
            'max_price_per_seat' => 'integer',
        ];
    }

    public function origin(): BelongsTo
    {
        return $this->belongsTo(City::class, 'origin_city_id');
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(City::class, 'destination_city_id');
    }

    public function scopeBetween(Builder $query, int $originId, int $destinationId): Builder
    {
        return $query->where('origin_city_id', $originId)->where('destination_city_id', $destinationId);
    }

    /** Route is open only if it is enabled and both cities are available. */
    public function isOpen(): bool
    {
        return $this->enabled && $this->origin?->isAvailable() && $this->destination?->isAvailable();
    }

    public function label(): string
    {
        return ($this->origin?->name_ar ?? '?').' ← '.($this->destination?->name_ar ?? '?');
    }
}
