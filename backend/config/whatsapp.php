<?php

/*
| WhatsApp message templates (WhatsApp Cloud API).
|
| WhatsApp only lets a business start a conversation with a template that Meta approved
| beforehand. Each key below is one of our message types (a NotificationEvent value,
| "otp_code" or "parcel_delivery_code"); the value says which approved template to use:
|
|   name     template name in WhatsApp Manager
|   type     "text" (default) or "authentication" (OTP template with a copy-code button)
|   params   what fills {{1}}, {{2}}… in the template body, in order. Available values:
|            "title", "body" (the notification as shown in the app), "text" (title + body),
|            or any key of the notification's data (e.g. "code").
|
| Anything not listed uses `default`. The suggested Arabic wording for every template is in
| docs/WHATSAPP_TEMPLATES.md — create them with exactly these names, or change the names here.
*/

return [

    'language' => env('WHATSAPP_TEMPLATE_LANGUAGE', 'ar'),

    'default' => [
        'name' => env('WHATSAPP_TEMPLATE_DEFAULT', 'musafir_notice'),
        'params' => ['title', 'body'],
    ],

    'templates' => [
        'otp_code' => [
            'name' => env('WHATSAPP_TEMPLATE_OTP', 'musafir_otp'),
            'type' => 'authentication',
            'params' => ['code'],
        ],
        'parcel_delivery_code' => [
            'name' => env('WHATSAPP_TEMPLATE_PARCEL_CODE', 'musafir_parcel_code'),
            'params' => ['sender', 'details', 'code'],
        ],
        'broadcast' => [
            'name' => env('WHATSAPP_TEMPLATE_BROADCAST', 'musafir_announcement'),
            'params' => ['title', 'body'],
        ],
    ],

];
