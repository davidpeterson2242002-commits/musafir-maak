<?php

namespace App\Gateways;

use App\Contracts\PushGateway;
use Illuminate\Support\Facades\Log;

class LogPushGateway implements PushGateway
{
    public function send(array $tokens, string $title, string $body, array $data = []): array
    {
        Log::channel(config('services.push.log_channel'))->info('[Push → '.count($tokens)." device(s)] $title: $body", $data);

        return [];
    }
}
