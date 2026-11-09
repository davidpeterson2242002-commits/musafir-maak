<?php

namespace App\Gateways;

use App\Contracts\OtpSender;
use App\Contracts\WhatsAppGateway;

/** Sends the OTP through WhatsApp (spec: no SMS). */
class WhatsAppOtpSender implements OtpSender
{
    public function __construct(private WhatsAppGateway $whatsapp) {}

    public function send(string $phone, string $code, int $ttlMinutes): void
    {
        $this->whatsapp->send(
            $phone,
            'otp_code',
            ['code' => $code, 'minutes' => $ttlMinutes],
            "رمز التحقق في مسافر معاك: $code\nصالح لمدة $ttlMinutes دقائق. لا تشاركه مع أي شخص."
        );
    }
}
