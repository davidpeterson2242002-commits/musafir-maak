<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserBlock extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['blocker_id', 'blocked_id'];

    public function blocked(): BelongsTo
    {
        return $this->belongsTo(User::class, 'blocked_id')->withTrashed();
    }
}
