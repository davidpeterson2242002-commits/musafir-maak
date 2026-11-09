<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class City extends Model
{
    use Auditable;

    protected $fillable = ['state_id', 'name_ar', 'name_en', 'enabled', 'sort'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean'];
    }

    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class);
    }

    /** A city is usable only if it and its state are enabled. */
    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where($query->qualifyColumn('enabled'), true)
            ->whereHas('state', fn ($q) => $q->where('states.enabled', true));
    }

    public function isAvailable(): bool
    {
        return $this->enabled && $this->state?->enabled;
    }
}
