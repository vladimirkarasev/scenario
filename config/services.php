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

    // Внешний API, куда ActionObserver шлёт обновлённые данные экшена при сохранении.
    // Пусто → отправка отключена.
    'action_sync' => [
        'url' => env('ACTION_SYNC_WEBHOOK_URL'),
    ],

    'centrifugo' => [
        'ws_url' => env('CENTRIFUGO_WS_URL', 'ws://localhost:8002/connection/websocket'),
        'token_hmac_secret_key' => env('CENTRIFUGO_TOKEN_HMAC_SECRET_KEY'),
    ],

];
