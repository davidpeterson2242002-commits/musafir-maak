<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

/**
 * Typed, cached access to business settings.
 *
 *   settings()->int('booking.response_timeout_hours')
 *   settings()->get('fees.model')
 *
 * All values are loaded in one query and cached; the cache is flushed whenever
 * a Setting row is saved (see Setting::booted).
 */
class Settings
{
    public const CACHE_KEY = 'settings.all.v1';

    /** @var array<string, mixed>|null in-request memo */
    private ?array $values = null;

    public function get(string $key, mixed $default = null): mixed
    {
        $definition = SettingsRegistry::get($key);
        $values = $this->all();

        if (array_key_exists($key, $values)) {
            return $values[$key];
        }

        if ($definition !== null) {
            return $definition['type']->cast($definition['default']);
        }

        if (func_num_args() < 2) {
            throw new InvalidArgumentException("Unknown setting [$key]. Add it to SettingsRegistry.");
        }

        return $default;
    }

    public function int(string $key): int
    {
        return (int) $this->get($key);
    }

    public function float(string $key): float
    {
        return (float) $this->get($key);
    }

    public function bool(string $key): bool
    {
        return (bool) $this->get($key);
    }

    public function string(string $key): string
    {
        return (string) $this->get($key);
    }

    /** @return array<int, string> */
    public function list(string $key): array
    {
        return (array) $this->get($key);
    }

    /** Set a value (creates the row from the registry if needed). Audited via the model. */
    public function set(string $key, mixed $value): void
    {
        $definition = SettingsRegistry::get($key)
            ?? throw new InvalidArgumentException("Unknown setting [$key].");

        $setting = Setting::firstOrNew(['key' => $key]);
        $setting->fill([
            'type' => $definition['type'],
            'group' => $definition['group'],
            'label' => $definition['label'],
            'value' => $definition['type']->cast($value),
        ])->save();
    }

    /** Settings flagged `public` in the registry, exposed to the mobile app. */
    public function publicValues(): array
    {
        return collect(SettingsRegistry::definitions())
            ->filter(fn ($d) => $d['public'] ?? false)
            ->keys()
            ->mapWithKeys(fn ($key) => [$key => $this->get($key)])
            ->all();
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return $this->values ??= Cache::rememberForever(self::CACHE_KEY, function () {
            return Setting::query()->get()->mapWithKeys(
                fn (Setting $s) => [$s->key => $s->type->cast($s->value)]
            )->all();
        });
    }

    public function flush(): void
    {
        $this->values = null;
        Cache::forget(self::CACHE_KEY);
    }
}
