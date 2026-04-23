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

    'alice' => [
        'enabled' => (bool) env('ALICE_ENABLED', false),
        'stale_sensor_seconds' => (int) env('ALICE_STALE_SENSOR_SECONDS', 600),
        'client_id' => env('ALICE_CLIENT_ID', ''),
        'client_secret' => env('ALICE_CLIENT_SECRET', ''),
        'oauth_redirect_uri' => env('ALICE_OAUTH_REDIRECT_URI', ''),
        'oauth_authorize_url' => env('ALICE_OAUTH_AUTHORIZE_URL', 'https://oauth.yandex.ru/authorize'),
        'oauth_token_url' => env('ALICE_OAUTH_TOKEN_URL', 'https://oauth.yandex.ru/token'),
        'oauth_userinfo_url' => env('ALICE_OAUTH_USERINFO_URL', 'https://login.yandex.ru/info'),
    ],

];
