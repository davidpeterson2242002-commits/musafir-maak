<?php

namespace App\Services;

use App\Contracts\OtpSender;
use App\Exceptions\BusinessRuleException;
use App\Gateways\EmailOtpSender;
use App\Models\User;
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
        private EmailOtpSender $emailSender,
        private Settings $settings,
    ) {}

    /**
     * Issue a new code for $phone (already normalized) and send it.
     *
     * @return array{code: OtpCode, plain: string, resend_after: int}
     */
    public function issue(string $phone, ?string $ip = null, ?string $email = null): array
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

        // Where the code goes, decided before anything is stored.
        $sendTo = $this->emailDestination($phone, $email, $isTest);

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
            'email' => $sendTo,
        ]);

        try {
            if ($this->channel() === 'whatsapp') {
                $this->sender->send($phone, $plain, $ttl);
            } elseif ($sendTo !== null && ! $isTest) {
                $this->emailSender->send($phone, $sendTo, $plain, $ttl);
            }
        } catch (GatewayException $e) {
            Log::warning('OTP delivery failed', ['phone' => $phone, 'error' => $e->getMessage()]);
            $code->update(['consumed_at' => now()]);
            throw new HttpException(503, $this->channel() === 'whatsapp'
                ? 'تعذّر إرسال الرمز عبر واتساب. تأكد أن الرقم عليه واتساب وحاول مرة أخرى.'
                : 'تعذّر إرسال الرمز إلى بريدك. تأكد من البريد وحاول مرة أخرى بعد قليل.');
        }

        return ['code' => $code, 'plain' => $plain, 'resend_after' => $resendSeconds, 'channel' => $this->channel(), 'email' => $sendTo];
    }

    /**
     * Verify a code. Consumes it on success.
     *
     * @throws ValidationException with an Arabic message on failure
     */
    public function verify(string $phone, string $plain): OtpCode
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

        return $code;
    }

    /** `email` or `whatsapp` (admin setting otp.channel). */
    public function channel(): string
    {
        return $this->settings->string('otp.channel') === 'whatsapp' ? 'whatsapp' : 'email';
    }

    /**
     * Email channel: an existing account always gets the code at its own address (so nobody
     * can take it over by typing another email); a new account (or one without an email)
     * must give one. Test numbers need none.
     */
    private function emailDestination(string $phone, ?string $email, bool $isTest): ?string
    {
        if ($this->channel() !== 'email') {
            return null;
        }

        $user = User::where('phone', $phone)->first();
        if (filled($user?->email) && ! $user->trashed()) {
            return $user->email;
        }

        $email = $email !== null ? mb_strtolower(trim($email)) : null;
        if (blank($email)) {
            if ($isTest) {
                return null;
            }
            throw new BusinessRuleException('اكتب بريدك الإلكتروني، نرسل لك عليه رمز الدخول.', 'email_required');
        }

        $taken = User::where('email', $email)->where('phone', '!=', $phone)->exists();
        if ($taken) {
            throw new BusinessRuleException('البريد ده مربوط بحساب تاني. اكتب بريد مختلف أو تواصل مع الدعم.', 'email_taken');
        }

        return $email;
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
