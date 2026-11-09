<?php

namespace App\Gateways;

use App\Contracts\WhatsAppGateway;
use Illuminate\Support\Facades\Log;

/** Local development driver: writes messages to the log instead of sending. */
class LogWhatsAppGateway implements WhatsAppGateway
{
    public function send(string $phone, string $template, array $params, string $text): void
    {
        Log::channel(config('services.whatsapp.log_channel'))->info("[WhatsApp → $phone] ($template) $text", $params);
    }
}
