<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Rating extends Model
{
    public const AS_DRIVER = 'as_driver';

    public const AS_PASSENGER = 'as_passenger';

    protected $fillable = ['rater_id', 'ratee_id', 'subject_type', 'subject_id', 'role', 'stars', 'comment', 'tags', 'hidden'];

    protected function casts(): array
    {
        return ['tags' => 'array', 'hidden' => 'boolean', 'stars' => 'integer'];
    }

    public function rater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rater_id')->withTrashed();
    }

    public function ratee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ratee_id')->withTrashed();
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
