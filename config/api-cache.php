<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Cache TTL (Time To Live)
    |--------------------------------------------------------------------------
    |
    | Default time in minutes for how long API data should be cached.
    | This can be overridden per model or per request.
    |
    */
    'default_ttl' => env('API_CACHE_DEFAULT_TTL', 60), // 1 hour

    /*
    |--------------------------------------------------------------------------
    | Cache TTL by Entity Type
    |--------------------------------------------------------------------------
    |
    | Specific TTL settings for different entity types.
    | This allows fine-grained control over cache duration.
    |
    */
    'ttl_by_type' => [
        'Product' => env('API_CACHE_PRODUCT_TTL', 30),      // 30 minutes for products
        'Category' => env('API_CACHE_CATEGORY_TTL', 120),   // 2 hours for categories
        'Order' => env('API_CACHE_ORDER_TTL', 5),           // 5 minutes for orders
        'Customer' => env('API_CACHE_CUSTOMER_TTL', 15),    // 15 minutes for customers
        'Review' => env('API_CACHE_REVIEW_TTL', 60),        // 1 hour for reviews
    ],

    /*
    |--------------------------------------------------------------------------
    | Real-time Data Types
    |--------------------------------------------------------------------------
    |
    | Entity types that should always fetch fresh data from API.
    | These will bypass cache and always make API calls.
    |
    */
    'real_time_types' => [
        'Payment',
        'Transaction',
        'Stock', // Real-time inventory
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Strategy
    |--------------------------------------------------------------------------
    |
    | Default caching strategy:
    | - 'hybrid': Use cache if fresh, API if stale
    | - 'cache_first': Always try cache first, API as fallback
    | - 'api_first': Always try API first, cache as fallback
    | - 'cache_only': Only use cache (offline mode)
    | - 'api_only': Never use cache (always fresh)
    |
    */
    'default_strategy' => env('API_CACHE_STRATEGY', 'hybrid'),

    /*
    |--------------------------------------------------------------------------
    | Auto Cleanup
    |--------------------------------------------------------------------------
    |
    | Automatically clean up stale cache entries.
    |
    */
    'auto_cleanup' => [
        'enabled' => env('API_CACHE_AUTO_CLEANUP', true),
        'frequency' => env('API_CACHE_CLEANUP_FREQUENCY', 'daily'), // daily, hourly
        'keep_stale_for_hours' => env('API_CACHE_KEEP_STALE_HOURS', 24), // Keep stale data for 24 hours
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Settings
    |--------------------------------------------------------------------------
    |
    | Settings to optimize cache performance.
    |
    */
    'performance' => [
        'batch_size' => env('API_CACHE_BATCH_SIZE', 100), // Batch size for bulk operations
        'max_cache_size_mb' => env('API_CACHE_MAX_SIZE_MB', 500), // Max cache size in MB
    ],

    /*
    |--------------------------------------------------------------------------
    | Debug Settings
    |--------------------------------------------------------------------------
    |
    | Debug and logging settings for API cache.
    |
    */
    'debug' => [
        'enabled' => env('API_CACHE_DEBUG', false),
        'log_hits' => env('API_CACHE_LOG_HITS', false),
        'log_misses' => env('API_CACHE_LOG_MISSES', true),
        'log_updates' => env('API_CACHE_LOG_UPDATES', true),
    ],
];
