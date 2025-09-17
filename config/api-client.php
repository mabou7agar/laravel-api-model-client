<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default API Client Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains the default configuration for the Laravel API Model
    | Client package. You can override these settings in your application's
    | config/api-client.php file after publishing.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Default Schema Configuration
    |--------------------------------------------------------------------------
    |
    | The default schema to use when no specific schema is specified.
    | This should match one of the keys in the 'schemas' array below.
    |
    */
    'default_schema' => env('API_CLIENT_DEFAULT_SCHEMA', 'primary'),

    /*
    |--------------------------------------------------------------------------
    | Multiple API Schemas Configuration
    |--------------------------------------------------------------------------
    |
    | Configure multiple API schemas for different services or environments.
    | Each schema can have its own OpenAPI specification, endpoints, and settings.
    |
    */
    'schemas' => [
        'primary' => [
            'name' => 'Primary API',
            'description' => 'Main application API schema',
            'source' => env('API_CLIENT_PRIMARY_SCHEMA', null),
            'base_url' => env('API_CLIENT_PRIMARY_BASE_URL', 'https://api.example.com'),
            'version' => env('API_CLIENT_PRIMARY_VERSION', 'v1'),
            'enabled' => env('API_CLIENT_PRIMARY_ENABLED', true),
            'timeout' => env('API_CLIENT_PRIMARY_TIMEOUT', 30),
            'retry_attempts' => env('API_CLIENT_PRIMARY_RETRY', 3),
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'User-Agent' => 'Laravel-API-Client/1.0',
            ],
            'authentication' => [
                'type' => env('API_CLIENT_PRIMARY_AUTH_TYPE', 'bearer'), // bearer, api_key, basic, oauth2
                'token' => env('API_CLIENT_PRIMARY_TOKEN', null),
                'api_key' => env('API_CLIENT_PRIMARY_API_KEY', null),
                'api_key_header' => env('API_CLIENT_PRIMARY_API_KEY_HEADER', 'X-API-Key'),
                'username' => env('API_CLIENT_PRIMARY_USERNAME', null),
                'password' => env('API_CLIENT_PRIMARY_PASSWORD', null),
            ],
            'model_generation' => [
                'enabled' => true,
                'namespace' => 'App\\Models\\Api\\Primary',
                'output_directory' => app_path('Models/Api/Primary'),
                'auto_generate' => env('API_CLIENT_PRIMARY_AUTO_GENERATE', false),
                'generate_factories' => true,
                'generate_schemas' => true,
                'naming_convention' => 'pascal_case', // pascal_case, snake_case, camel_case
                'prefix' => '',
                'suffix' => '',
            ],
            'validation' => [
                'strictness' => env('API_CLIENT_PRIMARY_VALIDATION_STRICTNESS', 'strict'), // strict, moderate, lenient
                'fail_on_unknown_properties' => true,
                'fail_on_missing_required' => true,
                'auto_cast_types' => true,
                'validate_formats' => true,
            ],
            'caching' => [
                'enabled' => true,
                'ttl' => env('API_CLIENT_PRIMARY_CACHE_TTL', 3600), // 1 hour
                'store' => env('API_CLIENT_PRIMARY_CACHE_STORE', env('API_CLIENT_CACHE_STORE', 'database')),
                'prefix' => 'api_client_primary_',
                'tags' => ['api-client', 'primary-schema'],
            ],
        ],

        'secondary' => [
            'name' => 'Secondary API',
            'description' => 'Secondary service API schema',
            'source' => env('API_CLIENT_SECONDARY_SCHEMA', null),
            'base_url' => env('API_CLIENT_SECONDARY_BASE_URL', 'https://api2.example.com'),
            'version' => env('API_CLIENT_SECONDARY_VERSION', 'v1'),
            'enabled' => env('API_CLIENT_SECONDARY_ENABLED', false),
            'timeout' => env('API_CLIENT_SECONDARY_TIMEOUT', 30),
            'retry_attempts' => env('API_CLIENT_SECONDARY_RETRY', 3),
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
            'authentication' => [
                'type' => env('API_CLIENT_SECONDARY_AUTH_TYPE', 'api_key'),
                'api_key' => env('API_CLIENT_SECONDARY_API_KEY', null),
                'api_key_header' => env('API_CLIENT_SECONDARY_API_KEY_HEADER', 'X-API-Key'),
            ],
            'model_generation' => [
                'enabled' => false,
                'namespace' => 'App\\Models\\Api\\Secondary',
                'output_directory' => app_path('Models/Api/Secondary'),
                'auto_generate' => false,
                'generate_factories' => false,
                'generate_schemas' => false,
                'naming_convention' => 'pascal_case',
                'prefix' => '',
                'suffix' => '',
            ],
            'validation' => [
                'strictness' => 'moderate',
                'fail_on_unknown_properties' => false,
                'fail_on_missing_required' => true,
                'auto_cast_types' => true,
                'validate_formats' => false,
            ],
            'caching' => [
                'enabled' => true,
                'ttl' => 1800, // 30 minutes
                'store' => env('API_CLIENT_SECONDARY_CACHE_STORE', env('API_CLIENT_CACHE_STORE', 'database')),
                'prefix' => 'api_client_secondary_',
                'tags' => ['api-client', 'secondary-schema'],
            ],
        ],

        'testing' => [
            'name' => 'Testing API',
            'description' => 'Testing environment API schema',
            'source' => env('API_CLIENT_TESTING_SCHEMA', null),
            'base_url' => env('API_CLIENT_TESTING_BASE_URL', 'https://test-api.example.com'),
            'version' => env('API_CLIENT_TESTING_VERSION', 'v1'),
            'enabled' => env('API_CLIENT_TESTING_ENABLED', app()->environment('testing')),
            'timeout' => 10,
            'retry_attempts' => 1,
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
            'authentication' => [
                'type' => 'bearer',
                'token' => env('API_CLIENT_TESTING_TOKEN', 'test-token'),
            ],
            'model_generation' => [
                'enabled' => true,
                'namespace' => 'Tests\\Models\\Api',
                'output_directory' => base_path('tests/Models/Api'),
                'auto_generate' => true,
                'generate_factories' => true,
                'generate_schemas' => false,
                'naming_convention' => 'pascal_case',
                'prefix' => 'Test',
                'suffix' => '',
            ],
            'validation' => [
                'strictness' => 'lenient',
                'fail_on_unknown_properties' => false,
                'fail_on_missing_required' => false,
                'auto_cast_types' => true,
                'validate_formats' => false,
            ],
            'caching' => [
                'enabled' => false,
                'ttl' => 300, // 5 minutes
                'store' => env('API_CLIENT_TESTING_CACHE_STORE', env('API_CLIENT_CACHE_STORE', 'array')),
                'prefix' => 'api_client_testing_',
                'tags' => ['api-client', 'testing-schema'],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Schema Versioning and Migration
    |--------------------------------------------------------------------------
    |
    | Configuration for schema versioning and migration support.
    | This allows you to manage schema changes over time.
    |
    */
    'versioning' => [
        'enabled' => env('API_CLIENT_VERSIONING_ENABLED', true),
        'storage_path' => storage_path('api-client/schemas'),
        'backup_enabled' => true,
        'backup_retention_days' => 30,
        'auto_migrate' => env('API_CLIENT_AUTO_MIGRATE', false),
        'migration_strategy' => 'backup_and_replace', // backup_and_replace, merge, manual
        'version_format' => 'Y-m-d_H-i-s', // PHP date format for version timestamps
        'compare_strategy' => 'hash', // hash, content, timestamp
    ],

    /*
    |--------------------------------------------------------------------------
    | Global Caching Configuration
    |--------------------------------------------------------------------------
    |
    | Global caching settings that apply to all schemas unless overridden.
    |
    */
    'caching' => [
        'enabled' => env('API_CLIENT_CACHE_ENABLED', true),
        'default_ttl' => env('API_CLIENT_CACHE_TTL', 3600),
        'store' => env('API_CLIENT_CACHE_STORE', 'database'),
        'prefix' => env('API_CLIENT_CACHE_PREFIX', 'api_client_'),
        'tags' => ['api-client'],
        'compression' => env('API_CLIENT_CACHE_COMPRESSION', false),
        'serialization' => 'json', // json, serialize, igbinary
    ],

    /*
    |--------------------------------------------------------------------------
    | Health Checks and Monitoring
    |--------------------------------------------------------------------------
    |
    | Configuration for schema health checks and monitoring.
    |
    */
    'health_checks' => [
        'enabled' => env('API_CLIENT_HEALTH_CHECKS_ENABLED', true),
        'schedule' => env('API_CLIENT_HEALTH_CHECK_SCHEDULE', '0 */6 * * *'), // Every 6 hours
        'timeout' => env('API_CLIENT_HEALTH_CHECK_TIMEOUT', 30),
        'retry_attempts' => 3,
        'fail_threshold' => 3, // Number of consecutive failures before marking as unhealthy
        'notification_channels' => ['log'], // log, mail, slack, webhook
        'webhook_url' => env('API_CLIENT_HEALTH_WEBHOOK_URL', null),
        'checks' => [
            'schema_accessibility' => true,
            'schema_validity' => true,
            'endpoint_connectivity' => true,
            'authentication' => true,
            'response_time' => true,
            'cache_health' => true,
        ],
        'thresholds' => [
            'response_time_ms' => 5000,
            'cache_hit_ratio' => 0.8,
            'error_rate' => 0.05,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging and Debugging
    |--------------------------------------------------------------------------
    |
    | Configuration for logging and debugging OpenAPI operations.
    |
    */
    'logging' => [
        'enabled' => env('API_CLIENT_LOGGING_ENABLED', true),
        'level' => env('API_CLIENT_LOG_LEVEL', 'info'), // debug, info, warning, error
        'channel' => env('API_CLIENT_LOG_CHANNEL', 'default'),
        'log_requests' => env('API_CLIENT_LOG_REQUESTS', false),
        'log_responses' => env('API_CLIENT_LOG_RESPONSES', false),
        'log_schema_parsing' => env('API_CLIENT_LOG_SCHEMA_PARSING', true),
        'log_model_generation' => env('API_CLIENT_LOG_MODEL_GENERATION', true),
        'log_cache_operations' => env('API_CLIENT_LOG_CACHE_OPERATIONS', false),
        'log_validation_errors' => env('API_CLIENT_LOG_VALIDATION_ERRORS', true),
        'sensitive_fields' => ['password', 'token', 'api_key', 'secret'], // Fields to mask in logs
        'max_log_size' => 1024, // Maximum size of logged content in KB
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Configuration
    |--------------------------------------------------------------------------
    |
    | Security settings for OpenAPI operations.
    |
    */
    'security' => [
        'verify_ssl' => env('API_CLIENT_VERIFY_SSL', true),
        'ssl_cert_path' => env('API_CLIENT_SSL_CERT_PATH', null),
        'ssl_key_path' => env('API_CLIENT_SSL_KEY_PATH', null),
        'allowed_hosts' => env('API_CLIENT_ALLOWED_HOSTS', null), // Comma-separated list
        'rate_limiting' => [
            'enabled' => env('API_CLIENT_RATE_LIMITING_ENABLED', true),
            'requests_per_minute' => env('API_CLIENT_RATE_LIMIT_RPM', 60),
            'burst_limit' => env('API_CLIENT_RATE_LIMIT_BURST', 10),
        ],
        'encryption' => [
            'enabled' => env('API_CLIENT_ENCRYPTION_ENABLED', false),
            'algorithm' => 'AES-256-CBC',
            'key' => env('API_CLIENT_ENCRYPTION_KEY', null),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Configuration
    |--------------------------------------------------------------------------
    |
    | Performance optimization settings.
    |
    */
    'performance' => [
        'connection_pooling' => env('API_CLIENT_CONNECTION_POOLING', true),
        'keep_alive' => env('API_CLIENT_KEEP_ALIVE', true),
        'compression' => env('API_CLIENT_COMPRESSION', true),
        'parallel_requests' => env('API_CLIENT_PARALLEL_REQUESTS', 5),
        'memory_limit' => env('API_CLIENT_MEMORY_LIMIT', '256M'),
        'max_execution_time' => env('API_CLIENT_MAX_EXECUTION_TIME', 300),
        'lazy_loading' => env('API_CLIENT_LAZY_LOADING', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Development and Testing
    |--------------------------------------------------------------------------
    |
    | Settings for development and testing environments.
    |
    */
    'development' => [
        'mock_responses' => env('API_CLIENT_MOCK_RESPONSES', false),
        'mock_data_path' => storage_path('api-client/mocks'),
        'generate_mock_data' => env('API_CLIENT_GENERATE_MOCK_DATA', false),
        'validate_examples' => env('API_CLIENT_VALIDATE_EXAMPLES', true),
        'debug_mode' => env('API_CLIENT_DEBUG_MODE',false),
        'profiling' => env('API_CLIENT_PROFILING', false),
        'test_coverage' => env('API_CLIENT_TEST_COVERAGE', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | SSL Certificate Verification
    |--------------------------------------------------------------------------
    |
    | Configure SSL certificate verification behavior for different environments.
    | In local/debug environments, you may want to disable SSL verification
    | for self-signed certificates or development APIs.
    |
    | Set API_CLIENT_SSL_VERIFY=false in your .env file to disable SSL verification
    | Set APP_ENV=local or APP_ENV=testing to automatically disable SSL verification
    |
    */
    'ssl' => [
        'verify' => env('API_CLIENT_SSL_VERIFY', env('APP_ENV', 'production') !== 'local' && env('APP_ENV', 'production') !== 'testing'),
        'verify_host' => env('API_CLIENT_SSL_VERIFY_HOST', env('APP_ENV', 'production') !== 'local' && env('APP_ENV', 'production') !== 'testing'),
        'allow_self_signed' => env('API_CLIENT_SSL_ALLOW_SELF_SIGNED', env('APP_ENV', 'production') === 'local' || env('APP_ENV', 'production') === 'testing'),
        'ca_bundle' => env('API_CLIENT_SSL_CA_BUNDLE', null),
    ],

    /*
    |--------------------------------------------------------------------------
    | Command Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Artisan commands.
    |
    */
    'commands' => [
        'generate_models' => [
            'enabled' => true,
            'default_options' => [
                'force' => false,
                'update' => false,
                'factories' => true,
                'schemas' => false,
                'dry_run' => false,
            ],
        ],
        'validate_schema' => [
            'enabled' => true,
            'auto_fix' => false,
            'report_format' => 'table', // table, json, yaml
        ],
        'cache_management' => [
            'enabled' => true,
            'auto_warm' => false,
            'warm_on_deploy' => true,
        ],
    ],
];
