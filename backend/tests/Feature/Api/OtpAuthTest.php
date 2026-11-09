<?php

namespace Tests\Feature\Api;

use App\Models\OtpCode;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OtpAuthTest extends TestCase
{
    private function requestCode(string $phone = '0912345678'): string
    {
        $this->postJson('/api/v1/auth/otp', ['phone' => $phone])->assertOk();

        return $this->whatsapp()->last()['params']['code'];
    }

    public function test_request_sends_code_via_whatsapp_with_normalized_phone(): void
    {
        $this->postJson('/api/v1/auth/otp', ['phone' => '0912345678'])
            ->assertOk()
            ->assertJsonPath('phone', '+249912345678')
            ->assertJsonPath('resend_after', 60)
            ->assertJsonMissingPath('debug_code');

        $this->whatsapp()->assertSentTo('+249912345678', 'otp_code');
        $this->assertSame(6, strlen($this->whatsapp()->last()['params']['code']));
        $this->assertDatabaseCount('otp_codes', 1);
    }

    public function test_invalid_phone_is_rejected(): void
    {
        $this->postJson('/api/v1/auth/otp', ['phone' => '12345'])->assertUnprocessable()->assertJsonValidationErrors('phone');
        $this->whatsapp()->assertNothingSent();
    }

    public function test_resend_cooldown_comes_from_settings(): void
    {
        $this->requestCode();
        $this->postJson('/api/v1/auth/otp', ['phone' => '0912345678'])->assertStatus(429);

        $this->travel(61)->seconds();
        $this->postJson('/api/v1/auth/otp', ['phone' => '0912345678'])->assertOk();

        settings()->set('otp.resend_seconds', 300);
        $this->travel(61)->seconds();
        $this->postJson('/api/v1/auth/otp', ['phone' => '0912345678'])->assertStatus(429);
    }

    public function test_hourly_limit_per_phone(): void
    {
        settings()->set('otp.max_per_hour', 2);
        $this->requestCode();
        $this->travel(61)->seconds();
        $this->requestCode();
        $this->travel(61)->seconds();
        $this->postJson('/api/v1/auth/otp', ['phone' => '0912345678'])->assertStatus(429);
    }

    public function test_whatsapp_failure_returns_503_and_burns_code(): void
    {
        $this->whatsapp()->shouldFail = true;
        $this->postJson('/api/v1/auth/otp', ['phone' => '0912345678'])->assertStatus(503);
        $this->assertNotNull(OtpCode::first()->consumed_at);
    }

    public function test_verify_creates_new_user_and_returns_token(): void
    {
        $code = $this->requestCode();

        $response = $this->postJson('/api/v1/auth/verify', ['phone' => '+249912345678', 'code' => $code, 'device_name' => 'Pixel'])
            ->assertOk()
            ->assertJsonPath('is_new', true)
            ->assertJsonPath('user.phone', '+249912345678')
            ->assertJsonPath('user.profile_completed', false);

        $this->getJson('/api/v1/me', ['Authorization' => 'Bearer '.$response->json('token')])
            ->assertOk()->assertJsonPath('data.phone', '+249912345678');

        $this->assertNotNull(User::first()->phone_verified_at);
    }

    public function test_verify_logs_in_existing_user(): void
    {
        $user = User::factory()->create(['phone' => '+249912345678']);
        $code = $this->requestCode();

        $this->postJson('/api/v1/auth/verify', ['phone' => '0912345678', 'code' => $code])
            ->assertOk()
            ->assertJsonPath('is_new', false)
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.profile_completed', true);

        $this->assertDatabaseCount('users', 1);
    }

    public function test_code_cannot_be_reused(): void
    {
        $code = $this->requestCode();
        $this->postJson('/api/v1/auth/verify', ['phone' => '0912345678', 'code' => $code])->assertOk();
        $this->postJson('/api/v1/auth/verify', ['phone' => '0912345678', 'code' => $code])->assertUnprocessable();
    }

    public function test_wrong_code_counts_attempts_then_locks(): void
    {
        settings()->set('otp.max_attempts', 3);
        $code = $this->requestCode();
        $wrong = $code === '000000' ? '111111' : '000000';

        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/v1/auth/verify', ['phone' => '0912345678', 'code' => $wrong])->assertUnprocessable();
        }

        // Even the right code is refused once attempts are exhausted.
        $this->postJson('/api/v1/auth/verify', ['phone' => '0912345678', 'code' => $code])
            ->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_expired_code_is_rejected(): void
    {
        $code = $this->requestCode();
        $this->travel(11)->minutes();
        $this->postJson('/api/v1/auth/verify', ['phone' => '0912345678', 'code' => $code])->assertUnprocessable();
    }

    public function test_new_code_invalidates_previous_one(): void
    {
        $first = $this->requestCode();
        $this->travel(61)->seconds();
        $second = $this->requestCode();

        if ($first !== $second) {
            $this->postJson('/api/v1/auth/verify', ['phone' => '0912345678', 'code' => $first])->assertUnprocessable();
        }
        $this->postJson('/api/v1/auth/verify', ['phone' => '0912345678', 'code' => $second])->assertOk();
    }

    public function test_banned_user_cannot_log_in(): void
    {
        User::factory()->banned()->create(['phone' => '+249912345678']);
        $code = $this->requestCode();
        $this->postJson('/api/v1/auth/verify', ['phone' => '0912345678', 'code' => $code])
            ->assertForbidden()->assertJsonPath('code', 'account_banned');
    }

    public function test_banned_user_token_stops_working(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('t')->plainTextToken;
        $user->update(['status' => 'banned']);

        $this->getJson('/api/v1/me', ['Authorization' => "Bearer $token"])->assertForbidden();
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_logout_revokes_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('t')->plainTextToken;

        $this->postJson('/api/v1/auth/logout', [], ['Authorization' => "Bearer $token"])->assertOk();
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_otp_is_stored_hashed(): void
    {
        $code = $this->requestCode();
        $row = OtpCode::first();
        $this->assertNotSame($code, $row->code_hash);
        $this->assertTrue(Hash::check($code, $row->code_hash));
    }
}
