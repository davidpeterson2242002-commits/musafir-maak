<?php

namespace App\Gateways;

use App\Contracts\OtpSender;
use App\Contracts\WhatsAppGateway;
use App\Enums\NotificationChannelType;
use App\Models\NotificationLog;
use App\Services\NotificationLogger;
use Throwable;

/** Sends the OTP through WhatsApp (spec: no SMS). The code itself is never written to the notification log. */
class WhatsAppOtpSender implements OtpSender
{
    public function __construct(private WhatsAppGateway $whatsapp, private NotificationLogger $log) {}

    public function send(string $phone, string $code, int $ttlMinutes): void
    {
        try {
            $this->whatsapp->send(
                $phone,
                'otp_code',
                ['code' => $code, 'minutes' => $ttlMinutes],
                "رمز التحقق في مسافر معاك: $code\nصالح لمدة $ttlMinutes دقائق. لا تشاركه مع أي شخص."
            );
        } catch (Throwable $e) {
            $this->log->record(NotificationChannelType::WhatsApp, 'otp_code', NotificationLog::FAILED, phone: $phone, error: $e->getMessage());

            throw $e instanceof GatewayException ? $e : new GatewayException($e->getMessage(), previous: $e);
        }

        $this->log->record(NotificationChannelType::WhatsApp, 'otp_code', NotificationLog::SENT, phone: $phone);
    }
}
