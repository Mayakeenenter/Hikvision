<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */
    'hikvision_sync' => [
    'url' => env('HIKVISION_SYNC_URL'),
    'api_key' => env('HIKVISION_SYNC_API_KEY'),
],
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
    'hikvision' => [
        'ip'       => env('HIKVISION_DEVICE_IP'),
        'username' => env('HIKVISION_USERNAME'),
        'password' => env('HIKVISION_PASSWORD'),
    ],

    'bitrix24' => [
        'webhook_url' => env('BITRIX24_WEBHOOK_URL'),
    ],
    'employees_sync' => [
    'url' => env('EMPLOYEES_SYNC_URL'),
    'api_key' => env('EMPLOYEES_SYNC_API_KEY'),
],
];
