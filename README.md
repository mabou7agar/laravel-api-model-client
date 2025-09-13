# Laravel API Model Client

[![Latest Version on Packagist](https://img.shields.io/packagist/v/m-tech-stack/laravel-api-model-client.svg)](https://packagist.org/packages/m-tech-stack/laravel-api-model-client)
[![Total Downloads](https://img.shields.io/packagist/dt/m-tech-stack/laravel-api-model-client.svg)](https://packagist.org/packages/m-tech-stack/laravel-api-model-client)
[![License](https://img.shields.io/packagist/l/m-tech-stack/laravel-api-model-client.svg)](https://packagist.org/packages/m-tech-stack/laravel-api-model-client)
[![Laravel Version](https://img.shields.io/badge/Laravel-8.x%20%7C%209.x%20%7C%2010.x%20%7C%2011.x%20%7C%2012.x-orange.svg)](https://laravel.com)

A powerful Laravel package that enables Eloquent-like models to interact seamlessly with external APIs instead of a local database, including relationships, caching, error handling, and more.

## Table of Contents

- [Features](#features)
- [Installation](#installation)
- [Configuration](#configuration)
- [Basic Usage](#basic-usage)
  - [Creating an API Model](#creating-an-api-model)
  - [Using API Models](#using-api-models)
  - [API Model Lifecycle](#api-model-lifecycle)
- [API Relationships](#api-relationships)
  - [Available Relationships](#available-relationships)
  - [Defining Relationships](#defining-relationships)
  - [Working with Relationships](#working-with-relationships)
- [Query Builder](#query-builder)
- [Caching](#caching)
- [Authentication](#authentication)
- [Events](#events)
- [Middleware Pipeline](#middleware-pipeline)
- [Error Handling](#error-handling)
- [Advanced Usage](#advanced-usage)
  - [Hybrid Data Source Management](#hybrid-data-source-management)
  - [Custom Response Transformers](#custom-response-transformers)
  - [High-Performance Caching](#high-performance-caching)
  - [API Mocking](#api-mocking)
  - [Performance Optimization](#performance-optimization)
- [Troubleshooting](#troubleshooting)
- [Contributing](#contributing)
- [License](#license)

## Features

### 🚀 **Core Capabilities**
- **Eloquent-like API Models**: Work with external APIs using familiar Eloquent syntax with full Laravel integration
- **Hybrid Data Source Management**: Intelligent switching between database and API with 5 sophisticated modes (`api_only`, `db_only`, `hybrid`, `api_first`, `dual_sync`)
- **Advanced Multi-Layer Caching**: High-performance caching system with Redis support, API cache, and configurable TTL
- **Comprehensive Query Builder**: Chainable query methods with advanced filtering, sorting, pagination, and relationship loading
- **Full API Relationships**: Complete relationship support (`hasMany`, `belongsTo`, `morphTo`, `hasManyThrough`, etc.) with lazy loading

### 🔧 **Advanced Features**
- **Multi-Authentication Support**: Bearer tokens, Basic auth, API keys, and custom authentication strategies
- **Robust Error Handling**: Comprehensive exception handling with retry logic, fallback mechanisms, and detailed logging
- **Event-Driven Architecture**: Laravel event integration with custom API model events and lifecycle hooks
- **Extensible Middleware Pipeline**: Built-in middleware for authentication, rate limiting, caching, logging, transformation, and validation
- **Powerful Response Transformers**: Transform API responses with custom transformer classes and data mapping
- **Intelligent Database Synchronization**: Seamless sync between API and local database with conflict resolution and timestamp comparison

### 🎯 **Enterprise Features**
- **High-Performance Caching**: Redis-based caching with intelligent cache warming, invalidation, and distributed caching support
- **Lazy Relationship Loading**: Efficient relationship loading with automatic API calls and batch loading optimization
- **Batch Operations**: Bulk create, update, and delete operations with transaction support
- **Comprehensive Testing Support**: Testing utilities, mock factories, and API response mocking
- **Console Commands**: Artisan commands for cache management, API operations, and debugging
- **Developer Tools**: Generate models from OpenAPI/Swagger specs, debug API calls, and auto-generate documentation

## Installation

You can install the package via composer:

```bash
composer require m-tech-stack/laravel-api-model-client
```

After installing, publish the configuration file:

```bash
php artisan vendor:publish --provider="MTechStack\LaravelApiModelClient\MTechStack\LaravelApiModelClientServiceProvider" --tag="config"
```

## Configuration

The package uses multiple configuration files for different aspects:

### Main Configuration (`config/api_model.php`)

```php
return [
    'client' => [
        'base_url' => env('API_MODEL_RELATIONS_BASE_URL'),
        'timeout' => env('API_MODEL_RELATIONS_TIMEOUT', 30),
        'connect_timeout' => env('API_MODEL_RELATIONS_CONNECT_TIMEOUT', 10),
    ],
    
    'auth' => [
        'strategy' => env('API_MODEL_RELATIONS_AUTH_STRATEGY', 'bearer'),
        'credentials' => [
            'token' => env('API_MODEL_RELATIONS_AUTH_TOKEN'),
            'username' => env('API_MODEL_RELATIONS_AUTH_USERNAME'),
            'password' => env('API_MODEL_RELATIONS_AUTH_PASSWORD'),
            'api_key' => env('API_MODEL_RELATIONS_AUTH_API_KEY'),
            'header_name' => env('API_MODEL_RELATIONS_AUTH_HEADER_NAME', 'X-API-KEY'),
        ],
    ],
    
    'events' => [
        'enabled' => env('API_MODEL_RELATIONS_EVENTS_ENABLED', true),
    ],
    
    'debug' => env('API_MODEL_RELATIONS_DEBUG', false),
];
```

### Hybrid Data Source Configuration (`config/hybrid-data-source.php`)

```php
return [
    'global_mode' => env('API_MODEL_DATA_SOURCE_MODE', 'hybrid'),
    'models' => [
        'product' => [
            'data_source_mode' => 'api_first',
            'sync_enabled' => true,
            'cache_ttl' => 3600,
        ],
    ],
];
```

### High-Performance Cache Configuration (`config/high-performance-cache.php`)

```php
return [
    'enabled' => env('HIGH_PERFORMANCE_CACHE_ENABLED', true),
    'driver' => env('HIGH_PERFORMANCE_CACHE_DRIVER', 'redis'),
    'redis' => [
        'connection' => 'default',
        'prefix' => 'api_model_hp:',
        'serializer' => 'igbinary',
    ],
    'ttl' => [
        'default' => 3600,
        'models' => [
            'product' => 7200,
            'category' => 86400,
        ],
    ],
];
```

### API Cache Configuration (`config/api-cache.php`)

```php
return [
    'enabled' => env('API_CACHE_ENABLED', true),
    'default_ttl' => env('API_CACHE_DEFAULT_TTL', 3600),
    'store' => env('API_CACHE_STORE', 'redis'),
    'prefix' => env('API_CACHE_PREFIX', 'api_cache:'),
    'tags' => [
        'enabled' => true,
        'separator' => ':',
    ],
];
```

## Basic Usage

### Creating an API Model

Create a model that extends `ApiModel` (which includes all necessary traits automatically):

```php
<?php

namespace App\Models\Api;

use MTechStack\LaravelApiModelClient\Models\ApiModel;

class Product extends ApiModel
{
    /**
     * The API endpoint for this model.
     *
     * @var string
     */
    protected $apiEndpoint = 'products';
    
    /**
     * The database table for hybrid data source mode (optional).
     *
     * @var string
     */
    protected $table = 'products';
    
    /**
     * Data source mode for this model (optional - defaults to config).
     * Available modes: 'api_only', 'db_only', 'hybrid', 'api_first', 'dual_sync'
     *
     * @var string
     */
    protected $dataSourceMode = 'hybrid';
    
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'id',
        'name',
        'description',
        'price',
        'category_id',
    ];
    
    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'price' => 'float',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
    
    /**
     * Get the category that owns the product.
     */
    public function category()
    {
        return $this->belongsToFromApi(Category::class, 'category_id');
    }
    
    /**
     * Get the reviews for the product.
     */
    public function reviews()
    {
        return $this->hasManyFromApi(Review::class, 'product_id');
    }
}
```

#### Built-in Traits and Capabilities

The `ApiModel` class automatically includes these powerful traits:

- **`ApiModelAttributes`**: Enhanced attribute handling for API data
- **`ApiModelCaching`**: Intelligent caching with TTL and invalidation
- **`ApiModelErrorHandling`**: Comprehensive error handling and logging
- **`ApiModelEvents`**: Laravel event integration for API operations
- **`ApiModelInterfaceMethods`**: Core API interface methods
- **`ApiModelQueries`**: Advanced query builder for API requests
- **`HasApiRelationships`**: Full relationship support (hasMany, belongsTo, etc.)
- **`LazyLoadsApiRelationships`**: Efficient lazy loading of relationships
- **`SyncWithApi`**: Database synchronization methods (syncFromApi, syncToApi)
- **`HybridDataSource`**: Intelligent switching between API and database

> **⚠️ Important Note**: As of v1.0.14, the `SyncWithApi` trait is now built into `ApiModel`. You no longer need to manually add `use SyncWithApi;` to your models. The trait collision between `SyncWithApi::syncToApi` and `HybridDataSource::syncToApi` has been resolved with `HybridDataSource` taking precedence for better hybrid data source compatibility.

This means you get all these capabilities automatically without needing to manually add traits:

```php
// All of these work out of the box with ApiModel
$product = Product::find(1);                    // HybridDataSource
$products = Product::where('active', true)->get(); // ApiModelQueries
$category = $product->category;                  // HasApiRelationships + LazyLoading
$product->save();                               // ApiModelInterfaceMethods + Events
$product->syncFromApi();                        // SyncWithApi (now built-in)
// Automatic caching, error handling, and event firing
```

#### Migration from Previous Versions

If you're upgrading from a version prior to v1.0.14 and have models that manually include the `SyncWithApi` trait:

```php
// ❌ Old way (will cause trait collision)
class Product extends ApiModel
{
    use SyncWithApi; // Remove this line
    
    protected $apiEndpoint = 'products';
}

// ✅ New way (v1.0.14+)
class Product extends ApiModel
{
    // SyncWithApi is now included automatically
    protected $apiEndpoint = 'products';
}
```

### Using API Models

Use API models just like regular Eloquent models:

```php
// Find a product by ID
$product = Product::find(1);

// Get all products
$products = Product::all();

// Query products
$expensiveProducts = Product::where('price', '>', 100)->get();

// Use relationships
$category = $product->category;
$reviews = $product->reviews;

// Create a new product
$newProduct = Product::create([
    'name' => 'New Product',
    'description' => 'This is a new product',
    'price' => 99.99,
    'category_id' => 2,
]);

// Update a product
$product->update(['price' => 149.99]);

// Delete a product
$product->delete();
```

### API Model Lifecycle

Understanding the API model lifecycle helps you work with the package more effectively:

```php
// Creating a new model instance
$product = new Product();
$product->name = 'New Product';
$product->price = 99.99;

// Save to API - triggers API POST request
$product->save();

// At this point, the model has been saved to the API and has an ID

// Refresh from API - triggers API GET request
$product->refreshFromApi();

// Update model - triggers API PUT/PATCH request
$product->price = 149.99;
$product->save();

// Delete from API - triggers API DELETE request
$product->delete();
```

## API Relationships

### Available Relationships

The package supports the following relationship types:

- `hasManyFromApi`: One-to-many relationship
- `belongsToFromApi`: Many-to-one relationship
- `hasOneFromApi`: One-to-one relationship
- `belongsToManyFromApi`: Many-to-many relationship
- `hasManyThroughFromApi`: One-to-many relationship through an intermediate model
- `morphManyFromApi`: Polymorphic one-to-many relationship

### Defining Relationships

Define relationships in your models:

```php
// One-to-many relationship
public function reviews()
{
    return $this->hasManyFromApi(Review::class, 'product_id');
}

// Many-to-one relationship
public function category()
{
    return $this->belongsToFromApi(Category::class, 'category_id');
}

// One-to-one relationship
public function featuredImage()
{
    return $this->hasOneFromApi(Image::class, 'product_id');
}

// Many-to-many relationship
public function tags()
{
    return $this->belongsToManyFromApi(
        Tag::class,
        'product_tags', // pivot endpoint
        'product_id',
        'tag_id'
    );
}

// One-to-many relationship through an intermediate model
public function comments()
{
    return $this->hasManyThroughFromApi(
        Comment::class,  // Final model we want to access
        Post::class,     // Intermediate model
        'user_id',       // Foreign key on intermediate model
        'post_id'        // Foreign key on final model
    );
}

// Polymorphic one-to-many relationship
public function comments()
{
    return $this->morphManyFromApi(
        Comment::class,
        'commentable'    // The name of the relationship
    );
}

// In your Comment model
public function commentable()
{
    return $this->morphToFromApi();
}
```

### Working with Relationships

Here are examples of working with different relationship types:

```php
// One-to-many relationship
$product = Product::find(1);
$reviews = $product->reviews; // Get all reviews for this product

// Create a new related model
$newReview = $product->reviews()->create([
    'rating' => 5,
    'comment' => 'Great product!'
]);

// Many-to-one relationship
$review = Review::find(1);
$product = $review->product; // Get the product for this review

// One-to-one relationship
$product = Product::find(1);
$image = $product->featuredImage; // Get the featured image

// Update a related model
$product->featuredImage->update([
    'url' => 'https://example.com/new-image.jpg'
]);

// Many-to-many relationship
$product = Product::find(1);
$tags = $product->tags; // Get all tags for this product

// Attach a tag to a product
$product->tags()->attach(5);

// Detach a tag from a product
$product->tags()->detach(3);

// Sync tags (remove existing and add new ones)
$product->tags()->sync([1, 2, 5]);

// One-to-many through relationship
$user = User::find(1);
$comments = $user->comments; // Get all comments on the user's posts

// Polymorphic relationships
$product = Product::find(1);
$comments = $product->comments; // Get comments for this product

$post = Post::find(1);
$comments = $post->comments; // Get comments for this post
```

## Query Builder

Use the query builder to filter API results:

```php
// Basic where clauses
$products = Product::where('category_id', 1)
    ->where('price', '>', 50)
    ->get();

// Where with array of conditions
$products = Product::where([
    ['status', '=', 'active'],
    ['price', '>', 100]
])->get();

// Where with OR condition
$products = Product::where('category_id', 1)
    ->orWhere('featured', true)
    ->get();

// Where with nested conditions
$products = Product::where('category_id', 1)
    ->where(function($query) {
        $query->where('price', '>', 100)
              ->orWhere('featured', true);
    })
    ->get();

// Order by
$products = Product::orderBy('price', 'desc')->get();

// Multiple order by
$products = Product::orderBy('category_id')
    ->orderBy('price', 'desc')
    ->get();

// Limit and offset
$products = Product::limit(10)->offset(20)->get();

// Pagination
$products = Product::paginate(15);
$products = Product::where('category_id', 1)->paginate(15);

// Custom query parameters
$products = Product::withQueryParam('include', 'category,tags')
    ->withQueryParam('fields', 'id,name,price')
    ->get();

// Custom macros
$products = Product::whereContains('name', 'phone')->get();
```

## Caching

API responses are automatically cached based on your configuration. You can customize caching behavior:

```php
// Set a custom cache TTL for a specific model
class Product extends ApiModel
{
    use SyncWithApi;
    
    protected $apiEndpoint = 'products';
    protected $cacheTtl = 1800; // 30 minutes
}

// Disable caching for a query
$products = Product::withoutCache()->get();

// Refresh cache for a model
$product = Product::find(1);
$product->refreshFromApi();

// Clear cache for a model
Product::clearCache();

// Clear cache for a specific model instance
$product = Product::find(1);
$product->clearCache();

// Clear cache for a specific query
Product::where('category_id', 1)->clearCache();

// Set custom cache key
$products = Product::withCacheKey('featured_products')
    ->where('featured', true)
    ->get();
```

## Authentication

The package supports multiple authentication strategies:

### Bearer Token

```php
// In your .env file
API_MODEL_RELATIONS_AUTH_STRATEGY=bearer
API_MODEL_RELATIONS_AUTH_TOKEN=your-token-here

// Or set dynamically in your code
ApiClient::setAuthStrategy('bearer');
ApiClient::setAuthToken('your-dynamic-token');
```

### Basic Auth

```php
// In your .env file
API_MODEL_RELATIONS_AUTH_STRATEGY=basic
API_MODEL_RELATIONS_AUTH_USERNAME=your-username
API_MODEL_RELATIONS_AUTH_PASSWORD=your-password

// Or set dynamically in your code
ApiClient::setAuthStrategy('basic');
ApiClient::setBasicAuth('username', 'password');
```

### API Key

```php
// In your .env file
API_MODEL_RELATIONS_AUTH_STRATEGY=api_key
API_MODEL_RELATIONS_AUTH_API_KEY=your-api-key
API_MODEL_RELATIONS_AUTH_HEADER_NAME=X-API-KEY

// Or set dynamically in your code
ApiClient::setAuthStrategy('api_key');
ApiClient::setApiKey('your-api-key', 'X-API-KEY');
```

### Custom Authentication

You can implement custom authentication strategies:

```php
use MTechStack\LaravelApiModelClient\Auth\AuthStrategyInterface;

class CustomAuthStrategy implements AuthStrategyInterface
{
    public function apply($request)
    {
        // Apply your custom authentication to the request
        return $request->withHeader('X-Custom-Auth', 'custom-value');
    }
}

// Register your custom strategy
app()->bind('api-model-relations.auth.custom', function() {
    return new CustomAuthStrategy();
});

// Use your custom strategy
ApiClient::setAuthStrategy('custom');
```

## Events

The package dispatches events during the API request lifecycle:

```php
use MTechStack\LaravelApiModelClient\Events\ApiRequestEvent;
use MTechStack\LaravelApiModelClient\Events\ApiResponseEvent;
use MTechStack\LaravelApiModelClient\Events\ApiExceptionEvent;
use MTechStack\LaravelApiModelClient\Events\ModelCreatedEvent;
use MTechStack\LaravelApiModelClient\Events\ModelUpdatedEvent;
use MTechStack\LaravelApiModelClient\Events\ModelDeletedEvent;
use Illuminate\Support\Facades\Event;

// Listen for API request events
Event::listen(ApiRequestEvent::class, function (ApiRequestEvent $event) {
    $method = $event->method;
    $endpoint = $event->endpoint;
    $options = $event->options;
    
    // Do something before the API request
    logger()->info("API Request: {$method} {$endpoint}");
});

// Listen for API response events
Event::listen(ApiResponseEvent::class, function (ApiResponseEvent $event) {
    $response = $event->response;
    $statusCode = $event->statusCode;
    
    // Do something with the API response
    logger()->info("API Response: {$statusCode}");
});

// Listen for API exception events
Event::listen(ApiExceptionEvent::class, function (ApiExceptionEvent $event) {
    $exception = $event->exception;
    
    // Handle API exceptions
    logger()->error("API Exception: {$exception->getMessage()}");
});

// Listen for model lifecycle events
Event::listen(ModelCreatedEvent::class, function (ModelCreatedEvent $event) {
    $model = $event->model;
    logger()->info("Model created: " . get_class($model) . " #{$model->id}");
});
```

## Middleware Pipeline

The package uses a middleware pipeline to process API requests. You can add custom middleware:

```php
use MTechStack\LaravelApiModelClient\Middleware\AbstractApiMiddleware;

class CustomMiddleware extends AbstractApiMiddleware
{
    public function __construct()
    {
        $this->priority = 50; // Set middleware priority
    }
    
    public function handle($request, \Closure $next)
    {
        // Modify the request
        $request = $request->withHeader('X-Custom-Header', 'custom-value');
        
        // Call the next middleware
        $response = $next($request);
        
        // Modify the response
        return $response->withHeader('X-Response-Time', microtime(true) - LARAVEL_START);
    }
}

// Register your middleware
app()->bind('api-model-relations.middleware.custom', function() {
    return new CustomMiddleware();
});

// Add your middleware to the pipeline
config(['api-model-relations.middleware' => array_merge(
    config('api-model-relations.middleware', []),
    ['custom']
)]);
```

## Error Handling

The package provides comprehensive error handling for API requests:

```php
// Try to find a model that doesn't exist
try {
    $product = Product::findOrFail(999);
} catch (\MTechStack\LaravelApiModelClient\Exceptions\ModelNotFoundException $e) {
    // Handle not found exception
    logger()->error("Product not found: {$e->getMessage()}");
}

// Try to create a model with validation errors
try {
    $product = Product::create([
        'name' => '',  // Required field
        'price' => 'invalid'  // Should be a number
    ]);
} catch (\MTechStack\LaravelApiModelClient\Exceptions\ValidationException $e) {
    // Get validation errors
    $errors = $e->getErrors();
    logger()->error("Validation errors: " . json_encode($errors));
}

// Handle API connection errors
try {
    $products = Product::all();
} catch (\MTechStack\LaravelApiModelClient\Exceptions\ApiConnectionException $e) {
    // Handle connection error
    logger()->error("API connection error: {$e->getMessage()}");
}

// Get the last API response
$lastResponse = ApiClient::getLastResponse();
$statusCode = $lastResponse->getStatusCode();
$body = $lastResponse->getBody()->getContents();

// Get the last API request
$lastRequest = ApiClient::getLastRequest();
$method = $lastRequest->getMethod();
$uri = $lastRequest->getUri();
```

## Advanced Usage

### Hybrid Data Source Management

The `ApiModel` class includes the `HybridDataSource` trait automatically, providing sophisticated hybrid data management between API and database:

```php
use MTechStack\LaravelApiModelClient\Models\ApiModel;

class Product extends ApiModel
{
    protected $apiEndpoint = 'products';
    protected $table = 'products';
    
    // Set the data source mode (optional - defaults to config)
    protected $dataSourceMode = 'hybrid';
}

// Usage examples for different modes:

// 1. Hybrid Mode (database first, API fallback)
$product = Product::find(1); // Checks database first, then API

// 2. API First Mode (API first, sync to database)
$product = new Product();
$product->dataSourceMode = 'api_first';
$product = $product->find(1); // Checks API first, syncs to database

// 3. Dual Sync Mode (keep both in sync)
$product = new Product();
$product->dataSourceMode = 'dual_sync';
$product->name = 'Updated Product';
$product->save(); // Saves to both API and database
```

#### Available Data Source Modes

The `HybridDataSource` trait supports five intelligent modes:

- **`api_only`**: All operations use API exclusively
- **`db_only`**: All operations use database exclusively  
- **`hybrid`**: Check database first, fallback to API
- **`api_first`**: Check API first, sync to database
- **`dual_sync`**: Keep both database and API in sync

#### Configuration

Configure hybrid data source modes using the dedicated configuration file:

```php
// config/hybrid-data-source.php
return [
    'global_mode' => env('API_MODEL_DATA_SOURCE_MODE', 'hybrid'),
    
    'models' => [
        'product' => [
            'data_source_mode' => env('PRODUCT_DATA_SOURCE_MODE', 'api_first'),
            'sync_enabled' => true,
            'cache_ttl' => 3600, // 1 hour
            'auto_sync_threshold' => 300, // 5 minutes
        ],
        'category' => [
            'data_source_mode' => env('CATEGORY_DATA_SOURCE_MODE', 'hybrid'),
            'sync_enabled' => true,
            'conflict_resolution' => 'timestamp', // 'timestamp', 'api_wins', 'db_wins'
        ],
    ],
    
    'sync_options' => [
        'batch_size' => 100,
        'retry_attempts' => 3,
        'retry_delay' => 1000, // milliseconds
    ],
];
```

#### Usage Examples

```php
// Basic usage with hybrid mode
$product = Product::find(1); // Checks database first, then API if not found

// Switch modes dynamically
$product = new Product();
$product->setDataSourceMode('api_first');
$allProducts = $product->all(); // Gets from API first, syncs to database

// Dual sync mode - keeps both sources in sync
$product = Product::find(1);
$product->setDataSourceMode('dual_sync');
$product->name = 'Updated Product';
$product->save(); // Saves to both API and database automatically

// Advanced conflict resolution in dual_sync mode
$product = Product::find(1);
// Automatically compares timestamps and chooses most recent data
// Syncs both sources to maintain consistency
```

### High-Performance Caching

The package includes a sophisticated multi-layer caching system with Redis support:

```php
// config/high-performance-cache.php
return [
    'enabled' => env('HIGH_PERFORMANCE_CACHE_ENABLED', true),
    'driver' => env('HIGH_PERFORMANCE_CACHE_DRIVER', 'redis'),
    
    'redis' => [
        'connection' => env('HIGH_PERFORMANCE_CACHE_REDIS_CONNECTION', 'default'),
        'prefix' => env('HIGH_PERFORMANCE_CACHE_PREFIX', 'api_model_hp:'),
        'serializer' => 'igbinary', // 'php', 'igbinary', 'json'
    ],
    
    'ttl' => [
        'default' => env('HIGH_PERFORMANCE_CACHE_TTL', 3600),
        'models' => [
            'product' => 7200, // 2 hours
            'category' => 86400, // 24 hours
        ],
    ],
    
    'warming' => [
        'enabled' => env('CACHE_WARMING_ENABLED', true),
        'batch_size' => 100,
        'concurrent_requests' => 5,
    ],
    
    'invalidation' => [
        'strategy' => 'tag_based', // 'tag_based', 'key_pattern', 'manual'
        'auto_invalidate_on_update' => true,
    ],
];

// Usage in models
class Product extends ApiModel
{
    protected $cacheProfile = 'high_performance';
    protected $cacheTags = ['products', 'catalog'];
    protected $cacheTtl = 7200; // Override default TTL
    
    // Enable cache warming for this model
    protected $enableCacheWarming = true;
}

// Advanced caching operations
$product = Product::withCache('products:featured')
    ->where('featured', true)
    ->remember(3600) // Cache for 1 hour
    ->get();

// Cache warming
Product::warmCache(['featured' => true], 100); // Warm cache for featured products

// Cache invalidation
Product::invalidateCache(['products', 'catalog']); // Invalidate by tags
Product::find(1)->invalidateModelCache(); // Invalidate specific model cache
```

### Custom Response Transformers

You can transform API responses before they're converted to models:

```php
use MTechStack\LaravelApiModelClient\Transformers\AbstractResponseTransformer;

class CustomProductTransformer extends AbstractResponseTransformer
{
    public function transform($response)
    {
        $data = json_decode($response->getBody()->getContents(), true);
        
        // Transform the data
        if (isset($data['products'])) {
            return $data['products'];
        }
        
        return $data;
    }
}

// Register your transformer
app()->bind('api-model-relations.transformers.product', function() {
    return new CustomProductTransformer();
});

// Use your transformer in your model
class Product extends ApiModel
{
    protected $apiEndpoint = 'products';
    protected $responseTransformer = 'product';
    
    // ApiModel already includes all necessary traits automatically
    // No need to manually add SyncWithApi or other traits
}
```

### API Mocking

For testing, you can mock API responses:

```php
use MTechStack\LaravelApiModelClient\Testing\MocksApiResponses;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use MocksApiResponses;
    
    public function testGetProducts()
    {
        // Mock a response for GET /products
        $this->mockApiResponse('GET', 'products', [
            'data' => [
                ['id' => 1, 'name' => 'Product 1', 'price' => 99.99],
                ['id' => 2, 'name' => 'Product 2', 'price' => 149.99],
            ]
        ]);
        
        // Now when Product::all() is called, it will use the mocked response
        $products = Product::all();
        
        $this->assertCount(2, $products);
        $this->assertEquals('Product 1', $products[0]->name);
    }
    
    public function testCreateProduct()
    {
        // Mock a response for POST /products
        $this->mockApiResponse('POST', 'products', [
            'id' => 3,
            'name' => 'New Product',
            'price' => 199.99
        ]);
        
        $product = Product::create([
            'name' => 'New Product',
            'price' => 199.99
        ]);
        
        $this->assertEquals(3, $product->id);
        $this->assertEquals('New Product', $product->name);
    }
}
```

### Performance Optimization

Tips for optimizing performance:

```php
// Eager load relationships to reduce API calls
$products = Product::with('category', 'reviews')->get();

// Select only the fields you need
$products = Product::select(['id', 'name', 'price'])->get();

// Use pagination for large datasets
$products = Product::paginate(20);

// Use caching effectively
$products = Product::withCacheTtl(3600)->get(); // Cache for 1 hour

// Batch operations when possible
$products = Product::whereIn('id', [1, 2, 3, 4, 5])->get();

// Use custom endpoints for specific operations
$featuredProducts = Product::withEndpoint('products/featured')->get();
```

## Troubleshooting

Common issues and solutions:

### API Connection Issues

```php
// Enable debug mode to see detailed request/response information
config(['api-model-relations.debug' => true]);

// Check the last request and response
$lastRequest = ApiClient::getLastRequest();
$lastResponse = ApiClient::getLastResponse();

// Log all API requests and responses
Event::listen(ApiRequestEvent::class, function ($event) {
    logger()->debug('API Request', [
        'method' => $event->method,
        'endpoint' => $event->endpoint,
        'options' => $event->options
    ]);
});

Event::listen(ApiResponseEvent::class, function ($event) {
    logger()->debug('API Response', [
        'status' => $event->statusCode,
        'body' => (string) $event->response->getBody()
    ]);
});
```

### Caching Issues

```php
// Clear all cache
\Illuminate\Support\Facades\Cache::store(config('api-model-relations.cache.store'))->flush();

// Disable caching temporarily
config(['api-model-relations.cache.enabled' => false]);

// Debug cache keys
logger()->debug('Cache key: ' . Product::where('id', 1)->getCacheKey());
```

## Contributing

Contributions are welcome! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
