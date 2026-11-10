<?php

namespace Tests\Feature;

use App\Contracts\PushGateway;
use App\Contracts\WhatsAppGateway;
use App\Gateways\FcmPushGateway;
use App\Gateways\GatewayException;
use App\Gateways\MetaWhatsAppGateway;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** The real providers, against faked HTTP (no network, no credentials). */
class GatewaysTest extends TestCase
{
    private function meta(): MetaWhatsAppGateway
    {
        return new MetaWhatsAppGateway('test-token', '1234567890', 'v23.0', config('whatsapp'));
    }

    private function fcm(): FcmPushGateway
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $pem);

        return new FcmPushGateway([
            'project_id' => 'musafir-test', 'client_email' => 'svc@musafir-test.iam.gserviceaccount.com', 'private_key' => $pem,
        ]);
    }

    // ---------------- WhatsApp Cloud API ----------------

    public function test_whatsapp_sends_the_default_template_with_title_and_body(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.1']]])]);

        $this->meta()->send('+249912345678', 'booking_accepted', ['title' => 'تم قبول حجزك', 'body' => "السائق: أحمد\nاللوحة: خ 1234"], 'ignored');

        Http::assertSent(function (Request $request) {
            $template = $request['template'];

            return $request->url() === 'https://graph.facebook.com/v23.0/1234567890/messages'
                && $request->hasHeader('Authorization', 'Bearer test-token')
                && $request['messaging_product'] === 'whatsapp'
                && $request['to'] === '249912345678'
                && $request['type'] === 'template'
                && $template['name'] === 'musafir_notice'
                && $template['language'] === ['code' => 'ar']
                && $template['components'] === [['type' => 'body', 'parameters' => [
                    ['type' => 'text', 'text' => 'تم قبول حجزك'],
                    ['type' => 'text', 'text' => 'السائق: أحمد — اللوحة: خ 1234'], // no line breaks allowed in variables
                ]]];
        });
    }

    public function test_whatsapp_otp_uses_the_authentication_template_with_copy_code_button(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.2']]])]);

        $this->meta()->send('+249912345678', 'otp_code', ['code' => '482913', 'minutes' => 10], 'text');

        Http::assertSent(fn (Request $request) => $request['template']['name'] === 'musafir_otp'
            && $request['template']['components'] === [
                ['type' => 'body', 'parameters' => [['type' => 'text', 'text' => '482913']]],
                ['type' => 'button', 'sub_type' => 'url', 'index' => '0', 'parameters' => [['type' => 'text', 'text' => '482913']]],
            ]);
    }

    public function test_whatsapp_parcel_code_template_and_long_values_are_trimmed(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.3']]])]);

        $this->meta()->send('+249912345678', 'parcel_delivery_code', [
            'sender' => 'منى', 'details' => str_repeat('تفاصيل ', 300), 'code' => '1234', 'parcel_id' => 7,
        ], 'text');

        Http::assertSent(function (Request $request) {
            $values = array_column($request['template']['components'][0]['parameters'], 'text');

            return $request['template']['name'] === 'musafir_parcel_code'
                && $values[0] === 'منى' && $values[2] === '1234'
                && mb_strlen($values[1]) <= 600;
        });
    }

    public function test_whatsapp_api_error_becomes_a_gateway_exception_with_the_reason(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['error' => [
            'message' => '(#132001) Template name does not exist in the translation', 'code' => 132001,
            'error_data' => ['details' => 'template name (musafir_notice) does not exist in ar'],
        ]], 404)]);

        try {
            $this->meta()->send('+249912345678', 'booking_accepted', ['title' => 'a', 'body' => 'b'], 'a b');
            $this->fail('Expected a GatewayException');
        } catch (GatewayException $e) {
            $this->assertStringContainsString('132001', $e->getMessage());
            $this->assertStringContainsString('does not exist in ar', $e->getMessage());
        }
    }

    public function test_whatsapp_meta_driver_requires_credentials(): void
    {
        config(['services.whatsapp.driver' => 'meta', 'services.whatsapp.token' => null]);
        $this->app->forgetInstance(WhatsAppGateway::class);

        $this->expectException(GatewayException::class);
        app(WhatsAppGateway::class);
    }

    public function test_whatsapp_meta_driver_resolves_from_config(): void
    {
        config(['services.whatsapp.driver' => 'meta', 'services.whatsapp.token' => 't', 'services.whatsapp.phone_number_id' => '1']);
        $this->app->forgetInstance(WhatsAppGateway::class);

        $this->assertInstanceOf(MetaWhatsAppGateway::class, app(WhatsAppGateway::class));
    }

    // ---------------- Firebase Cloud Messaging ----------------

    public function test_fcm_authenticates_once_and_sends_to_every_token(): void
    {
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'ya29.test', 'expires_in' => 3599]),
            'fcm.googleapis.com/*' => Http::response(['name' => 'projects/musafir-test/messages/1']),
        ]);
        $fcm = $this->fcm();

        $invalid = $fcm->send(['tok-a', 'tok-b'], 'تم قبول حجزك', 'تفاصيل', ['event' => 'booking_accepted', 'booking_id' => 12, 'flag' => true, 'none' => null]);
        $fcm->send(['tok-a'], 'ثانية', 'نص');

        $this->assertSame([], $invalid);
        Http::assertSentCount(4); // 1 auth (token cached) + 3 messages

        Http::assertSent(function (Request $request) {
            if (! str_contains($request->url(), 'oauth2.googleapis.com')) {
                return false;
            }
            [$header, $claims] = array_map(fn ($part) => json_decode(base64_decode(strtr($part, '-_', '+/')), true), array_slice(explode('.', $request['assertion']), 0, 2));

            return $request['grant_type'] === 'urn:ietf:params:oauth:grant-type:jwt-bearer'
                && $header['alg'] === 'RS256'
                && $claims['iss'] === 'svc@musafir-test.iam.gserviceaccount.com'
                && $claims['scope'] === 'https://www.googleapis.com/auth/firebase.messaging';
        });

        Http::assertSent(fn (Request $request) => $request->url() === 'https://fcm.googleapis.com/v1/projects/musafir-test/messages:send'
            && $request->hasHeader('Authorization', 'Bearer ya29.test')
            && $request['message']['token'] === 'tok-b'
            && $request['message']['notification'] === ['title' => 'تم قبول حجزك', 'body' => 'تفاصيل']
            && $request['message']['data'] === ['event' => 'booking_accepted', 'booking_id' => '12', 'flag' => '1'] // strings only
            && $request['message']['android']['priority'] === 'HIGH');
    }

    public function test_fcm_reports_unregistered_tokens_for_deletion(): void
    {
        Http::fake(function (Request $request) {
            if (str_contains($request->url(), 'oauth2')) {
                return Http::response(['access_token' => 'ya29.test', 'expires_in' => 3599]);
            }

            return match ($request['message']['token']) {
                'gone' => Http::response(['error' => ['code' => 404, 'status' => 'NOT_FOUND', 'message' => 'Requested entity was not found.',
                    'details' => [['@type' => 'type.googleapis.com/google.firebase.fcm.v1.FcmError', 'errorCode' => 'UNREGISTERED']]]], 404),
                'garbage' => Http::response(['error' => ['code' => 400, 'status' => 'INVALID_ARGUMENT',
                    'message' => 'The registration token is not a valid FCM registration token']], 400),
                default => Http::response(['name' => 'projects/musafir-test/messages/1']),
            };
        });

        $this->assertSame(['gone', 'garbage'], $this->fcm()->send(['good', 'gone', 'garbage'], 'عنوان', 'نص'));
    }

    public function test_fcm_throws_when_nothing_could_be_delivered_so_the_job_retries(): void
    {
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'ya29.test', 'expires_in' => 3599]),
            'fcm.googleapis.com/*' => Http::response(['error' => ['code' => 503, 'status' => 'UNAVAILABLE', 'message' => 'The service is currently unavailable.']], 503),
        ]);

        $this->expectException(GatewayException::class);
        $this->expectExceptionMessage('unavailable');
        $this->fcm()->send(['tok-a'], 'عنوان', 'نص');
    }

    public function test_fcm_auth_failure_is_a_gateway_exception(): void
    {
        Cache::flush();
        Http::fake(['oauth2.googleapis.com/token' => Http::response(['error' => 'invalid_grant', 'error_description' => 'Invalid JWT Signature.'], 400)]);

        $this->expectException(GatewayException::class);
        $this->expectExceptionMessage('Invalid JWT Signature');
        $this->fcm()->send(['tok-a'], 'عنوان', 'نص');
    }

    public function test_fcm_driver_reads_base64_credentials_and_rejects_missing_ones(): void
    {
        $json = json_encode(['project_id' => 'p', 'client_email' => 'e@p.iam.gserviceaccount.com', 'private_key' => 'k']);
        config(['services.push.driver' => 'fcm', 'services.push.fcm_credentials_base64' => base64_encode($json)]);
        $this->app->forgetInstance(PushGateway::class);
        $this->assertInstanceOf(FcmPushGateway::class, app(PushGateway::class));

        config(['services.push.fcm_credentials_base64' => null, 'services.push.fcm_credentials' => null]);
        $this->app->forgetInstance(PushGateway::class);
        $this->expectException(GatewayException::class);
        app(PushGateway::class);
    }
}
