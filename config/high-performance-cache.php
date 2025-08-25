<?php

return [
    /*
    |--------------------------------------------------------------------------
    | High Performance Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for high-load optimized API caching system.
    | Designed to handle thousands of concurrent requests efficiently.
    |
    */

    'enabled' => env('HIGH_PERFORMANCE_CACHE_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Redis Configuration
    |--------------------------------------------------------------------------
    |
    | Redis settings optimized for high-throughput caching.
    |
    */
    'redis' => [
        'connection' => env('HIGH_PERFORMANCE_CACHE_REDIS_CONNECTION', 'cache'),
        'prefix' => env('HIGH_PERFORMANCE_CACHE_PREFIX', 'hpc_api:'),
        'serializer' => 'json', // json, igbinary, php
        'compression' => env('HIGH_PERFORMANCE_CACHE_COMPRESSION', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Settings
    |--------------------------------------------------------------------------
    |
    | Settings to optimize performance under heavy load.
    |
    */
    'performance' => [
        // Batch size for bulk operations
        'batch_size' => env('HIGH_PERFORMANCE_CACHE_BATCH_SIZE', 1000),
        
        // Maximum concurrent cache operations
        'max_concurrent_ops' => env('HIGH_PERFORMANCE_CACHE_MAX_CONCURRENT', 50),
        
        // Connection pool size
        'connection_pool_size' => env('HIGH_PERFORMANCE_CACHE_POOL_SIZE', 10),
        
        // Pipeline batch size for Redis operations
        'pipeline_batch_size' => env('HIGH_PERFORMANCE_CACHE_PIPELINE_BATCH', 100),
        
        // Memory limit for cache operations (MB)
        'memory_limit' => env('HIGH_PERFORMANCE_CACHE_MEMORY_LIMIT', 256),
        
        // Enable async processing
        'async_enabled' => env('HIGH_PERFORMANCE_CACHE_ASYNC', true),
        
        // Queue for async operations
        'async_queue' => env('HIGH_PERFORMANCE_CACHE_QUEUE', 'high-priority'),
    ],

    /*
    |--------------------------------------------------------------------------
    | TTL Configuration
    |--------------------------------------------------------------------------
    |
    | Time-to-live settings for different cache layers.
    |
    */
    'ttl' => [
        // Default TTL in seconds
        'default' => env('HIGH_PERFORMANCE_CACHE_TTL', 3600), // 1 hour
        
        // Hot data TTL (frequently accessed)
        'hot_data' => env('HIGH_PERFORMANCE_CACHE_HOT_TTL', 1800), // 30 minutes
        
        // Cold data TTL (rarely accessed)
        'cold_data' => env('HIGH_PERFORMANCE_CACHE_COLD_TTL', 7200), // 2 hours
        
        // Pagination cache TTL
        'pagination' => env('HIGH_PERFORMANCE_CACHE_PAGINATION_TTL', 900), // 15 minutes
        
        // Index cache TTL
        'index' => env('HIGH_PERFORMANCE_CACHE_INDEX_TTL', 600), // 10 minutes
    ],

    /*
    |--------------------------------------------------------------------------
    | Monitoring & Metrics
    |--------------------------------------------------------------------------
    |
    | Performance monitoring and alerting configuration.
    |
    */
    'monitoring' => [
        'enabled' => env('HIGH_PERFORMANCE_CACHE_MONITORING', true),
        
        // Metrics collection interval (seconds)
        'metrics_interval' => env('HIGH_PERFORMANCE_CACHE_METRICS_INTERVAL', 60),
        
        // Performance thresholds for alerting
        'thresholds' => [
            'hit_rate_min' => env('HIGH_PERFORMANCE_CACHE_HIT_RATE_MIN', 0.85), // 85%
            'response_time_max' => env('HIGH_PERFORMANCE_CACHE_RESPONSE_MAX', 100), // 100ms
            'memory_usage_max' => env('HIGH_PERFORMANCE_CACHE_MEMORY_MAX', 0.80), // 80%
            'connection_pool_max' => env('HIGH_PERFORMANCE_CACHE_POOL_MAX', 0.90), // 90%
        ],
        
        // Alert channels
        'alerts' => [
            'slack_webhook' => env('HIGH_PERFORMANCE_CACHE_SLACK_WEBHOOK'),
            'email' => env('HIGH_PERFORMANCE_CACHE_ALERT_EMAIL'),
            'log_channel' => env('HIGH_PERFORMANCE_CACHE_LOG_CHANNEL', 'stack'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cleanup Configuration
    |--------------------------------------------------------------------------
    |
    | Automatic cleanup of expired and stale cache entries.
    |
    */
    'cleanup' => [
        'enabled' => env('HIGH_PERFORMANCE_CACHE_CLEANUP', true),
        
        // Cleanup interval (seconds)
        'interval' => env('HIGH_PERFORMANCE_CACHE_CLEANUP_INTERVAL', 3600), // 1 hour
        
        // Batch size for cleanup operations
        'batch_size' => env('HIGH_PERFORMANCE_CACHE_CLEANUP_BATCH', 5000),
        
        // Maximum cleanup time (seconds)
        'max_execution_time' => env('HIGH_PERFORMANCE_CACHE_CLEANUP_MAX_TIME', 300), // 5 minutes
        
        // Cleanup strategies
        'strategies' => [
            'expired' => true,  // Remove expired entries
            'lru' => true,      // Least Recently Used eviction
            'memory_pressure' => true, // Clean when memory usage is high
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Circuit Breaker
    |--------------------------------------------------------------------------
    |
    | Circuit breaker pattern to handle API failures gracefully.
    |
    */
    'circuit_breaker' => [
        'enabled' => env('HIGH_PERFORMANCE_CACHE_CIRCUIT_BREAKER', true),
        
        // Failure threshold before opening circuit
        'failure_threshold' => env('HIGH_PERFORMANCE_CACHE_FAILURE_THRESHOLD', 5),
        
        // Time to wait before trying again (seconds)
        'recovery_timeout' => env('HIGH_PERFORMANCE_CACHE_RECOVERY_TIMEOUT', 60),
        
        // Success threshold to close circuit
        'success_threshold' => env('HIGH_PERFORMANCE_CACHE_SUCCESS_THRESHOLD', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Rate limiting for cache operations to prevent overload.
    |
    */
    'rate_limiting' => [
        'enabled' => env('HIGH_PERFORMANCE_CACHE_RATE_LIMITING', true),
        
        // Maximum cache operations per minute
        'max_operations_per_minute' => env('HIGH_PERFORMANCE_CACHE_MAX_OPS_PER_MIN', 10000),
        
        // Maximum API calls per minute
        'max_api_calls_per_minute' => env('HIGH_PERFORMANCE_CACHE_MAX_API_PER_MIN', 1000),
        
        // Burst allowance
        'burst_allowance' => env('HIGH_PERFORMANCE_CACHE_BURST_ALLOWANCE', 100),
    ],

    /*
    |--------------------------------------------------------------------------
    | Warmup Configuration
    |--------------------------------------------------------------------------
    |
    | Cache warmup strategies for improved performance.
    |
    */
    'warmup' => [
        'enabled' => env('HIGH_PERFORMANCE_CACHE_WARMUP', true),
        
        // Warmup on application boot
        'on_boot' => env('HIGH_PERFORMANCE_CACHE_WARMUP_ON_BOOT', false),
        
        // Warmup schedule (cron expression)
        'schedule' => env('HIGH_PERFORMANCE_CACHE_WARMUP_SCHEDULE', '0 */6 * * *'), // Every 6 hours
        
        // Models to warmup
        'models' => [
            // 'App\Models\Product' => ['limit' => 1000],
            // 'App\Models\Category' => ['limit' => 500],
        ],
        
        // Warmup batch size
        'batch_size' => env('HIGH_PERFORMANCE_CACHE_WARMUP_BATCH', 500),
    ],

    /*
    |--------------------------------------------------------------------------
    | Debugging & Development
    |--------------------------------------------------------------------------
    |
    | Settings for debugging and development environments.
    |
    */
    'debug' => [
        'enabled' => env('HIGH_PERFORMANCE_CACHE_DEBUG', false),
        
        // Log all cache operations
        'log_operations' => env('HIGH_PERFORMANCE_CACHE_LOG_OPS', false),
        
        // Log performance metrics
        'log_metrics' => env('HIGH_PERFORMANCE_CACHE_LOG_METRICS', false),
        
        // Enable query logging
        'log_queries' => env('HIGH_PERFORMANCE_CACHE_LOG_QUERIES', false),
        
        // Profiling enabled
        'profiling' => env('HIGH_PERFORMANCE_CACHE_PROFILING', false),
    ],
];
