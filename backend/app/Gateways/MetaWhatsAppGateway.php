<?php

namespace App\Gateways;

use App\Contracts\WhatsAppGateway;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * WhatsApp Cloud API (Meta). Every message is sent as an approved template, because
 * our messages are business-initiated (see config/whatsapp.php for the template map).
 */
class MetaWhatsAppGateway implements WhatsAppGateway
{
    /** Template variables can't contain line breaks or long runs of spaces, and the body is capped at 1024 chars. */
    private const PARAM_MAX = 600;

    public function __construct(
        private string $token,
        private string $phoneNumberId,
        private string $graphVersion,
        private array $templates,
        private int $timeout = 15,
    ) {
        if ($token === '' || $phoneNumberId === '') {
            throw new GatewayException('WhatsApp is not configured: set WHATSAPP_TOKEN and WHATSAPP_PHONE_NUMBER_ID.');
        }
    }

    public function send(string $phone, string $template, array $params, string $text): void
    {
        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => ltrim($phone, '+'),
            'type' => 'template',
            'template' => $this->template($template, $params, $text),
        ];

        try {
            $response = Http::withToken($this->token)
                ->timeout($this->timeout)
                ->acceptJson()
                ->post("https://graph.facebook.com/{$this->graphVersion}/{$this->phoneNumberId}/messages", $payload);
        } catch (ConnectionException $e) {
            throw new GatewayException('WhatsApp connection failed: '.$e->getMessage(), previous: $e);
        }

        if ($response->failed()) {
            $error = $response->json('error') ?? [];

            throw new GatewayException(sprintf(
                'WhatsApp error %s (HTTP %d): %s',
                $error['code'] ?? '?', $response->status(),
                $error['error_data']['details'] ?? $error['message'] ?? mb_substr($response->body(), 0, 200),
            ));
        }
    }

    private function template(string $key, array $params, string $text): array
    {
        $definition = $this->templates['templates'][$key] ?? $this->templates['default'];
        $available = ['text' => $text] + $params + ['title' => '', 'body' => $text];

        $values = array_map(
            fn (string $name) => ['type' => 'text', 'text' => $this->clean((string) ($available[$name] ?? ''))],
            $definition['params'] ?? [],
        );

        $components = $values === [] ? [] : [['type' => 'body', 'parameters' => $values]];

        // Authentication templates carry the code a second time, for the "copy code" button.
        if (($definition['type'] ?? 'text') === 'authentication' && $values !== []) {
            $components[] = ['type' => 'button', 'sub_type' => 'url', 'index' => '0', 'parameters' => [$values[0]]];
        }

        return [
            'name' => $definition['name'],
            'language' => ['code' => $definition['language'] ?? $this->templates['language'] ?? 'ar'],
            'components' => $components,
        ];
    }

    private function clean(string $value): string
    {
        $value = preg_replace('/\s*[\r\n\t]+\s*/u', ' — ', trim($value));
        $value = preg_replace('/ {4,}/u', '   ', $value);
        $value = mb_strimwidth($value, 0, self::PARAM_MAX, '…');

        return $value === '' ? '-' : $value; // an empty variable is rejected by the API
    }
}
