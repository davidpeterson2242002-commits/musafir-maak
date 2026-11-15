<?php

namespace App\Services;

use App\Mail\NotificationMail;
use App\Models\AppSecret;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Outgoing email (login codes, statements) through an SMTP mailbox set in the admin panel,
 * e.g. noreply@statement-ds.com on Hostinger. The password is stored encrypted.
 */
class MailSetup
{
    public const KEY = 'mail_smtp';

    /** @return array{host: string, port: int, encryption: string, username: string, password: string, from_address: string, from_name: string}|null */
    public function current(): ?array
    {
        $json = AppSecret::read(self::KEY);
        $data = $json ? json_decode($json, true) : null;

        return is_array($data) ? $data : null;
    }

    /** Points Laravel's mailer at the saved mailbox. Tests (array mailer) are left alone. */
    public function apply(): void
    {
        if (config('mail.default') === 'array' || ($smtp = $this->current()) === null) {
            return;
        }

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.scheme' => $smtp['encryption'] === 'ssl' ? 'smtps' : 'smtp',
            'mail.mailers.smtp.host' => $smtp['host'],
            'mail.mailers.smtp.port' => (int) $smtp['port'],
            'mail.mailers.smtp.username' => $smtp['username'],
            'mail.mailers.smtp.password' => $smtp['password'],
            'mail.mailers.smtp.timeout' => 20,
            'mail.from.address' => $smtp['from_address'] ?: $smtp['username'],
            'mail.from.name' => $smtp['from_name'] ?: 'مسافر معاك',
        ]);
        app()->forgetInstance('mail.manager');
        app()->forgetInstance('mailer');
        Mail::clearResolvedInstances();
    }

    public function save(array $input): void
    {
        $old = $this->current();
        $password = filled($input['password'] ?? null) ? $input['password'] : ($old['password'] ?? null);
        if (blank($password)) {
            throw ValidationException::withMessages(['mountedActions.0.data.password' => 'اكتب كلمة مرور البريد.']);
        }

        AppSecret::put(self::KEY, json_encode([
            'host' => trim($input['host']),
            'port' => (int) $input['port'],
            'encryption' => $input['encryption'] === 'tls' ? 'tls' : 'ssl',
            'username' => trim($input['username']),
            'password' => $password,
            'from_address' => trim($input['from_address'] ?? '') ?: trim($input['username']),
            'from_name' => trim($input['from_name'] ?? '') ?: 'مسافر معاك',
        ], JSON_UNESCAPED_UNICODE));

        app(AuditLogger::class)->log('mail.smtp_set', null, ['host' => $input['host'], 'username' => $input['username']]);
        $this->apply();
    }

    /** Sends a test email now; returns the error text or null when it was accepted by the server. */
    public function test(string $to): ?string
    {
        try {
            Mail::to($to)->send(new NotificationMail('رسالة تجريبية', 'البريد يعمل في مسافر معاك ✓ — رموز الدخول ستصل للمستخدمين بهذه الطريقة.'));

            return null;
        } catch (Throwable $e) {
            return $e->getMessage();
        }
    }
}
