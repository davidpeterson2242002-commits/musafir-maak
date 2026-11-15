<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** API traffic for one minute (App\Support\ApiMetrics). */
class MetricMinute extends Model
{
    public $timestamps = false;

    protected $fillable = ['minute', 'requests', 'errors', 'slow', 'total_ms', 'max_ms', 'online'];

    protected function casts(): array
    {
        return ['minute' => 'datetime'];
    }
}
