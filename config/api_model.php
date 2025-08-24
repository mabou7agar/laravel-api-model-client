<?php

return [
    /*
    |--------------------------------------------------------------------------
    | API Model Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains configuration options for API model synchronization.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | API Protocol
    |--------------------------------------------------------------------------
    |
    | The protocol to use for API communication. Supported values are:
    | - 'rest': Use RESTful HTTP API (default)
    | - 'grpc': Use gRPC protocol
    |
    */
    'protocol' => env('API_MODEL_PROTOCOL', 'rest'),

    /*
    |--------------------------------------------------------------------------
    | Queue Operations
    |--------------------------------------------------------------------------
    |
    | Determine whether API operations should be queued for asynchronous processing.
    | When set to true, API operations will be processed in the background,
    | improving response times for the user.
    |
    */
    'queue_operations' => env('API_MODEL_QUEUE_OPERATIONS', true),

    /*
    |--------------------------------------------------------------------------
    | Retry Attempts
    |--------------------------------------------------------------------------
    |
    | The number of times to retry API operations if they fail.
    | A higher number provides more resilience but may increase processing time.
    |
    */
    'retry_attempts' => env('API_MODEL_RETRY_ATTEMPTS', 3),

    /*
    |--------------------------------------------------------------------------
    | Sync in Testing
    |--------------------------------------------------------------------------
    |
    | Determine whether API operations should be performed in testing environments.
    | Setting this to false prevents API calls during automated tests.
    |
    */
    'sync_in_testing' => env('API_MODEL_SYNC_IN_TESTING', false),

    /*
    |--------------------------------------------------------------------------
    | Queue Name
    |--------------------------------------------------------------------------
    |
    | The name of the queue to use for API operations.
    | This allows you to route API operations to a specific queue worker.
    |
    */
    'queue_name' => env('API_MODEL_QUEUE_NAME', 'api-sync'),

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Settings related to caching API responses.
    |
    */
    'cache_enabled' => env('API_MODEL_CACHE_ENABLED', true),
    
    /*
    |--------------------------------------------------------------------------
    | Cache TTL (Time To Live)
    |--------------------------------------------------------------------------
    |
    | The time in seconds that API responses should be cached.
    | Default is 1 hour (3600 seconds).
    |
    */
    'cache_ttl' => env('API_MODEL_CACHE_TTL', 3600),

    /*
    |--------------------------------------------------------------------------
    | HTTP Client Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for the HTTP client used for API requests.
    |
    */
    'client' => [
        'base_url' => env('API_MODEL_BASE_URL', ''),
        'timeout' => env('API_MODEL_TIMEOUT', 30),
        'connect_timeout' => env('API_MODEL_CONNECT_TIMEOUT', 10),
        'verify' => env('API_MODEL_VERIFY_SSL', true),
        'headers' => [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for API authentication strategies.
    |
    */
    'auth' => [
        'strategy' => env('API_MODEL_AUTH_STRATEGY', null), // 'bearer', 'basic', 'api_key'
        'token' => env('API_MODEL_AUTH_TOKEN', ''),
        'username' => env('API_MODEL_AUTH_USERNAME', ''),
        'password' => env('API_MODEL_AUTH_PASSWORD', ''),
        'api_key' => env('API_MODEL_API_KEY', ''),
        'api_key_header' => env('API_MODEL_API_KEY_HEADER', 'X-API-Key'),
    ],

    /*
    |--------------------------------------------------------------------------
    | gRPC Configuration
    |--------------------------------------------------------------------------
    |
    | Settings specific to gRPC protocol.
    |
    */
    'grpc' => [
        /*
        |--------------------------------------------------------------------------
        | Service Definitions
        |--------------------------------------------------------------------------
        |
        | Maps model classes to their corresponding gRPC service definitions.
        | Each entry should contain:
        | - 'client': The gRPC client class
        | - 'methods': Mapping of operations to gRPC methods and request classes
        |
        | Example:
        | 'App\Models\User' => [
        |     'client' => 'App\Grpc\UserServiceClient',
        |     'methods' => [
        |         'get' => [
        |             'method' => 'GetUser',
        |             'request' => 'App\Grpc\GetUserRequest',
        |         ],
        |         'list' => [
        |             'method' => 'ListUsers',
        |             'request' => 'App\Grpc\ListUsersRequest',
        |         ],
        |         'create' => [
        |             'method' => 'CreateUser',
        |             'request' => 'App\Grpc\CreateUserRequest',
        |         ],
        |         'update' => [
        |             'method' => 'UpdateUser',
        |             'request' => 'App\Grpc\UpdateUserRequest',
        |         ],
        |         'delete' => [
        |             'method' => 'DeleteUser',
        |             'request' => 'App\Grpc\DeleteUserRequest',
        |         ],
        |     ],
        | ],
        */
        'services' => [],
        
        /*
        |--------------------------------------------------------------------------
        | Default Options
        |--------------------------------------------------------------------------
        |
        | Default options for gRPC channel.
        |
        */
        'options' => [
            'credentials' => 'insecure', // 'insecure', 'ssl', or 'google_default'
            'timeout' => 30, // seconds
            'max_receive_message_length' => -1, // unlimited
            'max_send_message_length' => -1, // unlimited
        ],
    ],
];
