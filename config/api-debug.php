<?php

return [
    /*
    |--------------------------------------------------------------------------
    | API Debug Configuration
    |--------------------------------------------------------------------------
    |
    | This configuration controls the automatic debugging features for the
    | Laravel API Model Client package. Debug mode can be automatically
    | enabled based on environment variables.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Auto Debug Activation
    |--------------------------------------------------------------------------
    |
    | These settings control when debugging is automatically enabled.
    | Set API_CLIENT_AUTO_DEBUG=true in your .env to enable all debugging.
    |
    */
    'auto_enable' => env('API_CLIENT_AUTO_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Debug Triggers
    |--------------------------------------------------------------------------
    |
    | Multiple environment variables can trigger debug mode activation.
    |
    */
    'triggers' => [
        'http_client_debug' => env('HTTP_CLIENT_DEBUG', false),
        'api_debug_mode' => env('API_CLIENT_DEBUG_MODE', false),
        'app_debug_and_api_debug' => env('APP_DEBUG', false) && env('API_CLIENT_DEBUG_MODE', false),
        'local_environment' => app()->environment(['local', 'testing']) && env('API_CLIENT_DEBUG_IN_LOCAL', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Output Settings
    |--------------------------------------------------------------------------
    |
    | Control where and how debug information is displayed.
    |
    */
    'output' => [
        'console_verbose' => env('API_CLIENT_DEBUG_VERBOSE', true),
        'web_debug' => env('API_CLIENT_DEBUG_WEB', false),
        'show_status' => env('API_CLIENT_DEBUG_SHOW_STATUS', true),
        'max_response_size' => env('API_CLIENT_DEBUG_MAX_RESPONSE_SIZE', 1000),
        'max_body_size' => env('API_CLIENT_DEBUG_MAX_BODY_SIZE', 200),
        'show_raw_response' => env('API_CLIENT_DEBUG_SHOW_RAW', false),
        'max_raw_size' => env('API_CLIENT_DEBUG_MAX_RAW_SIZE', 2000),
        'show_response_headers' => env('API_CLIENT_DEBUG_SHOW_HEADERS', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Settings
    |--------------------------------------------------------------------------
    |
    | Configure file logging for debug information.
    |
    */
    'logging' => [
        'enabled' => env('API_CLIENT_DEBUG_LOG_FILE', true),
        'channel' => env('API_CLIENT_DEBUG_LOG_CHANNEL', 'single'),
        'level' => env('API_CLIENT_LOG_LEVEL', 'debug'),
        'log_raw_response' => env('API_CLIENT_DEBUG_LOG_RAW_RESPONSE', true),
        'max_log_size' => env('API_CLIENT_DEBUG_MAX_LOG_SIZE', 5000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Feature Toggles
    |--------------------------------------------------------------------------
    |
    | Enable or disable specific debugging features.
    |
    */
    'features' => [
        'http_requests' => env('API_CLIENT_DEBUG_HTTP', true),
        'database_queries' => env('API_CLIENT_DEBUG_QUERIES', false),
        'api_model_events' => env('API_CLIENT_DEBUG_EVENTS', true),
        'profiling' => env('API_CLIENT_PROFILING', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Settings
    |--------------------------------------------------------------------------
    |
    | Configure which headers and data should be filtered from debug output.
    |
    */
    'security' => [
        'filter_sensitive_headers' => env('API_CLIENT_DEBUG_FILTER_HEADERS', true),
        'sensitive_header_keys' => [
            'authorization', 'cookie', 'x-api-key', 'api-key', 'x-auth-token',
            'bearer', 'token', 'password', 'secret', 'key', 'x-csrf-token'
        ],
        'mask_sensitive_data' => env('API_CLIENT_DEBUG_MASK_DATA', true),
        'sensitive_data_keys' => [
            'password', 'token', 'secret', 'key', 'api_key', 'access_token',
            'refresh_token', 'client_secret', 'private_key'
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Settings
    |--------------------------------------------------------------------------
    |
    | Configure performance-related debug settings.
    |
    */
    'performance' => [
        'track_timing' => env('API_CLIENT_DEBUG_TIMING', true),
        'memory_usage' => env('API_CLIENT_DEBUG_MEMORY', false),
        'slow_query_threshold' => env('API_CLIENT_DEBUG_SLOW_QUERY_MS', 100),
        'slow_request_threshold' => env('API_CLIENT_DEBUG_SLOW_REQUEST_MS', 1000),
    ],
];
