<?php

namespace App\Services;

use App\Contracts\OtpSender;
use App\Gateways\GatewayException;
use App\Models\OtpCode;
use App\Support\Settings;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Phone OTP: issue + verify. All limits come from settings (otp.*).
 */
class OtpService
{
    public function __construct(
        private OtpSender $sender,
        private Settings $settings,
    ) {}

    /**
     * Issue a new code for $phone (already normalized) and send it.
     *
     * @return array{code: OtpCode, plain: string, resend_after: int}
     */
    public function issue(string $phone, ?string $ip = null): array
    {
        $resendSeconds = $this->settings->int('otp.resend_seconds');
        $latest = OtpCode::where('phone', $phone)->latest('id')->first();
        // Admin-listed test numbers skip the resend wait and the hourly cap,
        // so testers can log in and out repeatedly.
        $isTest = $this->isTestPhone($phone);
        if ($isTest) {
            $resendSeconds = 0;
        }

        if (! $isTest && $latest && $latest->created_at->gt(now()->subSeconds($resendSeconds))) {
            $wait = (int) ceil($resendSeconds - $latest->created_at->diffInSeconds(now()));
            throw new HttpException(429, "انتظر $wait ثانية قبل طلب رمز جديد.", null, ['Retry-After' => max($wait, 1)]);
        }

        $sentLastHour = OtpCode::where('phone', $phone)->where('created_at', '>', now()->subHour())->count();
        if (! $isTest && $sentLastHour >= $this->settings->int('otp.max_per_hour')) {
            throw new HttpException(429, 'طلبت رموزاً كثيرة. حاول مرة أخرى بعد ساعة.', null, ['Retry-After' => 3600]);
        }

        $length = $this->settings->int('otp.length');
        $plain = str_pad((string) random_int(0, 10 ** $length - 1), $length, '0', STR_PAD_LEFT);
        $ttl = $this->settings->int('otp.ttl_minutes');

        // Only one active code per phone.
        OtpCode::where('phone', $phone)->whereNull('consumed_at')->update(['consumed_at' => now()]);

        $code = OtpCode::create([
            'phone' => $phone,
            'code_hash' => Hash::make($plain),
            'expires_at' => now()->addMinutes($ttl),
            'ip_address' => $ip,
        ]);

        try {
            $this->sender->send($phone, $plain, $ttl);
        } catch (GatewayException $e) {
            Log::warning('OTP delivery failed', ['phone' => $phone, 'error' => $e->getMessage()]);
            $code->update(['consumed_at' => now()]);
            throw new HttpException(503, 'تعذّر إرسال الرمز عبر واتساب. تأكد أن الرقم عليه واتساب وحاول مرة أخرى.');
        }

        return ['code' => $code, 'plain' => $plain, 'resend_after' => $resendSeconds];
    }

    /**
     * Verify a code. Consumes it on success.
     *
     * @throws ValidationException with an Arabic message on failure
     */
    public function verify(string $phone, string $plain): void
    {
        $code = OtpCode::where('phone', $phone)->whereNull('consumed_at')->latest('id')->first();

        if ($code === null || $code->expires_at->isPast()) {
            throw ValidationException::withMessages(['code' => 'انتهت صلاحية الرمز. اطلب رمزاً جديداً.']);
        }

        if ($code->attempts >= $this->settings->int('otp.max_attempts')) {
            $code->update(['consumed_at' => now()]);
            throw ValidationException::withMessages(['code' => 'تجاوزت عدد المحاولات. اطلب رمزاً جديداً.']);
        }

        if (! Hash::check($plain, $code->code_hash)) {
            $code->increment('attempts');
            $remaining = $this->settings->int('otp.max_attempts') - $code->attempts;
            throw ValidationException::withMessages([
                'code' => $remaining > 0
                    ? "الرمز غير صحيح. تبقّت $remaining محاولة."
                    : 'الرمز غير صحيح. اطلب رمزاً جديداً.',
            ]);
        }

        $code->update(['consumed_at' => now()]);
    }

    /** Numbers listed in setting otp.test_phones (testing before WhatsApp is connected). */
    public function isTestPhone(string $phone): bool
    {
        foreach ($this->settings->list('otp.test_phones') as $entry) {
            if (\App\Support\Phone::normalize((string) $entry) === $phone) {
                return true;
            }
        }

        return false;
    }
}
