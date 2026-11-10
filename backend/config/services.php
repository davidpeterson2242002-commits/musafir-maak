<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    | Musafir Maak gateways.
    | Drivers: "log" (local dev, writes to storage/logs/gateways.log), "fake" (tests),
    | and the real providers: WhatsApp "meta" (WhatsApp Cloud API), push "fcm" (Firebase HTTP v1).
    | WhatsApp template names live in config/whatsapp.php.
    */
    'whatsapp' => [
        'driver' => env('WHATSAPP_DRIVER', 'log'),
        'log_channel' => 'gateways',
        'token' => env('WHATSAPP_TOKEN'),                     // permanent system-user access token
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'), // the sender number's id (not the number itself)
        'graph_version' => env('WHATSAPP_GRAPH_VERSION', 'v23.0'),
        'timeout' => (int) env('WHATSAPP_TIMEOUT', 15),
    ],

    'push' => [
        'driver' => env('PUSH_DRIVER', 'log'),
        'log_channel' => 'gateways',
        // Firebase service-account key: a file path, or the JSON itself base64-encoded (handy on hosts without file access).
        'fcm_credentials' => env('FCM_CREDENTIALS'),
        'fcm_credentials_base64' => env('FCM_CREDENTIALS_BASE64'),
        'timeout' => (int) env('FCM_TIMEOUT', 15),
    ],

    'otp' => [
        // Return the OTP in the API response — only ever honoured when APP_ENV=local.
        'expose_in_response' => (bool) env('OTP_EXPOSE_IN_RESPONSE', false),
    ],

];
