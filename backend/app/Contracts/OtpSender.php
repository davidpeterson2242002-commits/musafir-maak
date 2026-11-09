<?php

namespace App\Contracts;

interface OtpSender
{
    /** Deliver a one-time code to the user's phone. */
    public function send(string $phone, string $code, int $ttlMinutes): void;
}
