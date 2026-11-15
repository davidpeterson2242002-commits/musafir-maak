<?php

namespace App\Gateways;

use App\Enums\NotificationChannelType;
use App\Mail\NotificationMail;
use App\Models\NotificationLog;
use App\Services\NotificationLogger;
use Illuminate\Support\Facades\Mail;
use Throwable;

/** Sends the login code by email (setting otp.channel = email). The code is never logged. */
class EmailOtpSender
{
    public function __construct(private NotificationLogger $log) {}

    public function send(string $phone, string $email, string $code, int $ttlMinutes): void
    {
        try {
            Mail::to($email)->send(new NotificationMail(
                "رمز الدخول: $code",
                "رمز الدخول إلى مسافر معاك هو: $code\nصالح لمدة $ttlMinutes دقائق. لا تشاركه مع أي شخص، ولا أحد من فريق مسافر معاك سيطلبه منك.\n\nإذا لم تطلب هذا الرمز تجاهل الرسالة.",
            ));
        } catch (Throwable $e) {
            $this->log->record(NotificationChannelType::Email, 'otp_code', NotificationLog::FAILED, phone: $phone, error: $e->getMessage());

            throw new GatewayException($e->getMessage(), previous: $e);
        }

        $this->log->record(NotificationChannelType::Email, 'otp_code', NotificationLog::SENT, phone: $phone);
    }
}
