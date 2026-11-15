<?php

namespace Tests\Feature\Admin;

use App\Contracts\PushGateway;
use App\Enums\AdminRole;
use App\Filament\Resources\NotificationChannels\Pages\ManageNotificationChannels;
use App\Gateways\FakePushGateway;
use App\Gateways\FcmPushGateway;
use App\Gateways\LogPushGateway;
use App\Models\Admin;
use App\Models\AppSecret;
use App\Services\FirebaseSetup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class FirebaseSetupTest extends TestCase
{
    use RefreshDatabase;

    private function serviceAccountJson(): string
    {
        openssl_pkey_export(openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]), $pem);

        return json_encode([
            'type' => 'service_account',
            'project_id' => 'musafir-maak',
            'private_key_id' => 'abc',
            'private_key' => $pem,
            'client_email' => 'firebase-adminsdk@musafir-maak.iam.gserviceaccount.com',
            'token_uri' => 'https://oauth2.googleapis.com/token',
        ]);
    }

    public function test_saved_key_is_encrypted_and_switches_push_to_fcm(): void
    {
        config(['services.push.driver' => 'log']);
        app()->forgetInstance(PushGateway::class);
        $this->assertInstanceOf(LogPushGateway::class, app(PushGateway::class));

        $saved = app(FirebaseSetup::class)->save($this->serviceAccountJson());

        $this->assertSame('musafir-maak', $saved['project_id']);
        $raw = DB::table('app_secrets')->where('key', AppSecret::FCM_CREDENTIALS)->value('value');
        $this->assertStringNotContainsString('PRIVATE KEY', $raw);
        $this->assertInstanceOf(FcmPushGateway::class, app(PushGateway::class));
        $this->assertSame('musafir-maak', app(PushGateway::class)->projectId());
        $this->assertDatabaseHas('audit_logs', ['action' => 'firebase.credentials_set']);
        $this->assertStringNotContainsString('PRIVATE', (string) DB::table('audit_logs')->where('action', 'firebase.credentials_set')->value('changes'));

        app(FirebaseSetup::class)->remove();
        $this->assertInstanceOf(LogPushGateway::class, app(PushGateway::class));
        $this->assertNull(app(FirebaseSetup::class)->status());
    }

    public function test_tests_keep_the_fake_gateway_even_with_a_key(): void
    {
        AppSecret::put(AppSecret::FCM_CREDENTIALS, $this->serviceAccountJson());
        app()->forgetInstance(PushGateway::class);

        $this->assertInstanceOf(FakePushGateway::class, app(PushGateway::class));
    }

    public function test_invalid_keys_are_rejected(): void
    {
        foreach (['not json', json_encode(['type' => 'authorized_user']), json_encode(['type' => 'service_account', 'project_id' => 'x', 'client_email' => 'y', 'private_key' => 'nope'])] as $bad) {
            try {
                app(FirebaseSetup::class)->save($bad);
                $this->fail('accepted an invalid key');
            } catch (ValidationException) {
                $this->assertSame(0, AppSecret::count());
            }
        }
    }

    public function test_super_admin_saves_the_key_from_the_channels_page(): void
    {
        $this->actingAs(Admin::factory()->create(['role' => AdminRole::SuperAdmin]), 'admin');

        Livewire::test(ManageNotificationChannels::class)
            ->callAction('firebase', ['json' => 'garbage'])
            ->assertHasActionErrors(['json']);
        $this->assertNull(app(FirebaseSetup::class)->status());

        Livewire::test(ManageNotificationChannels::class)
            ->callAction('firebase', ['json' => $this->serviceAccountJson()])
            ->assertHasNoActionErrors();
        $this->assertSame('musafir-maak', app(FirebaseSetup::class)->status()['project_id']);
    }
}
