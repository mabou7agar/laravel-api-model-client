<?php

return [
    /*
    |--------------------------------------------------------------------------
    | OpenAPI Parser Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains configuration options for the OpenAPI schema parser.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Cache Settings
    |--------------------------------------------------------------------------
    |
    | Configure caching behavior for parsed OpenAPI schemas.
    |
    */
    'cache' => [
        'enabled' => env('OPENAPI_CACHE_ENABLED', true),
        'ttl' => env('OPENAPI_CACHE_TTL', 3600), // 1 hour in seconds
        'prefix' => env('OPENAPI_CACHE_PREFIX', 'openapi_schema_'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Remote Schema Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for loading schemas from remote URLs.
    |
    */
    'remote' => [
        'timeout' => env('OPENAPI_REMOTE_TIMEOUT', 30), // seconds
        'max_file_size' => env('OPENAPI_MAX_FILE_SIZE', 10485760), // 10MB
        'user_agent' => env('OPENAPI_USER_AGENT', 'Laravel-API-Model-Client/1.0'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Supported OpenAPI Versions
    |--------------------------------------------------------------------------
    |
    | List of supported OpenAPI specification versions.
    |
    */
    'supported_versions' => [
        '3.0.0',
        '3.0.1',
        '3.0.2',
        '3.0.3',
        '3.1.0',
    ],

    /*
    |--------------------------------------------------------------------------
    | Model Generation Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for automatic model generation from OpenAPI schemas.
    |
    */
    'model_generation' => [
        'namespace' => env('OPENAPI_MODEL_NAMESPACE', 'App\\Models'),
        'base_class' => env('OPENAPI_MODEL_BASE_CLASS', 'MTechStack\\LaravelApiModelClient\\Models\\ApiModel'),
        'output_directory' => env('OPENAPI_MODEL_OUTPUT_DIR', app_path('Models')),
        'overwrite_existing' => env('OPENAPI_MODEL_OVERWRITE', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Validation Rules Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for generating Laravel validation rules from OpenAPI schemas.
    |
    */
    'validation' => [
        'generate_rules' => env('OPENAPI_GENERATE_VALIDATION', true),
        'strict_mode' => env('OPENAPI_VALIDATION_STRICT', false),
        'custom_formats' => [
            // Add custom format validators
            // 'custom-format' => 'CustomFormatValidator',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Schema Processing Options
    |--------------------------------------------------------------------------
    |
    | Options for processing OpenAPI schemas.
    |
    */
    'processing' => [
        'resolve_references' => env('OPENAPI_RESOLVE_REFS', true),
        'extract_examples' => env('OPENAPI_EXTRACT_EXAMPLES', true),
        'generate_relationships' => env('OPENAPI_GENERATE_RELATIONSHIPS', true),
        'detect_pagination' => env('OPENAPI_DETECT_PAGINATION', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    |
    | Configure logging for OpenAPI parser operations.
    |
    */
    'logging' => [
        'enabled' => env('OPENAPI_LOGGING_ENABLED', true),
        'level' => env('OPENAPI_LOG_LEVEL', 'info'),
        'channel' => env('OPENAPI_LOG_CHANNEL', 'single'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Schema Sources
    |--------------------------------------------------------------------------
    |
    | Pre-configured schema sources for common APIs.
    |
    */
    'default_schemas' => [
        // 'petstore' => 'https://petstore3.swagger.io/api/v3/openapi.json',
        // 'github' => 'https://raw.githubusercontent.com/github/rest-api-description/main/descriptions/api.github.com/api.github.com.json',
    ],

    /*
    |--------------------------------------------------------------------------
    | Model Schema Mappings
    |--------------------------------------------------------------------------
    |
    | Map specific model classes to their OpenAPI schema sources.
    | This allows different models to use different schemas.
    |
    */
    'model_schemas' => [
        // 'App\\Models\\Pet' => '/path/to/petstore-openapi.json',
        // 'App\\Models\\User' => 'https://api.example.com/openapi.json',
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Schema Source
    |--------------------------------------------------------------------------
    |
    | Default OpenAPI schema source for models that don't have a specific mapping.
    |
    */
    'default_schema' => env('OPENAPI_DEFAULT_SCHEMA'),

    /*
    |--------------------------------------------------------------------------
    | Security Settings
    |--------------------------------------------------------------------------
    |
    | Security-related configuration for schema parsing.
    |
    */
    'security' => [
        'allow_remote_schemas' => env('OPENAPI_ALLOW_REMOTE', true),
        'allowed_domains' => env('OPENAPI_ALLOWED_DOMAINS', '*'), // comma-separated or '*' for all
        'verify_ssl' => env('OPENAPI_VERIFY_SSL', true),
    ],
];
