<?php

namespace App\Models;

use App\Enums\LedgerType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

/** Append-only money movement for a driver. Written only through LedgerService. */
class LedgerEntry extends Model
{
    public const UPDATED_AT = null;

    public const BUCKET_BALANCE = 'balance';

    public const BUCKET_WALLET = 'wallet';

    protected $fillable = [
        'driver_profile_id', 'type', 'bucket', 'amount', 'balance_after', 'wallet_after',
        'reference_type', 'reference_id', 'note', 'created_by_admin_id',
    ];

    protected function casts(): array
    {
        return ['type' => LedgerType::class, 'amount' => 'integer', 'balance_after' => 'integer', 'wallet_after' => 'integer'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Ledger entries are append-only.'));
        static::deleting(fn () => throw new LogicException('Ledger entries are append-only.'));
    }

    public function driverProfile(): BelongsTo
    {
        return $this->belongsTo(DriverProfile::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by_admin_id');
    }
}
