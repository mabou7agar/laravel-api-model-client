<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Hybrid Data Source Configuration
    |--------------------------------------------------------------------------
    |
    | Configure how API models handle data operations between database and API.
    | This provides intelligent switching between local database and remote API
    | based on your application needs.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Global Data Source Mode
    |--------------------------------------------------------------------------
    |
    | Default mode for all API models. Can be overridden per model.
    |
    | Available modes:
    | - 'api_only': All operations use API exclusively
    | - 'db_only': All operations use database exclusively  
    | - 'hybrid': Check database first, fallback to API
    | - 'api_first': Check API first, sync to database
    | - 'dual_sync': Keep both database and API in sync
    |
    */
    'global_mode' => env('API_MODEL_DATA_SOURCE_MODE', 'hybrid'),

    /*
    |--------------------------------------------------------------------------
    | Model-Specific Configurations
    |--------------------------------------------------------------------------
    |
    | Override the global mode for specific models.
    |
    */
    'models' => [
        // Example configurations
        'product' => [
            'data_source_mode' => env('PRODUCT_DATA_SOURCE_MODE', 'api_first'),
            'sync_enabled' => true,
            'cache_ttl' => 3600, // 1 hour
        ],
        
        'category' => [
            'data_source_mode' => env('CATEGORY_DATA_SOURCE_MODE', 'hybrid'),
            'sync_enabled' => true,
            'cache_ttl' => 7200, // 2 hours
        ],
        
        'order' => [
            'data_source_mode' => env('ORDER_DATA_SOURCE_MODE', 'dual_sync'),
            'sync_enabled' => true,
            'cache_ttl' => 1800, // 30 minutes
        ],
        
        'customer' => [
            'data_source_mode' => env('CUSTOMER_DATA_SOURCE_MODE', 'api_first'),
            'sync_enabled' => true,
            'cache_ttl' => 3600, // 1 hour
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Sync Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for data synchronization between database and API.
    |
    */
    'sync' => [
        // Enable automatic background sync
        'auto_sync' => env('API_MODEL_AUTO_SYNC', true),
        
        // Sync interval in minutes
        'sync_interval' => env('API_MODEL_SYNC_INTERVAL', 60),
        
        // Maximum items to sync per batch
        'batch_size' => env('API_MODEL_SYNC_BATCH_SIZE', 100),
        
        // Queue for background sync jobs
        'sync_queue' => env('API_MODEL_SYNC_QUEUE', 'default'),
        
        // Enable conflict resolution
        'conflict_resolution' => env('API_MODEL_CONFLICT_RESOLUTION', true),
        
        // Conflict resolution strategy: 'api_wins', 'db_wins', 'newest_wins'
        'conflict_strategy' => env('API_MODEL_CONFLICT_STRATEGY', 'newest_wins'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Fallback Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for handling failures and fallbacks.
    |
    */
    'fallback' => [
        // Enable fallback to alternative data source on failure
        'enabled' => env('API_MODEL_FALLBACK_ENABLED', true),
        
        // Maximum retry attempts
        'max_retries' => env('API_MODEL_MAX_RETRIES', 3),
        
        // Retry delay in seconds
        'retry_delay' => env('API_MODEL_RETRY_DELAY', 1),
        
        // Timeout for API operations in seconds
        'api_timeout' => env('API_MODEL_API_TIMEOUT', 30),
        
        // Enable graceful degradation
        'graceful_degradation' => env('API_MODEL_GRACEFUL_DEGRADATION', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Configuration
    |--------------------------------------------------------------------------
    |
    | Settings to optimize performance for different scenarios.
    |
    */
    'performance' => [
        // Enable lazy loading for relationships
        'lazy_loading' => env('API_MODEL_LAZY_LOADING', true),
        
        // Enable eager loading optimization
        'eager_loading' => env('API_MODEL_EAGER_LOADING', true),
        
        // Maximum concurrent API requests
        'max_concurrent_requests' => env('API_MODEL_MAX_CONCURRENT', 10),
        
        // Enable request batching
        'batch_requests' => env('API_MODEL_BATCH_REQUESTS', true),
        
        // Batch size for bulk operations
        'bulk_batch_size' => env('API_MODEL_BULK_BATCH_SIZE', 50),
    ],

    /*
    |--------------------------------------------------------------------------
    | Monitoring Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for monitoring and logging hybrid operations.
    |
    */
    'monitoring' => [
        // Enable operation logging
        'log_operations' => env('API_MODEL_LOG_OPERATIONS', false),
        
        // Log level for operations
        'log_level' => env('API_MODEL_LOG_LEVEL', 'info'),
        
        // Enable performance metrics
        'metrics_enabled' => env('API_MODEL_METRICS_ENABLED', true),
        
        // Metrics collection interval in seconds
        'metrics_interval' => env('API_MODEL_METRICS_INTERVAL', 300),
        
        // Enable health checks
        'health_checks' => env('API_MODEL_HEALTH_CHECKS', true),
        
        // Health check interval in minutes
        'health_check_interval' => env('API_MODEL_HEALTH_CHECK_INTERVAL', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Development Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for development and debugging.
    |
    */
    'development' => [
        // Enable debug mode
        'debug' => env('API_MODEL_DEBUG', false),
        
        // Enable query logging
        'log_queries' => env('API_MODEL_LOG_QUERIES', false),
        
        // Enable API request/response logging
        'log_api_calls' => env('API_MODEL_LOG_API_CALLS', false),
        
        // Enable sync operation logging
        'log_sync_operations' => env('API_MODEL_LOG_SYNC', false),
        
        // Enable performance profiling
        'profiling' => env('API_MODEL_PROFILING', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Environment-Specific Overrides
    |--------------------------------------------------------------------------
    |
    | Override settings based on application environment.
    |
    */
    'environments' => [
        'local' => [
            'global_mode' => 'hybrid',
            'sync' => ['auto_sync' => false],
            'development' => ['debug' => true, 'log_queries' => true],
        ],
        
        'testing' => [
            'global_mode' => 'db_only',
            'sync' => ['auto_sync' => false],
            'fallback' => ['enabled' => false],
        ],
        
        'staging' => [
            'global_mode' => 'api_first',
            'sync' => ['auto_sync' => true],
            'monitoring' => ['log_operations' => true],
        ],
        
        'production' => [
            'global_mode' => 'dual_sync',
            'sync' => ['auto_sync' => true, 'batch_size' => 200],
            'performance' => ['max_concurrent_requests' => 20],
            'monitoring' => ['metrics_enabled' => true, 'health_checks' => true],
        ],
    ],
];
