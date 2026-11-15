<?php

namespace App\Services;

use App\Contracts\PushGateway;
use App\Gateways\FcmPushGateway;
use App\Models\AppSecret;
use Illuminate\Validation\ValidationException;

/**
 * The Firebase service-account key, set from the admin panel (notification channels page).
 */
class FirebaseSetup
{
    /** @return array{project_id: string, client_email: string}|null */
    public function status(): ?array
    {
        $json = AppSecret::read(AppSecret::FCM_CREDENTIALS);
        $data = $json ? json_decode($json, true) : null;

        return is_array($data)
            ? ['project_id' => (string) ($data['project_id'] ?? ''), 'client_email' => (string) ($data['client_email'] ?? '')]
            : null;
    }

    /** Validates and stores the pasted service-account JSON. */
    public function save(string $json, string $field = 'json'): array
    {
        $data = json_decode(trim($json), true);

        $error = match (true) {
            ! is_array($data) => 'الملف ليس JSON صالحاً. افتح الملف بالمفكرة وانسخ كل محتواه.',
            ($data['type'] ?? null) !== 'service_account' => 'هذا ليس مفتاح حساب خدمة (service account). أنشئه من Firebase → إعدادات المشروع → حسابات الخدمة.',
            blank($data['project_id'] ?? null), blank($data['client_email'] ?? null) => 'المفتاح ناقص (project_id أو client_email).',
            ! str_contains((string) ($data['private_key'] ?? ''), 'PRIVATE KEY') => 'المفتاح ناقص (private_key).',
            openssl_pkey_get_private((string) $data['private_key']) === false => 'المفتاح الخاص تالف — أنشئ مفتاحاً جديداً.',
            default => null,
        };
        if ($error !== null) {
            throw ValidationException::withMessages([$field => $error]);
        }

        AppSecret::put(AppSecret::FCM_CREDENTIALS, json_encode($data, JSON_UNESCAPED_SLASHES));
        app()->forgetInstance(PushGateway::class);
        app(AuditLogger::class)->log('firebase.credentials_set', null, ['project_id' => $data['project_id']]);

        return ['project_id' => $data['project_id'], 'client_email' => $data['client_email']];
    }

    public function remove(): void
    {
        AppSecret::forget(AppSecret::FCM_CREDENTIALS);
        app()->forgetInstance(PushGateway::class);
        app(AuditLogger::class)->log('firebase.credentials_removed');
    }

    public function active(): bool
    {
        return app(PushGateway::class) instanceof FcmPushGateway;
    }
}
