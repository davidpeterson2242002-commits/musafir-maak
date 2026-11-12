<?php

namespace Tests\Feature\Api;

use App\Models\TrustedDevice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrustedDeviceTest extends TestCase
{
    use RefreshDatabase;

    private function signUp(string $phone = '0912345678'): array
    {
        settings()->set('otp.test_phones', [$phone]);
        $code = $this->postJson('/api/v1/auth/otp', ['phone' => $phone])->json('debug_code');

        return $this->postJson('/api/v1/auth/verify', ['phone' => $phone, 'code' => $code, 'device_name' => 'Pixel'])
            ->assertOk()->json();
    }

    public function test_verify_returns_a_device_token_that_logs_in_without_a_code(): void
    {
        $first = $this->signUp();
        $this->assertNotEmpty($first['device_token']);
        $this->assertSame(1, TrustedDevice::count());

        // Logged out (token revoked); the device token still works.
        $this->withToken($first['token'])->postJson('/api/v1/auth/logout')->assertOk();
        $this->app['auth']->forgetGuards();

        $again = $this->postJson('/api/v1/auth/device-login', ['phone' => '912345678', 'device_token' => $first['device_token']])
            ->assertOk()->assertJsonPath('is_new', false)->assertJsonPath('user.phone', '+249912345678')->json();
        $this->withToken($again['token'])->getJson('/api/v1/me')->assertOk();
    }

    public function test_wrong_number_or_unknown_token_needs_a_code(): void
    {
        $first = $this->signUp();
        $this->postJson('/api/v1/auth/device-login', ['phone' => '0911111111', 'device_token' => $first['device_token']])
            ->assertStatus(422)->assertJsonPath('code', 'device_not_trusted');
        $this->postJson('/api/v1/auth/device-login', ['phone' => '0912345678', 'device_token' => str_repeat('x', 64)])
            ->assertStatus(422)->assertJsonPath('code', 'device_not_trusted');
    }

    public function test_trust_expires_after_the_set_days_without_use_and_slides_with_use(): void
    {
        $first = $this->signUp();

        // Used on day 5 (app open) → good until day 12.
        $this->travel(5)->days();
        $this->withToken($first['token'])->getJson('/api/v1/me/badges')->assertOk();
        $this->travel(6)->days();
        $this->postJson('/api/v1/auth/device-login', ['phone' => '0912345678', 'device_token' => $first['device_token']])->assertOk();

        // Then 8 days untouched → a code again.
        $this->travel(8)->days();
        $this->postJson('/api/v1/auth/device-login', ['phone' => '0912345678', 'device_token' => $first['device_token']])
            ->assertStatus(422)->assertJsonPath('code', 'device_not_trusted');
    }

    public function test_zero_days_turns_it_off_and_ban_forgets_devices(): void
    {
        settings()->set('auth.trusted_device_days', 0);
        $this->assertNull($this->signUp()['device_token']);

        settings()->set('auth.trusted_device_days', 7);
        $second = $this->signUp('0913333333');
        User::where('phone', '+249913333333')->first()->forceFill(['status' => 'banned'])->save();
        $this->postJson('/api/v1/auth/device-login', ['phone' => '0913333333', 'device_token' => $second['device_token']])
            ->assertStatus(403);
        $this->assertSame(0, TrustedDevice::where('user_id', User::where('phone', '+249913333333')->value('id'))->count());
    }

    public function test_test_numbers_are_never_throttled(): void
    {
        settings()->set('otp.test_phones', ['0912345678']);
        for ($i = 0; $i < 15; $i++) {
            $code = $this->postJson('/api/v1/auth/otp', ['phone' => '0912345678'])->assertOk()->json('debug_code');
            $this->postJson('/api/v1/auth/verify', ['phone' => '0912345678', 'code' => $code])->assertOk();
        }
    }
}
