# OpenAPI Integration Guide

Welcome to the comprehensive guide for using OpenAPI integration with the Laravel API Model Client package. This guide will walk you through all the features, from basic setup to advanced usage patterns.

## Table of Contents

1. [Getting Started](#getting-started)
2. [Schema Configuration](#schema-configuration)
3. [Model Generation](#model-generation)
4. [Advanced Query Building](#advanced-query-building)
5. [Relationship Handling](#relationship-handling)
6. [Performance Optimization](#performance-optimization)
7. [Real-World Examples](#real-world-examples)
8. [Migration Guide](#migration-guide)
9. [Best Practices](#best-practices)
10. [Troubleshooting](#troubleshooting)

## Getting Started

### Installation

First, install the package via Composer:

```bash
composer require m-tech-stack/laravel-api-model-client
```

### Basic Setup

1. **Publish Configuration**

```bash
php artisan vendor:publish --provider="MTechStack\LaravelApiModelClient\ApiModelRelationsServiceProvider" --tag="config"
```

2. **Configure OpenAPI Schema**

Add your OpenAPI schema configuration to `config/api-client.php`:

```php
<?php

return [
    'schemas' => [
        'primary' => [
            'source' => env('API_CLIENT_PRIMARY_SCHEMA', 'https://api.example.com/openapi.json'),
            'base_url' => env('API_CLIENT_PRIMARY_BASE_URL', 'https://api.example.com'),
            'authentication' => [
                'type' => 'bearer',
                'token' => env('API_CLIENT_PRIMARY_TOKEN'),
            ],
            'validation' => [
                'strictness' => 'moderate', // strict, moderate, lenient
            ],
            'caching' => [
                'enabled' => true,
                'ttl' => 3600,
            ],
        ],
    ],
    
    'default_schema' => 'primary',
    
    'openapi' => [
        'cache_enabled' => true,
        'cache_ttl' => 3600,
        'remote_timeout' => 30,
        'max_file_size' => 10485760, // 10MB
    ],
];
```

3. **Environment Variables**

Add to your `.env` file:

```env
API_CLIENT_PRIMARY_SCHEMA=https://petstore.swagger.io/v2/swagger.json
API_CLIENT_PRIMARY_BASE_URL=https://petstore.swagger.io/v2
API_CLIENT_PRIMARY_TOKEN=your-api-token-here
```

### Your First OpenAPI Model

Create a model that uses OpenAPI schema:

```php
<?php

namespace App\Models\Api;

use MTechStack\LaravelApiModelClient\Models\ApiModel;
use MTechStack\LaravelApiModelClient\Traits\HasOpenApiSchema;

class Pet extends ApiModel
{
    use HasOpenApiSchema;

    protected string $openApiSchemaSource = 'primary'; // References config schema
    protected string $primaryKey = 'id';
    protected string $endpoint = '/pets';

    // Optional: Override auto-generated fillable and casts
    // These will be auto-generated from OpenAPI schema if not specified
    protected $fillable = ['name', 'status', 'category_id'];
    protected $casts = [
        'id' => 'integer',
        'category_id' => 'integer',
        'status' => 'string',
    ];
}
```

### Basic Usage

```php
// Create a new pet
$pet = Pet::create([
    'name' => 'Fluffy',
    'status' => 'available',
    'category_id' => 1
]);

// Find pets with OpenAPI validation
$availablePets = Pet::whereOpenApi('status', 'available')->get();

// Update with automatic validation
$pet->update(['status' => 'sold']);

// Delete
$pet->delete();
```

## Schema Configuration

### Multiple Schema Support

You can configure multiple OpenAPI schemas for different APIs:

```php
'schemas' => [
    'petstore' => [
        'source' => 'https://petstore.swagger.io/v2/swagger.json',
        'base_url' => 'https://petstore.swagger.io/v2',
        'authentication' => ['type' => 'api_key', 'key' => 'api_key', 'value' => env('PETSTORE_API_KEY')],
    ],
    'ecommerce' => [
        'source' => storage_path('api-schemas/ecommerce-openapi.json'),
        'base_url' => 'https://shop.example.com/api',
        'authentication' => ['type' => 'bearer', 'token' => env('ECOMMERCE_TOKEN')],
    ],
    'crm' => [
        'source' => 'https://crm.example.com/api-docs.yaml',
        'base_url' => 'https://crm.example.com/api/v1',
        'authentication' => ['type' => 'basic', 'username' => env('CRM_USER'), 'password' => env('CRM_PASS')],
    ],
],
```

### Schema Validation Strictness

Configure how strictly the package validates API parameters:

```php
'validation' => [
    'strictness' => 'strict', // strict, moderate, lenient
    'auto_cast' => true,
    'allow_unknown_properties' => false,
    'validate_formats' => true,
],
```

**Strictness Levels:**

- **Strict**: All OpenAPI rules enforced, fails on unknown properties
- **Moderate**: Flexible validation with warnings for non-critical issues  
- **Lenient**: Minimal validation, mostly warnings, maximum compatibility

### Caching Configuration

```php
'caching' => [
    'enabled' => true,
    'ttl' => 3600, // 1 hour
    'store' => 'redis', // default, redis, file
    'tags' => ['openapi', 'api-schemas'],
],
```

## Model Generation

### Automatic Model Generation

Generate models automatically from OpenAPI schemas:

```bash
# Generate all models from primary schema
php artisan api-client:generate-models

# Generate from specific schema
php artisan api-client:generate-models --schema=ecommerce

# Generate with custom namespace
php artisan api-client:generate-models --namespace="App\\Models\\Ecommerce"

# Generate with factories
php artisan api-client:generate-models --factories

# Dry run to see what would be generated
php artisan api-client:generate-models --dry-run
```

### Generated Model Example

Here's what a generated model looks like:

```php
<?php

namespace App\Models\Api;

use MTechStack\LaravelApiModelClient\Models\ApiModel;
use MTechStack\LaravelApiModelClient\Traits\HasOpenApiSchema;

/**
 * Pet Model
 * 
 * @property int $id Pet ID
 * @property string $name Pet name
 * @property string $status Pet status (available, pending, sold)
 * @property int $category_id Category ID
 * @property array $tags Pet tags
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class Pet extends ApiModel
{
    use HasOpenApiSchema;

    protected string $openApiSchemaSource = 'primary';
    protected string $primaryKey = 'id';
    protected string $endpoint = '/pets';

    protected $fillable = [
        'name',
        'status', 
        'category_id',
        'tags',
    ];

    protected $casts = [
        'id' => 'integer',
        'category_id' => 'integer',
        'tags' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Auto-generated validation rules from OpenAPI schema
    public function getValidationRules(string $operation = 'create'): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'status' => ['required', 'string', 'in:available,pending,sold'],
            'category_id' => ['required', 'integer', 'min:1'],
            'tags' => ['array'],
            'tags.*' => ['string'],
        ];
    }

    // Auto-detected relationships
    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function tags()
    {
        return $this->hasMany(Tag::class);
    }
}
```

### Custom Model Modifications

You can customize generated models:

```php
class Pet extends ApiModel
{
    use HasOpenApiSchema;

    // Override endpoint for custom logic
    protected function getEndpoint(): string
    {
        return $this->exists ? "/pets/{$this->id}" : '/pets';
    }

    // Custom parameter transformation
    protected function transformParametersForApi(array $parameters): array
    {
        // Convert Laravel naming to API naming
        if (isset($parameters['category_id'])) {
            $parameters['categoryId'] = $parameters['category_id'];
            unset($parameters['category_id']);
        }
        
        return parent::transformParametersForApi($parameters);
    }

    // Custom response transformation
    protected function transformResponseFromApi(array $response): array
    {
        // Convert API naming to Laravel naming
        if (isset($response['categoryId'])) {
            $response['category_id'] = $response['categoryId'];
            unset($response['categoryId']);
        }
        
        return parent::transformResponseFromApi($response);
    }

    // Custom validation
    public function validateParameters(array $data, string $operation = 'create'): \Illuminate\Validation\Validator
    {
        $rules = $this->getValidationRules($operation);
        
        // Add custom business rules
        if ($operation === 'create') {
            $rules['name'][] = 'unique:pets,name';
        }
        
        return validator($data, $rules);
    }
}
```

## Advanced Query Building

### OpenAPI-Aware Query Builder

The package provides an enhanced query builder that understands OpenAPI parameters:

```php
use App\Models\Api\Pet;

// Basic OpenAPI queries with automatic validation
$pets = Pet::whereOpenApi('status', 'available')
    ->whereOpenApi('category_id', '>', 1)
    ->orderByOpenApi('name')
    ->limitOpenApi(10)
    ->get();

// Complex filtering with OpenAPI parameter validation
$filteredPets = Pet::query()
    ->whereOpenApi('status', 'in', ['available', 'pending'])
    ->whereOpenApi('tags', 'contains', 'friendly')
    ->whereOpenApi('created_at', '>=', '2024-01-01')
    ->get();

// Pagination with OpenAPI parameters
$paginatedPets = Pet::whereOpenApi('status', 'available')
    ->paginateOpenApi(15, ['page' => 2]);
```

### Dynamic Query Methods

The query builder automatically generates methods based on OpenAPI parameters:

```php
// If OpenAPI schema defines 'status' parameter
$pets = Pet::whereStatus('available')->get();

// If OpenAPI schema defines 'category_id' parameter  
$pets = Pet::whereCategoryId(1)->get();

// If OpenAPI schema defines 'tags' array parameter
$pets = Pet::whereTagsContains('friendly')->get();

// Ordering by OpenAPI-defined sortable fields
$pets = Pet::orderByName('desc')->get();
$pets = Pet::orderByCreatedAt('asc')->get();
```

### Parameter Validation

All query parameters are automatically validated against the OpenAPI schema:

```php
try {
    // This will validate 'status' against OpenAPI enum values
    $pets = Pet::whereOpenApi('status', 'invalid_status')->get();
} catch (\MTechStack\LaravelApiModelClient\Exceptions\ValidationException $e) {
    // Handle validation error
    echo $e->getMessage(); // "Invalid value for parameter 'status'"
}

// Validation strictness can be controlled
Pet::withValidationStrictness('lenient')
    ->whereOpenApi('unknown_param', 'value')
    ->get(); // Won't throw exception in lenient mode
```

### Custom Query Scopes

Combine OpenAPI queries with custom scopes:

```php
class Pet extends ApiModel
{
    // Custom scope
    public function scopeAvailable($query)
    {
        return $query->whereOpenApi('status', 'available');
    }
    
    public function scopeInCategory($query, $categoryId)
    {
        return $query->whereOpenApi('category_id', $categoryId);
    }
    
    public function scopeWithTags($query, array $tags)
    {
        foreach ($tags as $tag) {
            $query->whereOpenApi('tags', 'contains', $tag);
        }
        return $query;
    }
}

// Usage
$pets = Pet::available()
    ->inCategory(1)
    ->withTags(['friendly', 'small'])
    ->get();
```

## Relationship Handling

### Auto-Detected Relationships

The package automatically detects relationships from OpenAPI schemas:

```php
// OpenAPI schema with $ref relationships
{
  "Pet": {
    "properties": {
      "category": {
        "$ref": "#/components/schemas/Category"
      },
      "tags": {
        "type": "array",
        "items": {
          "$ref": "#/components/schemas/Tag"
        }
      }
    }
  }
}
```

Generated relationships:

```php
class Pet extends ApiModel
{
    // Auto-generated belongsTo relationship
    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    // Auto-generated hasMany relationship
    public function tags()
    {
        return $this->hasMany(Tag::class);
    }
}
```

### Custom Relationship Configuration

Override auto-detected relationships:

```php
class Pet extends ApiModel
{
    // Custom relationship configuration
    protected array $openApiRelationships = [
        'category' => [
            'type' => 'belongsTo',
            'model' => Category::class,
            'foreign_key' => 'category_id',
            'local_key' => 'id',
        ],
        'tags' => [
            'type' => 'belongsToMany',
            'model' => Tag::class,
            'pivot_endpoint' => '/pets/{id}/tags',
        ],
        'owner' => [
            'type' => 'hasOne',
            'model' => User::class,
            'foreign_key' => 'pet_id',
            'endpoint' => '/pets/{id}/owner',
        ],
    ];
}
```

### Nested Object Handling

Handle nested objects in API responses:

```php
// API response with nested objects
{
  "id": 1,
  "name": "Fluffy",
  "category": {
    "id": 1,
    "name": "Dogs"
  },
  "tags": [
    {"id": 1, "name": "friendly"},
    {"id": 2, "name": "small"}
  ]
}

// Automatic nested object handling
$pet = Pet::find(1);
echo $pet->category->name; // "Dogs"
echo $pet->tags->first()->name; // "friendly"

// Access nested data directly
echo $pet->category_name; // Auto-mapped from category.name
```

### Eager Loading with OpenAPI

```php
// Eager load relationships defined in OpenAPI
$pets = Pet::with(['category', 'tags'])->get();

// Conditional eager loading
$pets = Pet::when($includeCategory, function($query) {
    $query->with('category');
})->get();

// Load relationships after retrieval
$pet = Pet::find(1);
$pet->load(['category', 'tags']);
```

## Performance Optimization

### Caching Strategies

#### Schema Caching

```php
// Configure schema caching in config/api-client.php
'caching' => [
    'schema_cache' => [
        'enabled' => true,
        'ttl' => 86400, // 24 hours
        'store' => 'redis',
    ],
    'response_cache' => [
        'enabled' => true,
        'ttl' => 3600, // 1 hour
        'store' => 'redis',
        'tags' => ['api-responses'],
    ],
],
```

#### Query Result Caching

```php
// Cache query results
$pets = Pet::whereOpenApi('status', 'available')
    ->remember(3600) // Cache for 1 hour
    ->get();

// Cache with tags for selective invalidation
$pets = Pet::whereOpenApi('category_id', 1)
    ->rememberForever(['pets', 'category-1'])
    ->get();

// Manual cache management
Cache::tags(['pets'])->flush(); // Clear all pet-related cache
```

#### Model-Level Caching

```php
class Pet extends ApiModel
{
    // Enable automatic model caching
    protected bool $enableCaching = true;
    protected int $cacheTtl = 3600;
    protected array $cacheTags = ['pets'];

    // Cache expensive operations
    public function getExpensiveAttribute()
    {
        return Cache::remember(
            "pet-{$this->id}-expensive",
            3600,
            fn() => $this->performExpensiveCalculation()
        );
    }
}
```

### Query Optimization

#### Batch Operations

```php
// Batch create with validation
$pets = Pet::createMany([
    ['name' => 'Pet 1', 'status' => 'available'],
    ['name' => 'Pet 2', 'status' => 'pending'],
    ['name' => 'Pet 3', 'status' => 'sold'],
]);

// Batch update
Pet::whereOpenApi('status', 'pending')
    ->update(['status' => 'available']);

// Batch delete
Pet::whereOpenApi('status', 'sold')
    ->where('updated_at', '<', now()->subMonths(6))
    ->delete();
```

#### Selective Field Loading

```php
// Load only specific fields
$pets = Pet::select(['id', 'name', 'status'])
    ->whereOpenApi('status', 'available')
    ->get();

// Exclude heavy fields
$pets = Pet::withoutOpenApiFields(['description', 'large_image_url'])
    ->get();
```

#### Connection Pooling

```php
// Configure connection pooling in config/api-client.php
'connection' => [
    'pool_size' => 10,
    'max_connections' => 100,
    'timeout' => 30,
    'retry_attempts' => 3,
    'retry_delay' => 1000, // milliseconds
],
```

### Memory Management

```php
// Process large datasets efficiently
Pet::whereOpenApi('status', 'available')
    ->chunk(100, function ($pets) {
        foreach ($pets as $pet) {
            // Process each pet
            $pet->processData();
        }
    });

// Use cursors for very large datasets
foreach (Pet::cursor() as $pet) {
    // Memory-efficient processing
    $pet->process();
}
```

## Real-World Examples

### E-commerce Integration (Bagisto)

```php
<?php

namespace App\Models\Ecommerce;

use MTechStack\LaravelApiModelClient\Models\ApiModel;
use MTechStack\LaravelApiModelClient\Traits\HasOpenApiSchema;

class Product extends ApiModel
{
    use HasOpenApiSchema;

    protected string $openApiSchemaSource = 'bagisto';
    protected string $endpoint = '/products';

    // Bagisto-specific configuration
    protected function getHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . config('api-client.schemas.bagisto.authentication.token'),
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];
    }

    // Custom parameter transformation for Bagisto API
    protected function transformParametersForApi(array $parameters): array
    {
        // Convert Laravel snake_case to Bagisto camelCase
        $transformed = [];
        foreach ($parameters as $key => $value) {
            $transformed[Str::camel($key)] = $value;
        }
        
        return $transformed;
    }

    // Bagisto-specific scopes
    public function scopeInStock($query)
    {
        return $query->whereOpenApi('quantity', '>', 0);
    }

    public function scopeByCategory($query, $categoryId)
    {
        return $query->whereOpenApi('category_id', $categoryId);
    }

    public function scopeOnSale($query)
    {
        return $query->whereOpenApi('special_price', '!=', null)
                    ->whereOpenApi('special_price_from', '<=', now())
                    ->whereOpenApi('special_price_to', '>=', now());
    }
}

// Usage
$products = Product::inStock()
    ->byCategory(1)
    ->onSale()
    ->orderByOpenApi('created_at', 'desc')
    ->paginate(20);
```

### Shopify Integration

```php
<?php

namespace App\Models\Shopify;

class ShopifyProduct extends ApiModel
{
    use HasOpenApiSchema;

    protected string $openApiSchemaSource = 'shopify';
    protected string $endpoint = '/admin/api/2023-10/products';

    // Shopify API versioning
    protected function getBaseUrl(): string
    {
        $shop = config('api-client.schemas.shopify.shop_domain');
        return "https://{$shop}.myshopify.com";
    }

    // Shopify-specific authentication
    protected function getHeaders(): array
    {
        return [
            'X-Shopify-Access-Token' => config('api-client.schemas.shopify.access_token'),
            'Content-Type' => 'application/json',
        ];
    }

    // Handle Shopify's nested product structure
    protected function transformResponseFromApi(array $response): array
    {
        // Shopify wraps products in a 'product' key
        if (isset($response['product'])) {
            return $response['product'];
        }
        
        return $response;
    }

    // Shopify-specific methods
    public function publish()
    {
        return $this->update(['status' => 'active']);
    }

    public function unpublish()
    {
        return $this->update(['status' => 'draft']);
    }

    public function addToCollection($collectionId)
    {
        return $this->apiCall('POST', "/admin/api/2023-10/collections/{$collectionId}/products", [
            'product_id' => $this->id
        ]);
    }
}
```

### Multi-Tenant SaaS Application

```php
<?php

namespace App\Models;

class TenantApiModel extends ApiModel
{
    use HasOpenApiSchema;

    // Dynamic schema selection based on tenant
    protected function getOpenApiSchemaSource(): string
    {
        $tenant = auth()->user()->tenant;
        
        return match($tenant->api_version) {
            'v1' => 'tenant_v1',
            'v2' => 'tenant_v2',
            'v3' => 'tenant_v3',
            default => 'tenant_latest',
        };
    }

    // Dynamic base URL based on tenant
    protected function getBaseUrl(): string
    {
        $tenant = auth()->user()->tenant;
        return "https://{$tenant->subdomain}.api.example.com";
    }

    // Tenant-specific authentication
    protected function getHeaders(): array
    {
        $tenant = auth()->user()->tenant;
        
        return [
            'Authorization' => 'Bearer ' . $tenant->api_token,
            'X-Tenant-ID' => $tenant->id,
            'Accept' => 'application/json',
        ];
    }
}

class Customer extends TenantApiModel
{
    protected string $endpoint = '/customers';

    // Tenant-aware caching
    protected function getCacheKey(string $suffix = ''): string
    {
        $tenantId = auth()->user()->tenant->id;
        return "tenant-{$tenantId}-customer-{$this->id}-{$suffix}";
    }
}
```

### Complex Parameter Validation

```php
<?php

namespace App\Models\Api;

class Order extends ApiModel
{
    use HasOpenApiSchema;

    protected string $openApiSchemaSource = 'ecommerce';
    protected string $endpoint = '/orders';

    // Custom validation with OpenAPI integration
    public function validateParameters(array $data, string $operation = 'create'): \Illuminate\Validation\Validator
    {
        // Get base OpenAPI validation rules
        $rules = $this->getValidationRules($operation);
        
        // Add complex business logic validation
        if ($operation === 'create') {
            $rules['items'] = ['required', 'array', 'min:1'];
            $rules['items.*.product_id'] = ['required', 'integer', 'exists:products,id'];
            $rules['items.*.quantity'] = ['required', 'integer', 'min:1'];
            $rules['items.*.price'] = ['required', 'numeric', 'min:0'];
            
            // Custom validation rule
            $rules['total'] = [
                'required',
                'numeric',
                function ($attribute, $value, $fail) use ($data) {
                    $calculatedTotal = collect($data['items'] ?? [])
                        ->sum(fn($item) => $item['quantity'] * $item['price']);
                    
                    if (abs($value - $calculatedTotal) > 0.01) {
                        $fail('The total does not match the sum of item prices.');
                    }
                },
            ];
        }
        
        return validator($data, $rules, $this->getValidationMessages());
    }

    // Custom validation messages
    protected function getValidationMessages(): array
    {
        return [
            'items.required' => 'An order must contain at least one item.',
            'items.*.product_id.exists' => 'The selected product does not exist.',
            'customer_email.email' => 'Please provide a valid email address.',
        ];
    }

    // Custom parameter transformation with validation
    protected function transformParametersForApi(array $parameters): array
    {
        // Validate before transformation
        $validator = $this->validateParameters($parameters);
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        // Transform for API
        $transformed = parent::transformParametersForApi($parameters);
        
        // Calculate derived fields
        if (isset($transformed['items'])) {
            $transformed['item_count'] = count($transformed['items']);
            $transformed['total_quantity'] = array_sum(array_column($transformed['items'], 'quantity'));
        }
        
        return $transformed;
    }
}
```

This comprehensive guide covers the major aspects of OpenAPI integration. The next sections will cover migration, troubleshooting, and contributing guidelines.
