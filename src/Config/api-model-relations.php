<?php

return [
    /*
    |--------------------------------------------------------------------------
    | API Client Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the default settings for API client interactions
    |
    */
    'client' => [
        'base_url' => env('API_MODEL_RELATIONS_BASE_URL', ''),
        'timeout' => 30,
        'connect_timeout' => 10,
        'retry' => [
            'max_attempts' => 3,
            'delay' => 1000, // milliseconds
        ],
        'headers' => [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Strategies
    |--------------------------------------------------------------------------
    |
    | Configure different authentication strategies for API endpoints
    |
    */
    'auth' => [
        'strategy' => env('API_MODEL_RELATIONS_AUTH_STRATEGY', 'bearer'),
        'credentials' => [
            'token' => env('API_MODEL_RELATIONS_AUTH_TOKEN', ''),
            'username' => env('API_MODEL_RELATIONS_AUTH_USERNAME', ''),
            'password' => env('API_MODEL_RELATIONS_AUTH_PASSWORD', ''),
            'api_key' => env('API_MODEL_RELATIONS_AUTH_API_KEY', ''),
            'header_name' => env('API_MODEL_RELATIONS_AUTH_HEADER_NAME', 'X-API-KEY'),
            'use_query_param' => env('API_MODEL_RELATIONS_AUTH_USE_QUERY', false),
            'query_param_name' => env('API_MODEL_RELATIONS_AUTH_QUERY_PARAM', 'api_key'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Caching Configuration
    |--------------------------------------------------------------------------
    |
    | Configure caching for API responses
    |
    */
    'cache' => [
        'enabled' => env('API_MODEL_RELATIONS_CACHE_ENABLED', true),
        'ttl' => env('API_MODEL_RELATIONS_CACHE_TTL', 3600), // 1 hour
        'store' => env('API_MODEL_RELATIONS_CACHE_STORE', null), // null = default cache store
        'prefix' => 'api_model_relations_',
    ],

    /*
    |--------------------------------------------------------------------------
    | Error Handling
    |--------------------------------------------------------------------------
    |
    | Configure error handling for API requests
    |
    */
    'error_handling' => [
        'log_requests' => env('API_MODEL_RELATIONS_LOG_REQUESTS', true),
        'log_responses' => env('API_MODEL_RELATIONS_LOG_RESPONSES', false),
        'log_channel' => env('API_MODEL_RELATIONS_LOG_CHANNEL', null), // null = default log channel
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Configure rate limiting for API requests
    |
    */
    'rate_limiting' => [
        'enabled' => env('API_MODEL_RELATIONS_RATE_LIMIT_ENABLED', true),
        'max_attempts' => env('API_MODEL_RELATIONS_RATE_LIMIT_MAX', 60),
        'decay_minutes' => env('API_MODEL_RELATIONS_RATE_LIMIT_DECAY', 1),
        'key_prefix' => 'api_rate_limit',
    ],

    /*
    |--------------------------------------------------------------------------
    | Debugging
    |--------------------------------------------------------------------------
    |
    | Configure debugging options
    |
    */
    'debug' => env('API_MODEL_RELATIONS_DEBUG', false),
    
    /*
    |--------------------------------------------------------------------------
    | Events
    |--------------------------------------------------------------------------
    |
    | Configure event handling for API requests
    |
    */
    'events' => [
        'enabled' => env('API_MODEL_RELATIONS_EVENTS_ENABLED', true),
    ],
    
    /*
    |--------------------------------------------------------------------------
    | API Model Defaults
    |--------------------------------------------------------------------------
    |
    | Default settings for API models
    |
    */
    'model_defaults' => [
        'merge_with_local_data' => env('API_MODEL_RELATIONS_MERGE_LOCAL', true),
        'cache_ttl' => env('API_MODEL_RELATIONS_MODEL_CACHE_TTL', 3600),
    ],
];
