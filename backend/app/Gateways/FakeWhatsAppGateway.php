<?php

namespace App\Gateways;

use App\Contracts\WhatsAppGateway;
use PHPUnit\Framework\Assert;

/** Test driver: records messages in memory. */
class FakeWhatsAppGateway implements WhatsAppGateway
{
    /** @var array<int, array{phone: string, template: string, params: array, text: string}> */
    public array $sent = [];

    public bool $shouldFail = false;

    public function send(string $phone, string $template, array $params, string $text): void
    {
        if ($this->shouldFail) {
            throw new GatewayException('Fake WhatsApp failure');
        }

        $this->sent[] = compact('phone', 'template', 'params', 'text');
    }

    public function sentTo(string $phone, ?string $template = null): array
    {
        return array_values(array_filter(
            $this->sent,
            fn ($m) => $m['phone'] === $phone && ($template === null || $m['template'] === $template)
        ));
    }

    public function last(): ?array
    {
        return $this->sent === [] ? null : end($this->sent);
    }

    public function assertSentTo(string $phone, ?string $template = null): void
    {
        Assert::assertNotEmpty($this->sentTo($phone, $template), "No WhatsApp message sent to $phone".($template ? " with template $template" : ''));
    }

    public function assertNothingSent(): void
    {
        Assert::assertEmpty($this->sent, 'Unexpected WhatsApp messages were sent.');
    }
}
