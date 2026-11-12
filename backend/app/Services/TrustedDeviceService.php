<?php

namespace App\Services;

use App\Models\TrustedDevice;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Support\Str;
use App\Exceptions\BusinessRuleException;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * "Trusted phone" login: after a phone verifies a code once, it gets a device
 * token (kept in the phone's secure storage). Logging back in on that phone with
 * the same number needs no new code while the device is trusted. Trust lasts
 * `auth.trusted_device_days` days and slides forward every time the app is used,
 * so a code is asked again only after that many days without opening the app,
 * or on a new phone. 0 days turns the feature off.
 */
class TrustedDeviceService
{
    public function __construct(private Settings $settings) {}

    public function enabled(): bool
    {
        return $this->days() > 0;
    }

    /** Called after a successful code check; returns the plain device token (or null when off). */
    public function trust(User $user, ?PersonalAccessToken $accessToken, ?string $deviceName): ?string
    {
        if (! $this->enabled()) {
            return null;
        }
        $plain = Str::random(64);
        TrustedDevice::create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $plain),
            'device_name' => $deviceName ? Str::limit($deviceName, 100, '') : null,
            'access_token_id' => $accessToken?->id,
            'last_used_at' => now(),
            'expires_at' => now()->addDays($this->days()),
        ]);

        return $plain;
    }

    /**
     * Logs in with a device token. Throws (code `device_not_trusted`) when the token
     * is unknown, belongs to another number or has expired — the app then asks for a code.
     */
    public function login(string $phone, string $plain): TrustedDevice
    {
        $device = $this->enabled()
            ? TrustedDevice::where('token_hash', hash('sha256', $plain))->with('user')->first()
            : null;

        if (! $device || $device->user?->phone !== $phone || $device->expires_at->isPast() || $device->user->trashed()) {
            if ($device && $device->expires_at->isPast()) {
                $device->delete();
            }
            throw new BusinessRuleException('محتاجين نتأكد من رقمك برمز جديد.', 'device_not_trusted');
        }

        return $device;
    }

    /** After a device login: link the new access token and push the expiry forward. */
    public function used(TrustedDevice $device, ?PersonalAccessToken $accessToken): void
    {
        $device->forceFill([
            'access_token_id' => $accessToken?->id ?? $device->access_token_id,
            'last_used_at' => now(),
            'expires_at' => now()->addDays($this->days()),
        ])->save();
    }

    /** The app is open on this device (from the badges poll): keep it trusted. */
    public function touch(mixed $accessToken): void
    {
        // Session cookies / test doubles carry no real token id.
        if (! $accessToken instanceof PersonalAccessToken || ! $accessToken->id || ! $this->enabled()) {
            return;
        }
        TrustedDevice::where('access_token_id', $accessToken->id)
            ->where(fn ($q) => $q->whereNull('last_used_at')->orWhere('last_used_at', '<', now()->subHour()))
            ->update(['last_used_at' => now(), 'expires_at' => now()->addDays($this->days())]);
    }

    public function forgetAll(User $user): void
    {
        TrustedDevice::where('user_id', $user->id)->delete();
    }

    private function days(): int
    {
        return $this->settings->int('auth.trusted_device_days');
    }
}
