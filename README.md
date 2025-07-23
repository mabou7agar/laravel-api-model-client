# Laravel API Model Relations

[![Latest Version on Packagist](https://img.shields.io/packagist/v/api-model-relations/laravel-api-model-relations.svg)](https://packagist.org/packages/api-model-relations/laravel-api-model-relations)
[![Total Downloads](https://img.shields.io/packagist/dt/api-model-relations/laravel-api-model-relations.svg)](https://packagist.org/packages/api-model-relations/laravel-api-model-relations)
[![License](https://img.shields.io/packagist/l/api-model-relations/laravel-api-model-relations.svg)](https://packagist.org/packages/api-model-relations/laravel-api-model-relations)
[![Laravel Version](https://img.shields.io/badge/Laravel-9.x%20%7C%2010.x%20%7C%2011.x-orange.svg)](https://laravel.com)

A powerful Laravel package that enables Eloquent-like models to interact seamlessly with external APIs instead of a local database, including relationships, caching, error handling, and more.

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
composer require api-model-relations/laravel-api-model-relations
```

After installing, publish the configuration file:

```bash
php artisan vendor:publish --provider="ApiModelRelations\ApiModelRelationsServiceProvider"
```

## Configuration

The package can be configured via the `config/api-model-relations.php` file:

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

## Query Builder

Use the query builder to filter API results:

```php
// Basic where clauses
$products = Product::where('category_id', 1)
    ->where('price', '>', 50)
    ->get();

// Order by
$products = Product::orderBy('price', 'desc')->get();

// Limit and offset
$products = Product::limit(10)->offset(20)->get();

// Pagination
$products = Product::paginate(15);

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
```

## Authentication

The package supports multiple authentication strategies:

### Bearer Token

```php
// In your .env file
API_MODEL_RELATIONS_AUTH_STRATEGY=bearer
API_MODEL_RELATIONS_AUTH_TOKEN=your-token-here
```

### Basic Auth

```php
// In your .env file
API_MODEL_RELATIONS_AUTH_STRATEGY=basic
API_MODEL_RELATIONS_AUTH_USERNAME=your-username
API_MODEL_RELATIONS_AUTH_PASSWORD=your-password
```

### API Key

```php
// In your .env file
API_MODEL_RELATIONS_AUTH_STRATEGY=api_key
API_MODEL_RELATIONS_AUTH_API_KEY=your-api-key
API_MODEL_RELATIONS_AUTH_HEADER_NAME=X-API-KEY
```

## Events

The package dispatches events during the API request lifecycle:

```php
use ApiModelRelations\Events\ApiRequestEvent;
use ApiModelRelations\Events\ApiResponseEvent;
use ApiModelRelations\Events\ApiExceptionEvent;
use Illuminate\Support\Facades\Event;

// Listen for API request events
Event::listen(ApiRequestEvent::class, function (ApiRequestEvent $event) {
    $method = $event->method;
    $endpoint = $event->endpoint;
    $options = $event->options;
    
    // Do something before the API request
});

// Listen for API response events
Event::listen(ApiResponseEvent::class, function (ApiResponseEvent $event) {
    $response = $event->response;
    $statusCode = $event->statusCode;
    
    // Do something with the API response
});

// Listen for API exception events
Event::listen(ApiExceptionEvent::class, function (ApiExceptionEvent $event) {
    $exception = $event->exception;
    
    // Handle API exceptions
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
    
    public function handle(array $request, callable $next): array
    {
        // Modify the request
        $request['options']['headers']['Custom-Header'] = 'Value';
        
        // Call the next middleware
        $response = $next($request);
        
        // Modify the response
        $response['data']['custom_field'] = 'value';
        
        return $response;
    }
}

// Register the middleware in a service provider
$this->app->make('api-pipeline')->pipe(new CustomMiddleware());
```

## Local Database Integration

You can merge API data with local database records:

```php
class Product extends ApiModel
{
    use SyncWithApi;
    
    protected $apiEndpoint = 'products';
    protected $mergeLocalData = true; // Enable merging with local DB
    protected $table = 'products'; // Local table name
}
```

## Developer Tools

### Generate Models from OpenAPI/Swagger

```bash
php artisan api-model:generate-from-swagger --url=https://example.com/api-docs.json --namespace=App\\Models\\Api
```

### Generate API Model Documentation

```bash
php artisan api-model:docs --directory=app/Models/Api --namespace=App\\Models\\Api
```

### Debug API Calls

Enable debugging in your `.env` file:

```
API_MODEL_RELATIONS_DEBUG=true
```

Then visit `/api-model-relations/debug` in your browser to see the debug dashboard.

## Testing

The package includes tools for testing API models:

```php
use ApiModelRelations\Testing\MocksApiResponses;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use MocksApiResponses;
    
    public function test_can_fetch_products()
    {
        $this->mockApiResponse('products', 'GET', [
            'data' => [
                ['id' => 1, 'name' => 'Product 1'],
                ['id' => 2, 'name' => 'Product 2'],
            ]
        ]);
        
        $products = Product::all();
        
        $this->assertCount(2, $products);
        $this->assertEquals('Product 1', $products[0]->name);
    }
}
```

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
