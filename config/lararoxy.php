<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Upstream Services
    |--------------------------------------------------------------------------
    */

    'services' => [
        // 'service-name' => [
        //     'base_url'        => env('SERVICE_URL'),
        //     'timeout'         => 30,
        //     'connect_timeout' => 5,
        //     'retry'           => ['times' => 3, 'delay' => 100, 'multiplier' => 2, 'on' => [500, 502, 503, 504]],
        //     'circuit_breaker' => ['enabled' => false, 'threshold' => 5, 'timeout' => 30],
        //     'health_check'    => ['path' => '/health', 'interval' => 30],
        //     'auth'            => ['type' => 'bearer', 'token' => env('SERVICE_TOKEN')],
        // ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Endpoint Groups (Incoming Proxy)
    |--------------------------------------------------------------------------
    */

    'groups' => [
        // 'group-name' => [
        //     'prefix'        => '/api/v1',
        //     'domain'        => null,
        //     'middleware'     => [],
        //     'auth'          => ['driver' => 'sanctum', 'guard' => 'api'],
        //     'token_payload' => null,
        //     'rate_limit'    => null,
        //     'cors'          => null,
        //     'logging'       => ['level' => 'minimal'],
        //     'pipeline'      => [],
        //     'routes'        => [],
        // ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Outgoing Services
    |--------------------------------------------------------------------------
    */

    'outgoing' => [
        // 'outgoing-name' => [
        //     'base_url'  => env('OUTGOING_URL'),
        //     'timeout'   => 30,
        //     'auth'      => ['type' => 'bearer', 'token' => env('OUTGOING_TOKEN')],
        //     'tracking'  => ['enabled' => true, 'store' => 'database', 'ttl' => 86400, 'id_header' => 'X-Tracking-Id'],
        //     'callback'  => ['path' => '/webhooks/{tracking_id}', 'signature_header' => null, 'handler' => null],
        //     'retry'     => ['times' => 3, 'delay' => 1000, 'multiplier' => 2],
        //     'queue'     => ['enabled' => false, 'connection' => null, 'queue' => null],
        //     'logging'   => ['level' => 'standard'],
        // ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Request Logging
    |--------------------------------------------------------------------------
    */

    'logging' => [
        'default_level' => env('LARAROXY_LOG_LEVEL', 'minimal'),
        'driver' => env('LARAROXY_LOG_DRIVER', 'database'),
        'body_size_limit' => 16384,

        'sampling' => [
            'enabled' => env('LARAROXY_LOG_SAMPLING', false),
            'rate' => 0.1,
        ],

        'retention' => [
            'enabled' => true,
            'days' => 30,
            'max_records' => 1_000_000,
            'cleanup_schedule' => 'daily',
        ],

        'redact_headers' => [
            'Authorization',
            'Cookie',
            'X-Api-Key',
            'X-CSRF-Token',
        ],

        'redact_fields' => [
            'password',
            'password_confirmation',
            'credit_card',
            'card_number',
            'cvv',
            'ssn',
            'secret',
            'token',
        ],

        'exclude_paths' => [
            '/health',
            '/ping',
            '/metrics',
        ],

        'conditions' => [
            'log_errors_only' => false,
            'log_slow_requests' => [
                'enabled' => true,
                'threshold' => 2000,
            ],
        ],

        'escalation' => [
            '4xx' => 'standard',
            '5xx' => 'full',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Tracking Store
    |--------------------------------------------------------------------------
    */

    'tracking' => [
        'default_store' => env('LARAROXY_TRACKING_STORE', 'database'),
        'id_prefix' => 'trk_',
        'retention_days' => 90,
    ],

    /*
    |--------------------------------------------------------------------------
    | Request ID
    |--------------------------------------------------------------------------
    */

    'request_id' => [
        'enabled' => true,
        'header' => 'X-Request-Id',
        'trust_incoming' => false,
        'forward' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP Client Defaults
    |--------------------------------------------------------------------------
    */

    'http' => [
        'default_timeout' => 30,
        'default_connect_timeout' => 5,
        'verify_ssl' => env('LARAROXY_VERIFY_SSL', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | PHP Attribute Discovery
    |--------------------------------------------------------------------------
    */

    'attributes' => [
        'enabled' => true,
        'scan_paths' => [],
        'cache' => env('LARAROXY_CACHE_ATTRIBUTES', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Route Files
    |--------------------------------------------------------------------------
    */

    'routes' => [
        'files' => [
            // base_path('routes/proxy.php'),
        ],
    ],
];
