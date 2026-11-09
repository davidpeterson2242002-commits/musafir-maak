<?php

namespace App\Contracts;

interface PushGateway
{
    /**
     * Send a push notification to device tokens.
     *
     * @param  array<int, string>  $tokens
     * @return array<int, string> tokens that are invalid and should be deleted
     */
    public function send(array $tokens, string $title, string $body, array $data = []): array;
}
