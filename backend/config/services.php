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
    | Musafir Maak gateways. Drivers: "log" (local dev, writes to storage/logs/gateways.log),
    | "fake" (tests). Real providers (Meta Cloud API, FCM) are added in M6.
    */
    'whatsapp' => [
        'driver' => env('WHATSAPP_DRIVER', 'log'),
        'log_channel' => 'gateways',
    ],

    'push' => [
        'driver' => env('PUSH_DRIVER', 'log'),
        'log_channel' => 'gateways',
    ],

    'otp' => [
        // Return the OTP in the API response — only ever honoured when APP_ENV=local.
        'expose_in_response' => (bool) env('OTP_EXPOSE_IN_RESPONSE', false),
    ],

];
