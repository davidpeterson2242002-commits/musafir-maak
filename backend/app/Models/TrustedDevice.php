<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A phone that verified a login code; see App\Services\TrustedDeviceService. */
class TrustedDevice extends Model
{
    protected $fillable = ['user_id', 'token_hash', 'device_name', 'access_token_id', 'last_used_at', 'expires_at'];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return ['last_used_at' => 'datetime', 'expires_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
