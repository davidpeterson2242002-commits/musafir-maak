<?php

namespace App\Gateways;

use App\Contracts\PushGateway;

class FakePushGateway implements PushGateway
{
    /** @var array<int, array{tokens: array, title: string, body: string, data: array}> */
    public array $sent = [];

    /** @var array<int, string> tokens the fake reports as invalid */
    public array $invalidTokens = [];

    public function send(array $tokens, string $title, string $body, array $data = []): array
    {
        $this->sent[] = compact('tokens', 'title', 'body', 'data');

        return array_values(array_intersect($tokens, $this->invalidTokens));
    }
}
