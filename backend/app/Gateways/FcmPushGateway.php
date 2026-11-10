<?php

namespace App\Gateways;

use App\Contracts\PushGateway;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Firebase Cloud Messaging, HTTP v1 API. Authenticates with the project's service-account
 * key (a signed JWT exchanged for a short-lived access token, cached until it expires).
 */
class FcmPushGateway implements PushGateway
{
    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    private const CONCURRENCY = 20;

    /** @param array{project_id: string, client_email: string, private_key: string, token_uri?: string} $credentials */
    public function __construct(private array $credentials, private int $timeout = 15)
    {
        foreach (['project_id', 'client_email', 'private_key'] as $key) {
            if (blank($credentials[$key] ?? null)) {
                throw new GatewayException("FCM is not configured: the service-account key has no $key.");
            }
        }
    }

    public static function fromConfig(array $config): self
    {
        $json = match (true) {
            filled($config['fcm_credentials_base64'] ?? null) => base64_decode($config['fcm_credentials_base64'], true),
            filled($config['fcm_credentials'] ?? null) && is_file($config['fcm_credentials']) => file_get_contents($config['fcm_credentials']),
            default => throw new GatewayException('FCM is not configured: set FCM_CREDENTIALS (path to the service-account JSON) or FCM_CREDENTIALS_BASE64.'),
        };

        $credentials = json_decode((string) $json, true);
        if (! is_array($credentials)) {
            throw new GatewayException('FCM credentials are not valid JSON.');
        }

        return new self($credentials, (int) ($config['timeout'] ?? 15));
    }

    public function send(array $tokens, string $title, string $body, array $data = []): array
    {
        $tokens = array_values(array_unique($tokens));
        if ($tokens === []) {
            return [];
        }

        $accessToken = $this->accessToken();
        $url = "https://fcm.googleapis.com/v1/projects/{$this->credentials['project_id']}/messages:send";
        $base = [
            'notification' => ['title' => $title, 'body' => $body],
            'data' => $this->stringify($data),
            'android' => ['priority' => 'HIGH', 'notification' => ['channel_id' => 'default', 'sound' => 'default']],
            'apns' => ['payload' => ['aps' => ['sound' => 'default']]],
        ];
        if ($base['data'] === []) {
            unset($base['data']);
        }

        $invalid = [];
        $errors = [];

        foreach (array_chunk($tokens, self::CONCURRENCY) as $chunk) {
            $responses = Http::pool(fn (Pool $pool) => array_map(
                fn (string $token) => $pool->as($token)->withToken($accessToken)->timeout($this->timeout)->acceptJson()
                    ->post($url, ['message' => ['token' => $token] + $base]),
                $chunk,
            ));

            foreach ($chunk as $token) {
                $response = $responses[$token] ?? null;

                if (! $response instanceof Response) {
                    $errors[] = $response instanceof Throwable ? $response->getMessage() : 'no response';
                } elseif ($this->tokenIsDead($response)) {
                    $invalid[] = $token;
                } elseif ($response->failed()) {
                    $errors[] = 'HTTP '.$response->status().': '.($response->json('error.message') ?? mb_substr($response->body(), 0, 200));
                }
            }
        }

        // Nothing got through and it wasn't just dead tokens: let the job retry.
        if ($errors !== [] && count($errors) + count($invalid) === count($tokens)) {
            throw new GatewayException('FCM send failed: '.$errors[0]);
        }

        return $invalid;
    }

    /** The app was uninstalled or the token expired — the token should be deleted. */
    private function tokenIsDead(Response $response): bool
    {
        if ($response->successful()) {
            return false;
        }

        $codes = collect($response->json('error.details') ?? [])->pluck('errorCode')->filter()->all();

        if ($response->status() === 404 || in_array('UNREGISTERED', $codes, true)) {
            return true;
        }

        // A malformed token is reported as INVALID_ARGUMENT naming the token field.
        return $response->status() === 400
            && str_contains(strtolower((string) $response->json('error.message')), 'registration token');
    }

    /** FCM data values must all be strings. */
    private function stringify(array $data): array
    {
        $out = [];
        foreach ($data as $key => $value) {
            if ($value === null) {
                continue;
            }
            $out[(string) $key] = is_scalar($value) ? (string) (is_bool($value) ? (int) $value : $value) : json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        return $out;
    }

    private function accessToken(): string
    {
        $cacheKey = 'fcm.access_token.'.md5($this->credentials['client_email']);

        if ($cached = Cache::get($cacheKey)) {
            return $cached;
        }

        $tokenUri = $this->credentials['token_uri'] ?? 'https://oauth2.googleapis.com/token';
        $now = time();
        $jwt = $this->jwt([
            'iss' => $this->credentials['client_email'],
            'scope' => self::SCOPE,
            'aud' => $tokenUri,
            'iat' => $now,
            'exp' => $now + 3600,
        ]);

        try {
            $response = Http::asForm()->timeout($this->timeout)->post($tokenUri, [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);
        } catch (ConnectionException $e) {
            throw new GatewayException('FCM auth connection failed: '.$e->getMessage(), previous: $e);
        }

        if ($response->failed() || blank($response->json('access_token'))) {
            throw new GatewayException('FCM auth failed (HTTP '.$response->status().'): '
                .($response->json('error_description') ?? mb_substr($response->body(), 0, 200)));
        }

        Cache::put($cacheKey, $response->json('access_token'), max(60, (int) $response->json('expires_in', 3600) - 120));

        return $response->json('access_token');
    }

    private function jwt(array $claims): string
    {
        $encode = fn (string $raw) => rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
        $unsigned = $encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])).'.'.$encode(json_encode($claims));

        $key = openssl_pkey_get_private($this->credentials['private_key']);
        if ($key === false || ! openssl_sign($unsigned, $signature, $key, OPENSSL_ALGO_SHA256)) {
            throw new GatewayException('FCM credentials: the private key could not be used to sign.');
        }

        return $unsigned.'.'.$encode($signature);
    }
}
