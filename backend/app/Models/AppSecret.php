<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Encrypted key/value store for provider credentials set from the admin panel.
 * Values are never shown back in the admin UI or written to the audit log.
 */
class AppSecret extends Model
{
    public const FCM_CREDENTIALS = 'fcm_credentials';

    protected $fillable = ['key', 'value'];

    protected $hidden = ['value'];

    protected function casts(): array
    {
        return ['value' => 'encrypted'];
    }

    /** Null when missing, undecryptable (APP_KEY changed) or before migrations ran. */
    public static function read(string $key): ?string
    {
        try {
            return static::query()->where('key', $key)->first()?->value;
        } catch (Throwable) {
            return null;
        }
    }

    public static function put(string $key, string $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }

    public static function forget(string $key): void
    {
        static::query()->where('key', $key)->delete();
    }
}
