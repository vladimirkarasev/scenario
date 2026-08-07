<?php

declare(strict_types=1);

return [
    'handler_namespace' => 'Module\\Proxy\\Proxies\\',

    // Тестовый fixture-handler (tests/Stubs/Proxy) — допущен только через PROXY_ALLOWED_TEST_HANDLERS (phpunit.xml).
    'allowed_handlers' => array_filter(explode(',', (string) env('PROXY_ALLOWED_TEST_HANDLERS', ''))),

    'max_payload_bytes' => 1024 * 1024,

    'masked_headers' => [
        'authorization',
        'cookie',
        'x-api-key',
        'x-signature',
    ],

    /*
    |--------------------------------------------------------------------------
    | Endpoint seeds
    |--------------------------------------------------------------------------
    |
    | Стартовые доступы для код-определяемых интеграций (`proxies:sync`): задаются
    | один раз при создании записи, далее правятся в UI и не перезатираются.
    | Реальные доступы хранятся в БД зашифрованными, здесь — только начальные
    | значения из окружения.
    */
    'endpoint_seeds' => [
        'motorinvest' => [
            'base_uri' => env('PROXY_GATEWAY_MOTORINVEST_AUTOCRM_BASE_URI'),
            'bearer_token' => env('PROXY_GATEWAY_MOTORINVEST_AUTOCRM_TOKEN'),
        ],
    ],
];
