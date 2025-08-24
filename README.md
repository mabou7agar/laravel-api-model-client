# Laravel API Model Client

[![Latest Version on Packagist](https://img.shields.io/packagist/v/m-tech-stack/laravel-api-model-client.svg)](https://packagist.org/packages/m-tech-stack/laravel-api-model-client)
[![Total Downloads](https://img.shields.io/packagist/dt/m-tech-stack/laravel-api-model-client.svg)](https://packagist.org/packages/m-tech-stack/laravel-api-model-client)
[![License](https://img.shields.io/packagist/l/m-tech-stack/laravel-api-model-client.svg)](https://packagist.org/packages/m-tech-stack/laravel-api-model-client)
[![Laravel Version](https://img.shields.io/badge/Laravel-9.x%20%7C%2010.x%20%7C%2011.x-orange.svg)](https://laravel.com)

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
  - [Local Database Integration](#local-database-integration)
  - [Custom Response Transformers](#custom-response-transformers)
  - [API Mocking](#api-mocking)
  - [Performance Optimization](#performance-optimization)
- [Troubleshooting](#troubleshooting)
- [Contributing](#contributing)
- [License](#license)

## Features

- **Eloquent-like API Models**: Use familiar Laravel Eloquent syntax with external APIs
- **API Relationships**: Define and use relationships between API resources (`hasMany`, `belongsTo`, etc.)
- **Smart Caching**: Cache API responses with configurable TTL and strategies
- **Query Builder**: Use Eloquent-like query builder methods for API queries
- **Pagination Support**: Handle paginated API responses like Eloquent collections
- **Authentication Strategies**: Support for multiple API authentication methods (Bearer, Basic, API Key)
- **Error Handling**: Comprehensive error handling and logging for API failures
- **Local DB Integration**: Optionally merge API data with local database records
- **Middleware Pipeline**: Process API requests through a configurable middleware pipeline
- **Event System**: Hook into API request lifecycle with Laravel events
- **Response Transformers**: Transform API responses into your desired format
- **API Mocking**: Mock API responses for testing
- **Developer Tools**: Generate models from OpenAPI/Swagger specs, debug API calls, and generate documentation
- **Lazy Loading**: Lazy load API relationships to improve performance
- **Modularized Traits**: Use individual traits to add API model capabilities to your own models
- **Complex Relationships**: Support for hasManyThrough, morphMany, and other complex relationships

## Installation

You can install the package via composer:

```bash
composer require m-tech-stack/laravel-api-model-client
```

After installing, publish the configuration file:

```bash
php artisan vendor:publish --provider="MTechStack\LaravelApiModelClient\ApiModelRelationsServiceProvider" --tag="config"
```

## Configuration

The package can be configured via the `config/api-model-client.php` file:

```php
return [
    'client' => [
        'base_url' => env('API_MODEL_RELATIONS_BASE_URL'),
        'timeout' => env('API_MODEL_RELATIONS_TIMEOUT', 30),
        'connect_timeout' => env('API_MODEL_RELATIONS_CONNECT_TIMEOUT', 10),
    ],
    
    'auth' => [
        'strategy' => env('API_MODEL_RELATIONS_AUTH_STRATEGY', 'bearer'), // 'bearer', 'basic', 'api_key'
        'credentials' => [
            'token' => env('API_MODEL_RELATIONS_AUTH_TOKEN'),
            'username' => env('API_MODEL_RELATIONS_AUTH_USERNAME'),
            'password' => env('API_MODEL_RELATIONS_AUTH_PASSWORD'),
            'api_key' => env('API_MODEL_RELATIONS_AUTH_API_KEY'),
            'header_name' => env('API_MODEL_RELATIONS_AUTH_HEADER_NAME', 'X-API-KEY'),
            'use_query_param' => env('API_MODEL_RELATIONS_AUTH_USE_QUERY', false),
            'query_param_name' => env('API_MODEL_RELATIONS_AUTH_QUERY_NAME', 'api_key'),
        ],
    ],
    
    'cache' => [
        'enabled' => env('API_MODEL_RELATIONS_CACHE_ENABLED', true),
        'ttl' => env('API_MODEL_RELATIONS_CACHE_TTL', 3600), // seconds
        'store' => env('API_MODEL_RELATIONS_CACHE_STORE', 'file'),
        'prefix' => env('API_MODEL_RELATIONS_CACHE_PREFIX', 'api_model_'),
    ],
    
    'error_handling' => [
        'log_requests' => env('API_MODEL_RELATIONS_LOG_REQUESTS', true),
        'log_responses' => env('API_MODEL_RELATIONS_LOG_RESPONSES', true),
        'log_channel' => env('API_MODEL_RELATIONS_LOG_CHANNEL', 'stack'),
    ],
    
    'rate_limiting' => [
        'enabled' => env('API_MODEL_RELATIONS_RATE_LIMIT_ENABLED', true),
        'max_attempts' => env('API_MODEL_RELATIONS_RATE_LIMIT_MAX', 60),
        'decay_minutes' => env('API_MODEL_RELATIONS_RATE_LIMIT_DECAY', 1),
    ],
    
    'debug' => env('API_MODEL_RELATIONS_DEBUG', false),
    
    'events' => [
        'enabled' => env('API_MODEL_RELATIONS_EVENTS_ENABLED', true),
    ],
    
    'models' => [
        'merge_local' => env('API_MODEL_RELATIONS_MERGE_LOCAL', false),
        'cache_ttl' => env('API_MODEL_RELATIONS_MODEL_CACHE_TTL', 3600),
    ],
];
```

## Basic Usage

### Creating an API Model

Create a model that extends `ApiModel` and use the `SyncWithApi` trait:

```php
<?php

namespace App\Models\Api;

use ApiModelRelations\Models\ApiModel;
use ApiModelRelations\Traits\SyncWithApi;

class Product extends ApiModel
{
    use SyncWithApi;

    /**
     * The API endpoint for this model.
     *
     * @var string
     */
    protected $apiEndpoint = 'products';
    
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
use ApiModelRelations\Auth\AuthStrategyInterface;

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
use ApiModelRelations\Events\ApiRequestEvent;
use ApiModelRelations\Events\ApiResponseEvent;
use ApiModelRelations\Events\ApiExceptionEvent;
use ApiModelRelations\Events\ModelCreatedEvent;
use ApiModelRelations\Events\ModelUpdatedEvent;
use ApiModelRelations\Events\ModelDeletedEvent;
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
use ApiModelRelations\Middleware\AbstractApiMiddleware;

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
} catch (\ApiModelRelations\Exceptions\ModelNotFoundException $e) {
    // Handle not found exception
    logger()->error("Product not found: {$e->getMessage()}");
}

// Try to create a model with validation errors
try {
    $product = Product::create([
        'name' => '',  // Required field
        'price' => 'invalid'  // Should be a number
    ]);
} catch (\ApiModelRelations\Exceptions\ValidationException $e) {
    // Get validation errors
    $errors = $e->getErrors();
    logger()->error("Validation errors: " . json_encode($errors));
}

// Handle API connection errors
try {
    $products = Product::all();
} catch (\ApiModelRelations\Exceptions\ApiConnectionException $e) {
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

### Local Database Integration

You can integrate API models with local database records:

```php
use ApiModelRelations\Traits\SyncWithApi;
use ApiModelRelations\Traits\MergesWithDatabase;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use SyncWithApi, MergesWithDatabase;
    
    protected $apiEndpoint = 'products';
    protected $table = 'products';
    
    // Specify which attributes should be stored only in the database
    protected $dbOnly = ['local_stock', 'last_checked_at'];
    
    // Specify which attributes should be sent to the API
    protected $apiOnly = ['name', 'description', 'price'];
    
    // Override the shouldMergeWithDatabase method to control when to merge
    public function shouldMergeWithDatabase()
    {
        return true; // Always merge with database
    }
}

// Usage
$product = Product::find(1); // Gets from API and merges with local DB
$product->local_stock = 10; // This will only be saved to the database
$product->price = 149.99; // This will be saved to both API and database
$product->save(); // Saves to both API and database in a transaction
```

### Custom Response Transformers

You can transform API responses before they're converted to models:

```php
use ApiModelRelations\Transformers\AbstractResponseTransformer;

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
    use SyncWithApi;
    
    protected $apiEndpoint = 'products';
    protected $responseTransformer = 'product';
}
```

### API Mocking

For testing, you can mock API responses:

```php
use ApiModelRelations\Testing\MocksApiResponses;
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
