<?php

declare(strict_types=1);

return [
    'handler_namespace' => 'Module\\Proxy\\Proxies\\',

    'allowed_handlers' => [],

    'max_payload_bytes' => 1024 * 1024,

    'masked_headers' => [
        'authorization',
        'cookie',
        'x-api-key',
        'x-signature',
    ],

    /*
    |--------------------------------------------------------------------------
    | Outbound API Gateways
    |--------------------------------------------------------------------------
    |
    | Handlers can use these named gateway configs when they need to call an
    | external service. Every service can choose its own auth scheme.
    |
    | Supported auth types: none, basic, bearer, headers.
    */
    'gateways' => [
        'default' => [
            'base_uri' => env('PROXY_GATEWAY_DEFAULT_BASE_URI', ''),
            'timeout' => (float)env('PROXY_GATEWAY_DEFAULT_TIMEOUT', 10),
            'connect_timeout' => (float)env('PROXY_GATEWAY_DEFAULT_CONNECT_TIMEOUT', 5),
            'mock' => (bool)env('PROXY_GATEWAY_DEFAULT_MOCK', false),
            'auth' => [
                'type' => env('PROXY_GATEWAY_DEFAULT_AUTH_TYPE', 'none'),
                'username' => env('PROXY_GATEWAY_DEFAULT_USERNAME'),
                'password' => env('PROXY_GATEWAY_DEFAULT_PASSWORD'),
                'token' => env('PROXY_GATEWAY_DEFAULT_TOKEN'),
                'headers' => [],
            ],
            'headers' => [
                'Accept' => 'application/json',
            ],
        ],

        'basic_service' => [
            'base_uri' => env('PROXY_GATEWAY_BASIC_BASE_URI', ''),
            'timeout' => (float)env('PROXY_GATEWAY_BASIC_TIMEOUT', 10),
            'connect_timeout' => (float)env('PROXY_GATEWAY_BASIC_CONNECT_TIMEOUT', 5),
            'mock' => (bool)env('PROXY_GATEWAY_BASIC_MOCK', false),
            'auth' => [
                'type' => 'basic',
                'username' => env('PROXY_GATEWAY_BASIC_USERNAME'),
                'password' => env('PROXY_GATEWAY_BASIC_PASSWORD'),
            ],
            'headers' => [
                'Accept' => 'application/json',
            ],
        ],

        'bearer_service' => [
            'base_uri' => env('PROXY_GATEWAY_BEARER_BASE_URI', ''),
            'timeout' => (float)env('PROXY_GATEWAY_BEARER_TIMEOUT', 10),
            'connect_timeout' => (float)env('PROXY_GATEWAY_BEARER_CONNECT_TIMEOUT', 5),
            'mock' => (bool)env('PROXY_GATEWAY_BEARER_MOCK', false),
            'auth' => [
                'type' => 'bearer',
                'token' => env('PROXY_GATEWAY_BEARER_TOKEN'),
            ],
            'headers' => [
                'Accept' => 'application/json',
            ],
        ],

        'autocrm' => [
            'base_uri' => env('PROXY_GATEWAY_AUTOCRM_BASE_URI', ''),
            'timeout' => (float)env('PROXY_GATEWAY_AUTOCRM_TIMEOUT', 10),
            'connect_timeout' => (float)env('PROXY_GATEWAY_AUTOCRM_CONNECT_TIMEOUT', 5),
            'mock' => (bool)env('PROXY_GATEWAY_AUTOCRM_MOCK', false),
            'auth' => [
                'type' => env('PROXY_GATEWAY_AUTOCRM_AUTH_TYPE', 'bearer'),
                'username' => env('PROXY_GATEWAY_AUTOCRM_USERNAME'),
                'password' => env('PROXY_GATEWAY_AUTOCRM_PASSWORD'),
                'token' => env('PROXY_GATEWAY_AUTOCRM_TOKEN'),
            ],
            'headers' => [
                'Accept' => 'application/json',
            ],
        ],

        'motorinvest_autocrm' => [
            'base_uri' => env('PROXY_GATEWAY_MOTORINVEST_AUTOCRM_BASE_URI', ''),
            'timeout' => (float)env('PROXY_GATEWAY_MOTORINVEST_AUTOCRM_TIMEOUT', 10),
            'connect_timeout' => (float)env('PROXY_GATEWAY_MOTORINVEST_AUTOCRM_CONNECT_TIMEOUT', 5),
            'mock' => (bool)env('PROXY_GATEWAY_MOTORINVEST_AUTOCRM_MOCK', false),
            'auth' => [
                'type' => env('PROXY_GATEWAY_MOTORINVEST_AUTOCRM_AUTH_TYPE', 'bearer'),
                'username' => env('PROXY_GATEWAY_MOTORINVEST_AUTOCRM_USERNAME'),
                'password' => env('PROXY_GATEWAY_MOTORINVEST_AUTOCRM_PASSWORD'),
                'token' => env('PROXY_GATEWAY_MOTORINVEST_AUTOCRM_TOKEN'),
            ],
            'headers' => [
                'Accept' => 'application/json',
            ],
        ],

        'belgee_autocrm' => [
            'base_uri' => env('PROXY_GATEWAY_BELGEE_AUTOCRM_BASE_URI', ''),
            'timeout' => (float)env('PROXY_GATEWAY_BELGEE_AUTOCRM_TIMEOUT', 10),
            'connect_timeout' => (float)env('PROXY_GATEWAY_BELGEE_AUTOCRM_CONNECT_TIMEOUT', 5),
            'mock' => (bool)env('PROXY_GATEWAY_BELGEE_AUTOCRM_MOCK', false),
            'auth' => [
                'type' => env('PROXY_GATEWAY_BELGEE_AUTOCRM_AUTH_TYPE', 'bearer'),
                'username' => env('PROXY_GATEWAY_BELGEE_AUTOCRM_USERNAME'),
                'password' => env('PROXY_GATEWAY_BELGEE_AUTOCRM_PASSWORD'),
                'token' => env('PROXY_GATEWAY_BELGEE_AUTOCRM_TOKEN'),
            ],
            'headers' => [
                'Accept' => 'application/json',
            ],
        ],
    ],
];
