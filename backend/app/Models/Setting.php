<?php

namespace App\Models;

use App\Enums\SettingType;
use App\Models\Concerns\Auditable;
use App\Support\Settings;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use Auditable;

    protected $fillable = ['key', 'value', 'type', 'group', 'label'];

    protected function casts(): array
    {
        return [
            'value' => 'json',
            'type' => SettingType::class,
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => app(Settings::class)->flush());
        static::deleted(fn () => app(Settings::class)->flush());
    }
}
