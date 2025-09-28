<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Morph Map Configuration
    |--------------------------------------------------------------------------
    |
    | Define the mapping between morph types stored in the database and
    | their corresponding model classes. This helps normalize polymorphic
    | relationships and prevents storing full class names in the database.
    |
    */
    'morph_map' => [
        // Example mappings - customize for your application
        // 'product' => \Modules\BagistoProduct\Models\Api\Product::class,
        // 'user' => \App\Models\User::class,
        // 'category' => \Modules\BagistoProduct\Models\Api\Category::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Enforce Morph Map
    |--------------------------------------------------------------------------
    |
    | When enabled, Laravel will only allow morph types that are defined
    | in the morph_map above. This prevents storing full class names in
    | the database and enforces consistency.
    |
    */
    'enforce_morph_map' => false,

    /*
    |--------------------------------------------------------------------------
    | Global MorphTo Override
    |--------------------------------------------------------------------------
    |
    | Enable or disable the global morphTo override functionality.
    | When enabled, any morphTo relationship that targets an ApiModel
    | will automatically use MorphToFromApi instead of the standard MorphTo.
    |
    */
    'enable_global_morph_override' => true,

    /*
    |--------------------------------------------------------------------------
    | Morph Relation Names
    |--------------------------------------------------------------------------
    |
    | List of common morphTo relation names that should be automatically
    | overridden. Add any custom morph relation names your application uses.
    |
    */
    'morph_relation_names' => [
        'entity',
        'subject', 
        'target',
        'owner',
        'morph',
        'morphable',
        'related',
        'parent',
        'source',
    ],

    /*
    |--------------------------------------------------------------------------
    | Debug Morph Override
    |--------------------------------------------------------------------------
    |
    | Enable debug logging for morphTo override activities.
    | Useful for troubleshooting morph relationship issues.
    |
    */
    'debug_morph_override' => env('API_MODEL_DEBUG_MORPH', false),

    /*
    |--------------------------------------------------------------------------
    | API Model Detection
    |--------------------------------------------------------------------------
    |
    | Configuration for detecting ApiModel classes in morph relationships.
    |
    */
    'api_model_detection' => [
        // Base class to check for ApiModel inheritance
        'base_class' => \MTechStack\LaravelApiModelClient\Models\ApiModel::class,
        
        // Cache resolved class checks for performance
        'cache_class_checks' => true,
        
        // TTL for cached class checks (in seconds)
        'cache_ttl' => 3600,
    ],

    /*
    |--------------------------------------------------------------------------
    | Fallback Behavior
    |--------------------------------------------------------------------------
    |
    | Configure how the system behaves when morphTo targets cannot be resolved
    | or when API requests fail.
    |
    */
    'fallback_behavior' => [
        // What to do when morph class cannot be resolved
        'unknown_morph_class' => 'null', // 'null', 'exception', 'log'
        
        // What to do when API request fails
        'api_request_failure' => 'null', // 'null', 'exception', 'log', 'retry'
        
        // Number of retry attempts for failed API requests
        'retry_attempts' => 3,
        
        // Delay between retry attempts (in milliseconds)
        'retry_delay' => 1000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Optimization
    |--------------------------------------------------------------------------
    |
    | Settings to optimize performance of morph relationships with API models.
    |
    */
    'performance' => [
        // Enable eager loading optimization
        'optimize_eager_loading' => true,
        
        // Batch size for eager loading API requests
        'eager_loading_batch_size' => 100,
        
        // Enable relationship caching
        'enable_relationship_caching' => true,
        
        // Cache TTL for relationships (in seconds)
        'relationship_cache_ttl' => 300,
    ],
];
