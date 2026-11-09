<?php

namespace App\Contracts;

use App\Gateways\GatewayException;

interface WhatsAppGateway
{
    /**
     * Send a WhatsApp message to an E.164 phone number.
     *
     * $template is the approved template name (business-initiated messages need one);
     * $params are its variables. $text is the full rendered message, used by drivers
     * that can't send templates (log/fake) and as a session message fallback.
     *
     * @throws GatewayException on delivery failure
     */
    public function send(string $phone, string $template, array $params, string $text): void;
}
