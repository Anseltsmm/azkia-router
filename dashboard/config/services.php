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

    // Login Google (Laravel Socialite). Kosong = tombol Google disembunyikan.
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', '/auth/google/callback'),
    ],

    // URL endpoint /health gateway FastAPI — dipakai halaman API Health dashboard.
    'gateway_health_url' => env('GATEWAY_HEALTH_URL', 'http://127.0.0.1:8001/health'),

    // Endpoint ping realtime per model (halaman Status admin) + token opsional.
    'gateway_health_models_url' => env('GATEWAY_HEALTH_MODELS_URL', 'http://127.0.0.1:8001/health/models'),
    'gateway_health_token' => env('GATEWAY_HEALTH_TOKEN', null),

];
