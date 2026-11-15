<?php

namespace Tests\Feature\Api;

use App\Mail\NotificationMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EmailOtpTest extends TestCase
{
    use RefreshDatabase;

    private function sentCode(string $to): string
    {
        $code = null;
        Mail::assertSent(NotificationMail::class, function (NotificationMail $mail) use ($to, &$code) {
            if (! $mail->hasTo($to)) {
                return false;
            }
            preg_match('/(\d{4,8})/', $mail->title, $m);
            $code = $m[1] ?? null;

            return true;
        });

        return (string) $code;
    }

    public function test_email_is_the_default_channel_and_new_accounts_must_give_one(): void
    {
        Mail::fake();

        $this->postJson('/api/v1/auth/otp', ['phone' => '0912345678'])
            ->assertStatus(422)->assertJsonPath('code', 'email_required');
        Mail::assertNothingSent();

        $this->postJson('/api/v1/auth/otp', ['phone' => '0912345678', 'email' => 'not-an-email'])
            ->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_new_account_signs_up_with_an_email_code(): void
    {
        Mail::fake();

        $this->postJson('/api/v1/auth/otp', ['phone' => '0912345678', 'email' => 'Sara@Gmail.com'])
            ->assertOk()
            ->assertJsonPath('channel', 'email')
            ->assertJsonPath('sent_to', 's•••@gmail.com')
            ->assertJsonMissingPath('debug_code');

        $code = $this->sentCode('sara@gmail.com');
        $this->assertSame(6, strlen($code));
        $this->assertDatabaseMissing('notification_logs', ['title' => $code]);

        $this->postJson('/api/v1/auth/verify', ['phone' => '0912345678', 'code' => $code])
            ->assertOk()->assertJsonPath('is_new', true);

        $user = User::where('phone', '+249912345678')->first();
        $this->assertSame('sara@gmail.com', $user->email);
        $this->assertNotNull($user->email_verified_at);
        $this->assertNull($user->phone_verified_at);
    }

    public function test_existing_account_always_gets_the_code_at_its_own_email(): void
    {
        Mail::fake();
        User::factory()->create(['phone' => '+249912345678', 'email' => 'owner@gmail.com']);

        // Typing another address must not redirect the code (account takeover).
        $this->postJson('/api/v1/auth/otp', ['phone' => '0912345678', 'email' => 'attacker@gmail.com'])
            ->assertOk()->assertJsonPath('sent_to', 'o•••@gmail.com');

        Mail::assertNotSent(NotificationMail::class, fn ($m) => $m->hasTo('attacker@gmail.com'));
        $code = $this->sentCode('owner@gmail.com');

        $this->postJson('/api/v1/auth/verify', ['phone' => '0912345678', 'code' => $code])->assertOk()
            ->assertJsonPath('is_new', false);
        $this->assertSame('owner@gmail.com', User::where('phone', '+249912345678')->value('email'));
    }

    public function test_an_email_of_another_account_is_refused(): void
    {
        Mail::fake();
        User::factory()->create(['phone' => '+249911111111', 'email' => 'taken@gmail.com']);

        $this->postJson('/api/v1/auth/otp', ['phone' => '0912345678', 'email' => 'TAKEN@gmail.com'])
            ->assertStatus(422)->assertJsonPath('code', 'email_taken');
    }

    public function test_test_numbers_need_no_email_and_get_the_code_in_the_response(): void
    {
        Mail::fake();
        settings()->set('otp.test_phones', ['0912345678']);

        $response = $this->postJson('/api/v1/auth/otp', ['phone' => '0912345678'])->assertOk();

        Mail::assertNothingSent();
        $this->postJson('/api/v1/auth/verify', ['phone' => '0912345678', 'code' => $response->json('debug_code')])->assertOk();
    }

    public function test_mail_failure_returns_503_and_burns_the_code(): void
    {
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => '127.0.0.1', 'mail.mailers.smtp.port' => 1, 'mail.mailers.smtp.timeout' => 1]);

        $this->postJson('/api/v1/auth/otp', ['phone' => '0912345678', 'email' => 'sara@gmail.com'])
            ->assertStatus(503);

        $this->assertDatabaseHas('otp_codes', ['phone' => '+249912345678', 'email' => 'sara@gmail.com']);
        $this->assertDatabaseMissing('otp_codes', ['phone' => '+249912345678', 'consumed_at' => null]);
        $this->assertDatabaseHas('notification_logs', ['phone' => '+249912345678', 'event' => 'otp_code', 'status' => 'failed']);
    }
}
