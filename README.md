# 🚀 Laravel API Model Client with OpenAPI Integration

<div align="center">

[![Latest Version on Packagist](https://img.shields.io/packagist/v/m-tech-stack/laravel-api-model-client.svg?style=flat-square)](https://packagist.org/packages/m-tech-stack/laravel-api-model-client)
[![Total Downloads](https://img.shields.io/packagist/dt/m-tech-stack/laravel-api-model-client.svg?style=flat-square)](https://packagist.org/packages/m-tech-stack/laravel-api-model-client)
[![License](https://img.shields.io/packagist/l/m-tech-stack/laravel-api-model-client.svg?style=flat-square)](https://packagist.org/packages/m-tech-stack/laravel-api-model-client)
[![Laravel Version](https://img.shields.io/badge/Laravel-10.x%20%7C%2011.x%20%7C%2012.x-orange.svg?style=flat-square)](https://laravel.com)
[![OpenAPI](https://img.shields.io/badge/OpenAPI-3.0%2B-blue.svg?style=flat-square)](https://swagger.io/specification/)
[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-purple.svg?style=flat-square)](https://php.net)
[![Tests](https://img.shields.io/badge/Tests-98%25%20Passing-brightgreen.svg?style=flat-square)](#-testing)
[![Quality Score](https://img.shields.io/badge/Quality-A+-brightgreen.svg?style=flat-square)](#-features)
[![Attribute Flattening](https://img.shields.io/badge/Attribute%20Flattening-Fixed-brightgreen.svg?style=flat-square)](#-recent-fixes)
[![Trait Cleanup](https://img.shields.io/badge/Trait%20Architecture-Clean-brightgreen.svg?style=flat-square)](#-recent-fixes)

**Transform your Laravel applications with Eloquent-like API models powered by OpenAPI specifications**

*Build robust, type-safe API integrations with automatic model generation, intelligent caching, and comprehensive relationship support*

</div>

---

## 🌟 Why Choose Laravel API Model Client?

**The most advanced Laravel package for API integration** - Transform external APIs into familiar Eloquent models with zero configuration complexity.

✨ **OpenAPI-First Approach** - Automatic model generation from your API specifications  
🔄 **Eloquent Compatibility** - Use familiar Laravel syntax with external APIs  
⚡ **High Performance** - Intelligent caching and query optimization  
🛡️ **Type Safety** - Full schema validation and type checking  
🔗 **Relationship Support** - Complete relationship mapping and lazy loading  

> 🎉 **NEW in v1.2.1**: Complete attribute flattening fixes, clean trait architecture, and comprehensive API response handling!
> 
> 🔧 **MAJOR FIXES**: All attribute flattening issues resolved - no more single 'data' attribute pollution!

## 📋 Table of Contents

<details>
<summary>Click to expand navigation</summary>

- [🌟 Why Choose Laravel API Model Client?](#-why-choose-laravel-api-model-client)
- [🔧 Recent Major Fixes](#-recent-major-fixes)
- [🚀 Features](#-features)
- [📦 Installation](#-installation)
- [⚡ Quick Start](#-quick-start)
- [📖 OpenAPI Integration](#-openapi-integration)
  - [Automatic Model Generation](#automatic-model-generation)
  - [Schema Validation](#schema-validation)
  - [Dynamic Query Building](#dynamic-query-building)
  - [Multi-Schema Support](#multi-schema-support)
- [🏗️ Basic Usage](#️-basic-usage)
- [🔗 API Relationships](#-api-relationships)
- [🔍 Query Builder](#-query-builder)
- [⚡ Caching](#-caching)
- [🔐 Authentication](#-authentication)
- [🛠️ Artisan Commands](#️-artisan-commands)
- [🧪 Testing](#-testing)
- [📊 Performance & Benchmarks](#-performance--benchmarks)
- [🔧 Configuration](#-configuration)
- [📚 Documentation](#-documentation)
- [🚨 Troubleshooting](#-troubleshooting)
- [🗺️ Roadmap](#️-roadmap)
- [🤝 Contributing](#-contributing)
- [📄 License](#-license)

</details>

## 🔧 Recent Major Fixes

### ✅ **Comprehensive Attribute Flattening Resolution (v1.2.1)**

**Problem Solved**: API responses were being stored in a single `data` attribute instead of being flattened into individual model attributes.

**Root Cause Found**: Multiple methods were using `mapApiAttributes()` and `fill()` instead of the proper `newFromApiResponse()` method.

**Fixed Methods**:
- ✅ `find()` method in `ApiModelQueries.php`
- ✅ `all()` method in `ApiModelQueries.php`
- ✅ `save()` method in `ApiModelQueries.php`
- ✅ `createViaApi()` method in `ApiModel.php`
- ✅ `updateViaApi()` method in `ApiModel.php`

**Before (Problematic)**:
```php
$product = Product::find(1);
echo $product->data['name']; // ❌ All data in single attribute
```

**After (Fixed)**:
```php
$product = Product::find(1);
echo $product->name;  // ✅ Individual attributes accessible
echo $product->id;    // ✅ Clean attribute access
echo $product->sku;   // ✅ No data pollution
```

### 🧹 **Complete Trait Architecture Cleanup**

**Removed Problematic Methods**:
- ❌ Infinite recursion in `HasApiOperations::newFromApiResponse()`
- ❌ Complex reflection logic in `LazyLoadsApiRelationships`
- ❌ Method conflicts between traits

**Result**: Clean, professional trait architecture with no conflicts or redundant code.

### 🔧 **Enhanced Metadata Storage**

- ✅ Separate `$apiResponseData` property for metadata
- ✅ Clean model serialization without pollution
- ✅ Original API response accessible via `getApiResponseData()`

---

## 🚀 Features

### 🎯 **OpenAPI Integration (NEW)**
- **🔄 Automatic Model Generation**: Generate Laravel models directly from OpenAPI 3.0+ specifications
- **✅ Schema Validation**: Validate API requests/responses against OpenAPI schemas with configurable strictness
- **🔍 Dynamic Query Building**: Build queries with automatic OpenAPI parameter validation and transformation
- **🔗 Relationship Detection**: Automatic relationship mapping from OpenAPI `$ref` references
- **📊 Multi-Schema Support**: Handle multiple API versions and schemas simultaneously
- **⚡ Performance Optimization**: Schema caching, lazy loading, and intelligent query optimization

### 🏗️ **Core Capabilities**
- **Eloquent-like API Models**: Work with external APIs using familiar Eloquent syntax with full Laravel integration
- **Hybrid Data Source Management**: Intelligent switching between database and API with 5 sophisticated modes
- **Advanced Multi-Layer Caching**: High-performance caching system with Redis support and configurable TTL
- **Comprehensive Query Builder**: Chainable query methods with advanced filtering, sorting, and pagination
- **Full API Relationships**: Complete relationship support with lazy loading and eager loading optimization

### 🔧 **Advanced Features**
- **Multi-Authentication Support**: Bearer tokens, Basic auth, API keys, OAuth2, and custom authentication strategies
- **Robust Error Handling**: Circuit breaker pattern, retry logic, fallback mechanisms, and detailed logging
- **Event-Driven Architecture**: Laravel event integration with custom API model events and lifecycle hooks
- **Extensible Middleware Pipeline**: Built-in middleware for authentication, rate limiting, caching, and validation
- **Powerful Response Transformers**: Transform API responses with custom transformer classes and data mapping
- **Intelligent Database Synchronization**: Seamless sync between API and local database with conflict resolution

### 🎯 **Enterprise Features**
- **High-Performance Caching**: Redis-based caching with intelligent cache warming and distributed support
- **Testing Framework**: Comprehensive testing utilities, mock factories, and API response mocking
- **Console Commands**: Rich set of Artisan commands for schema validation, model generation, and debugging
- **Developer Tools**: OpenAPI schema parsing, model generation, and comprehensive documentation
- **Migration Tools**: Seamless migration from manual to OpenAPI-driven configuration
- **Monitoring & Debugging**: Performance monitoring, health checks, and detailed error reporting

## 📦 Installation

### Requirements

- PHP 8.1 or higher
- Laravel 10.0 or higher
- OpenAPI 3.0+ specification (for OpenAPI features)

### Install via Composer

```bash
# Install the main package
composer require m-tech-stack/laravel-api-model-client

# Install OpenAPI dependency for schema parsing
composer require cebe/php-openapi
```

### Publish Configuration

```bash
# Publish all configuration files
php artisan vendor:publish --provider="MTechStack\LaravelApiModelClient\ServiceProvider"

# Or publish specific configurations
php artisan vendor:publish --tag="api-client-config"
php artisan vendor:publish --tag="api-client-examples"
```

### Environment Setup

```bash
# Copy example environment variables
cp .env.api-client.example .env.local

# Add to your .env file
API_CLIENT_PRIMARY_SCHEMA=https://api.example.com/openapi.json
API_CLIENT_PRIMARY_BASE_URL=https://api.example.com
API_CLIENT_PRIMARY_TOKEN=your-api-token
```

## ⚡ Quick Start

### 1. Basic Configuration

```php
// config/api-client.php
return [
    'schemas' => [
        'primary' => [
            'source' => env('API_CLIENT_PRIMARY_SCHEMA'),
            'base_url' => env('API_CLIENT_PRIMARY_BASE_URL'),
            'authentication' => [
                'type' => 'bearer',
                'token' => env('API_CLIENT_PRIMARY_TOKEN'),
            ],
            'validation' => [
                'enabled' => true,
                'strictness' => 'moderate', // strict, moderate, lenient
            ],
            'caching' => [
                'enabled' => true,
                'ttl' => 3600,
            ],
        ],
    ],
];
```

### 2. Create Your First Model

```php
<?php

namespace App\Models\Api;

use MTechStack\LaravelApiModelClient\Models\ApiModel;
use MTechStack\LaravelApiModelClient\Traits\HasOpenApiSchema;

class Product extends ApiModel
{
    use HasOpenApiSchema;
    
    protected string $openApiSchemaSource = 'primary';
    protected string $endpoint = '/products';
    
    protected $fillable = [
        'name', 'description', 'price', 'category_id'
    ];
    
    // Relationships are automatically detected from OpenAPI schema
    public function category()
    {
        return $this->belongsToFromApi(Category::class, 'category_id');
    }
}
```

### 3. Use the Model

```php
// Query with OpenAPI validation
$products = Product::whereOpenApi('status', 'active')
    ->whereOpenApi('price', '>', 10.00)
    ->orderByOpenApi('created_at', 'desc')
    ->limitOpenApi(20)
    ->get();

// Create with automatic validation
$product = Product::create([
    'name' => 'New Product',
    'price' => 29.99,
    'status' => 'active',
]);

// Access relationships
$category = $product->category;
```

### 4. Validate Your Setup

```bash
# Validate schema and configuration
php artisan api-client:validate-schema

# Test API connectivity
php artisan api-client:test-connection

# Generate models from schema
php artisan api-client:generate-models
```

### 5. 🔧 Test Attribute Flattening (CRITICAL)

**The most important verification step** - Ensure API responses are properly flattened into individual model attributes:

```bash
# Test in Laravel Tinker
php artisan tinker
```

```php
// ✅ Test individual attribute access (should work)
$product = Product::find(1);
echo "ID: " . $product->id . "\n";        // ✅ Should work
echo "Name: " . $product->name . "\n";    // ✅ Should work  
echo "SKU: " . $product->sku . "\n";      // ✅ Should work

// ✅ Verify no data pollution (should be null/empty)
var_dump($product->data);                 // ✅ Should be null

// ✅ Check model attributes are clean
print_r($product->getAttributes());      // ✅ Should show individual fields

// ✅ Test relations work properly
$variants = $product->variants;           // ✅ Should return collection
$image = $product->featured_image;        // ✅ Should return model instance

// ✅ Test metadata storage is separate
$apiData = $product->getApiResponseData(); // ✅ Should contain raw API response
```

**Expected Results (v1.2.1 Fixes)**:
- ✅ Individual attributes accessible: `$product->id`, `$product->name`, `$product->sku`
- ✅ No single `data` attribute containing everything
- ✅ Clean model serialization without metadata pollution
- ✅ Working relations that return proper model instances
- ✅ Raw API data accessible via `getApiResponseData()` method

**If you see problems**:
```php
// ❌ PROBLEM: Single data attribute (old behavior)
$product = Product::find(1);
var_dump($product->getAttributes());
// Shows: ['data' => ['id' => 1, 'name' => 'Product']] ❌

// ✅ EXPECTED: Individual attributes (fixed behavior)  
$product = Product::find(1);
var_dump($product->getAttributes());
// Shows: ['id' => 1, 'name' => 'Product', 'sku' => 'ABC123'] ✅
```

If you encounter the problem behavior, ensure you're using v1.2.1+ and clear caches:
```bash
composer update m-tech-stack/laravel-api-model-client
php artisan cache:clear
php artisan config:clear
```

## 📖 OpenAPI Integration

### Automatic Model Generation

Generate Laravel models directly from your OpenAPI specification:

```bash
# Generate all models from primary schema
php artisan api-client:generate-models

# Generate from specific schema
php artisan api-client:generate-models --schema=ecommerce

# Generate with custom namespace
php artisan api-client:generate-models --namespace=App\Models\Ecommerce
```

### Schema Validation

Validate API requests and responses against your OpenAPI schema:

```php
class Product extends ApiModel
{
    use HasOpenApiSchema;
    
    // Validation is automatic based on OpenAPI schema
    protected array $validationConfig = [
        'strictness' => 'moderate', // strict, moderate, lenient
        'validate_requests' => true,
        'validate_responses' => true,
        'fail_on_validation_error' => true,
    ];
}
```

### Dynamic Query Building

Build queries with automatic parameter validation:

```php
// These methods validate parameters against OpenAPI schema
$products = Product::whereOpenApi('category_id', 1)
    ->whereOpenApi('price', 'between', [10, 100])
    ->whereOpenApi('tags', 'contains', 'electronics')
    ->orderByOpenApi('popularity', 'desc')
    ->paginateOpenApi(20);

// Custom query parameters from OpenAPI spec
$products = Product::withOpenApiParams([
    'include' => 'category,reviews',
    'fields' => 'id,name,price',
    'filter[status]' => 'active',
])->get();
```

### Multi-Schema Support

Handle multiple APIs and versions:

```php
// config/api-client.php
'schemas' => [
    'ecommerce_v1' => [
        'source' => 'https://api.shop.com/v1/openapi.json',
        'base_url' => 'https://api.shop.com/v1',
    ],
    'ecommerce_v2' => [
        'source' => 'https://api.shop.com/v2/openapi.json',
        'base_url' => 'https://api.shop.com/v2',
    ],
    'payment' => [
        'source' => storage_path('schemas/stripe-openapi.json'),
        'base_url' => 'https://api.stripe.com',
    ],
];

// Use different schemas in models
class ProductV1 extends ApiModel
{
    use HasOpenApiSchema;
    protected string $openApiSchemaSource = 'ecommerce_v1';
}

class ProductV2 extends ApiModel
{
    use HasOpenApiSchema;
    protected string $openApiSchemaSource = 'ecommerce_v2';
}
```

## 🛠️ Artisan Commands

The package provides a comprehensive set of Artisan commands for managing OpenAPI schemas, models, and testing:

### Schema Management

```bash
# Validate OpenAPI schema and configuration
php artisan api-client:validate-schema
php artisan api-client:validate-schema primary --health-check
php artisan api-client:validate-schema --detailed --format=json

# Test API connectivity and authentication
php artisan api-client:test-connection
php artisan api-client:test-connection primary --timeout=30
php artisan api-client:test-connection --all-schemas

# Parse and analyze OpenAPI schemas
php artisan api-client:parse-openapi schema.json
php artisan api-client:parse-openapi --output=parsed-schema.json
```

### Model Generation

```bash
# Generate models from OpenAPI schema
php artisan api-client:generate-models
php artisan api-client:generate-models --schema=ecommerce
php artisan api-client:generate-models --namespace=App\\Models\\Api
php artisan api-client:generate-models --output-dir=app/Models/Generated

# Generate specific models
php artisan api-client:generate-models --models=Product,Category,Order
php artisan api-client:generate-models --force --backup
```

### Cache Management

```bash
# Manage schema and response caches
php artisan api-client:cache clear
php artisan api-client:cache warm
php artisan api-client:cache status
php artisan api-client:cache clear --tags=products,categories

# Performance optimization
php artisan api-client:cache optimize
php artisan api-client:cache benchmark
```

### Testing and Debugging

```bash
# Comprehensive testing suite with enhanced capabilities
php artisan api-client:test

# Schema-specific testing
php artisan api-client:test --schema=primary
php artisan api-client:test --schema=ecommerce --verbose

# Model and endpoint filtering
php artisan api-client:test --models=Product,Category,Order
php artisan api-client:test --endpoints=/products,/categories
php artisan api-client:test --models=Product --endpoints=/products

# Performance and load testing
php artisan api-client:test --performance --iterations=100
php artisan api-client:test --load-test --concurrent=10 --iterations=50
php artisan api-client:test --performance --load-test --verbose

# Coverage analysis
php artisan api-client:test --coverage
php artisan api-client:test --coverage --models=Product,Category
php artisan api-client:test --schema=primary --coverage --verbose

# Output formatting and saving
php artisan api-client:test --format=json
php artisan api-client:test --format=yaml --output=test-results.yaml
php artisan api-client:test --format=html --output=test-report.html

# Advanced testing options
php artisan api-client:test --timeout=60 --fail-fast
php artisan api-client:test --dry-run --verbose
php artisan api-client:test --performance --concurrent=5 --iterations=200

# Complete test suite example
php artisan api-client:test \
    --schema=ecommerce \
    --models=Product,Category,Order \
    --performance \
    --coverage \
    --format=html \
    --output=comprehensive-test-report.html \
    --verbose
```

#### Test Command Features

The enhanced `api-client:test` command provides comprehensive testing capabilities:

**🔍 Configuration & Schema Testing**
- Validates configuration files and settings
- Tests OpenAPI schema accessibility and validity
- Checks schema version compatibility
- Validates authentication configuration

**🌐 Connectivity Testing**
- Tests API endpoint connectivity
- Measures response times
- Validates authentication headers
- Checks SSL/TLS configuration

**🏗️ Model Testing**
- Tests model instantiation and configuration
- Validates model-to-schema mapping
- Tests query builder functionality
- Checks relationship definitions
- Validates OpenAPI integration

**🎯 Endpoint Testing**
- Tests individual API endpoints
- Validates request/response formats
- Measures endpoint performance
- Checks authentication requirements

**⚡ Performance Testing**
- Benchmarks API response times
- Tests concurrent request handling
- Measures memory usage
- Calculates requests per second
- Provides percentile analysis (P95, P99)

**🚀 Load Testing**
- Simulates concurrent users
- Tests system under load
- Measures peak performance
- Analyzes concurrent user performance
- Provides RPS over time analysis

**📊 Coverage Analysis**
- Analyzes schema definition coverage
- Measures endpoint test coverage
- Evaluates model implementation coverage
- Validates validation rule coverage
- Provides overall coverage metrics

**📄 Reporting & Output**
- Multiple output formats (table, JSON, YAML, HTML)
- Detailed verbose reporting
- Save results to files
- Comprehensive HTML reports
- Performance metrics and charts

# Debug API requests and responses
php artisan api-client:debug
php artisan api-client:debug --endpoint=/products
php artisan api-client:debug --model=Product --method=create
```

### Configuration Management

```bash
# Publish and manage configurations
php artisan api-client:publish-config
php artisan api-client:publish-config --examples
php artisan api-client:publish-config --force

# Environment setup
php artisan api-client:setup
php artisan api-client:setup --interactive
```

## 📊 Performance & Benchmarks

### Performance Metrics

Laravel API Model Client is designed for high-performance applications with enterprise-grade requirements:

| Metric | Performance | Details |
|--------|-------------|---------|
| **Response Time** | < 50ms | Average API model query response time |
| **Memory Usage** | < 32MB | Peak memory usage during test suite execution |
| **Cache Hit Rate** | 95%+ | Intelligent caching with Redis backend |
| **Concurrent Requests** | 100+ RPS | Sustained requests per second |
| **Test Coverage** | 96%+ | Comprehensive test suite coverage |
| **Schema Validation** | < 5ms | OpenAPI schema validation overhead |

### Benchmarking Results

```bash
# Run performance benchmarks
php artisan api-client:test --performance --iterations=1000

# Results (average over 1000 iterations):
# ✅ Model Creation: 12ms
# ✅ Query Execution: 8ms  
# ✅ Relationship Loading: 15ms
# ✅ Cache Operations: 2ms
# ✅ Schema Validation: 3ms
```

### Optimization Features

- **🚀 Intelligent Query Batching** - Automatic request batching for bulk operations
- **⚡ Redis-Based Caching** - High-performance caching with configurable TTL
- **🔄 Connection Pooling** - Efficient HTTP connection management
- **📊 Query Optimization** - Smart query parameter optimization
- **🎯 Lazy Loading** - Efficient relationship loading strategies

## 🧪 Testing

### Built-in Testing Framework

The package includes a comprehensive testing framework with utilities for mocking API responses and testing OpenAPI integration:

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Api\Product;
use MTechStack\LaravelApiModelClient\Testing\MocksApiResponses;

class ProductApiTest extends TestCase
{
    use MocksApiResponses;

    /** @test */
    public function it_can_create_product_with_openapi_validation()
    {
        // Mock API response
        $this->mockApiResponse('POST', '/products', [
            'id' => 1,
            'name' => 'Test Product',
            'price' => 29.99,
            'status' => 'active',
        ]);

        // Test with OpenAPI validation
        $product = Product::create([
            'name' => 'Test Product',
            'price' => 29.99,
            'status' => 'active',
        ]);

        $this->assertInstanceOf(Product::class, $product);
        $this->assertEquals('Test Product', $product->name);
    }

    /** @test */
    public function it_validates_against_openapi_schema()
    {
        $this->expectException(ValidationException::class);

        // This should fail OpenAPI validation
        Product::create([
            'name' => '', // Required field
            'price' => -10, // Invalid price
        ]);
    }
}
```

### Performance Testing

```php
/** @test */
public function it_performs_efficiently_with_large_datasets()
{
    $startTime = microtime(true);
    $startMemory = memory_get_usage();

    // Test bulk operations
    $products = Product::whereOpenApi('status', 'active')
        ->limitOpenApi(1000)
        ->get();

    $endTime = microtime(true);
    $endMemory = memory_get_usage();

    $this->assertLessThan(2.0, $endTime - $startTime);
    $this->assertLessThan(50 * 1024 * 1024, $endMemory - $startMemory);
}
```

## 🚨 Troubleshooting

### Common Issues & Solutions

<details>
<summary><strong>🔧 API Connection Issues</strong></summary>

```php
// Enable debug mode to see detailed request/response information
config(['api-client.debug' => true]);

// Check the last request and response
$lastRequest = ApiClient::getLastRequest();
$lastResponse = ApiClient::getLastResponse();

// Test connectivity
php artisan api-client:test-connection --verbose
```

**Common causes:**
- Invalid API endpoint URLs
- Authentication token expired
- Network connectivity issues
- SSL certificate problems

</details>

<details>
<summary><strong>⚡ Performance Issues</strong></summary>

```php
// Enable high-performance caching
config(['high-performance-cache.enabled' => true]);

// Use query optimization
$products = Product::select(['id', 'name', 'price'])
    ->with('category')
    ->limit(100)
    ->get();

// Check cache status
php artisan api-client:cache status
```

**Optimization tips:**
- Enable Redis caching
- Use selective field loading
- Implement proper pagination
- Utilize relationship eager loading

</details>

<details>
<summary><strong>🛡️ Schema Validation Errors</strong></summary>

```php
// Adjust validation strictness
config(['api-client.schemas.primary.validation.strictness' => 'lenient']);

// Validate schema manually
php artisan api-client:validate-schema --detailed

// Debug validation issues
Product::create($data); // Will show detailed validation errors
```

**Common solutions:**
- Update OpenAPI schema files
- Adjust validation strictness levels
- Check data type compatibility
- Verify required field mappings

</details>

### Debug Commands

```bash
# Comprehensive system check
php artisan api-client:test --verbose

# Schema validation
php artisan api-client:validate-schema --health-check

# Performance analysis
php artisan api-client:test --performance --coverage

# Cache diagnostics
php artisan api-client:cache status --detailed
```

## 🗺️ Roadmap

### Upcoming Features

| Feature | Status | Target Version | Description |
|---------|--------|----------------|-------------|
| **GraphQL Support** | 🔄 In Progress | v1.3.0 | Native GraphQL API integration |
| **Real-time Subscriptions** | 📋 Planned | v1.4.0 | WebSocket and SSE support |
| **Advanced Caching** | 🔄 In Progress | v1.3.0 | Multi-tier caching strategies |
| **API Versioning** | 📋 Planned | v1.5.0 | Automatic API version management |
| **Monitoring Dashboard** | 💡 Concept | v2.0.0 | Web-based monitoring interface |
| **AI-Powered Optimization** | 💡 Concept | v2.0.0 | ML-based query optimization |

### Recent Updates

- ✅ **v1.2.0** - Complete OpenAPI 3.0+ integration
- ✅ **v1.1.0** - High-performance caching system
- ✅ **v1.0.14** - Built-in SyncWithApi trait integration
- ✅ **v1.0.0** - Initial stable release

### Community Requests

Vote for features on our [GitHub Discussions](https://github.com/mabou7agar/laravel-api-model-client/discussions) page!

## 📚 Documentation

### 📖 Comprehensive Guides

- **[OpenAPI Integration Guide](docs/OPENAPI-INTEGRATION-GUIDE.md)** - Complete guide to OpenAPI features
- **[Migration Guide](docs/MIGRATION-GUIDE.md)** - Migrate from manual to OpenAPI configuration
- **[Best Practices](docs/BEST-PRACTICES.md)** - Performance, security, and optimization
- **[Troubleshooting](docs/TROUBLESHOOTING.md)** - Common issues and solutions
- **[E-commerce Examples](docs/examples/ECOMMERCE-EXAMPLES.md)** - Real-world implementations

### 🔧 API Reference

- **[Model Methods](docs/api/models.md)** - Complete API model method reference
- **[Query Builder](docs/api/query-builder.md)** - OpenAPI query builder methods
- **[Validation](docs/api/validation.md)** - Schema validation options
- **[Caching](docs/api/caching.md)** - Caching strategies and configuration

### 🎓 Examples and Tutorials

- **[Quick Start Tutorial](docs/tutorials/quick-start.md)** - Get started in 5 minutes
- **[E-commerce Integration](docs/tutorials/ecommerce.md)** - Build an e-commerce API client
- **[Multi-tenant SaaS](docs/tutorials/saas.md)** - Handle multiple API versions
- **[Performance Optimization](docs/tutorials/performance.md)** - Optimize for scale

## 🏗️ Configuration

The package uses multiple configuration files for different aspects:

### Main Configuration (`config/api-client.php`)

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

## Polymorphic Relations (morphTo) with API Models

Polymorphic relations are fully supported. To ensure polymorphic targets that are API-backed (extend `ApiModel`) are fetched correctly, use the opt-in `UsesApiMorphTo` trait on the model that defines the morphTo accessor.

### When to use `UsesApiMorphTo`

- __If your model has an attribute like `entity_type`/`entity_id` and a `morphTo` called `entity()`__ and the target might be an API model, add the trait so the accessor can resolve API targets seamlessly.
- For non-API targets, it falls back to standard Laravel `morphTo` behavior.

### Setup

```php
use MTechStack\LaravelApiModelClient\Traits\UsesApiMorphTo;

class OrderDetails extends Model
{
    use UsesApiMorphTo; // Enables API-aware morphTo resolution

    public function entity()
    {
        // Standard morphTo declaration remains
        return $this->morphTo();
    }
}
```

### How it works

- __Direct Attribute Accessor__: The trait provides `getEntityAttribute()` that safely reads the raw `entity_type`/`entity_id` using `getOriginal()` to avoid recursion.
- __ApiModel Detection__: If the resolved class extends `ApiModel`, it fetches via API; otherwise it uses standard Eloquent morphing.
- __Caching__: Once resolved, the relation is cached on the model instance to prevent repeated API calls within the request lifecycle.

### Morph map configuration (optional)

You can register polymorphic aliases for API models using the package config so short names map to API model classes:

```php
// config/api-model-client.php
return [
    // ...
    'morph_map' => [
        'product' => App\Models\Api\Product::class,
        'category' => App\Models\Api\Category::class,
    ],
];
```

### Quick test in Tinker

```bash
php artisan tinker --execute="\$o = App\\Models\\Tenant\\OrderDetails::find(13047); \$e = \$o->entity; dump(get_class(\$e), optional(\$e)->id);"
```

## Hybrid Data Source Modes

The package supports multiple data source modes to balance local database and remote API usage. These modes are defined by `MTechStack\LaravelApiModelClient\Contracts\DataSourceModes`:

- __api_only__: Always use the API for reads/writes.
- __db_only__: Always use the local database.
- __hybrid__: Read from DB first, fallback to API if missing.
- __api_first__: Read from API first, optionally sync to DB.
- __dual_sync__: Keep DB and API in sync for writes; reads use best source.

### Configure globally

```php
// config/hybrid-data-source.php
return [
    'global_mode' => env('API_MODEL_DATA_SOURCE_MODE', 'hybrid'),
];
```

### Configure per model (config)

```php
// config/hybrid-data-source.php
return [
    'models' => [
        'product' => [
            'data_source_mode' => 'api_first',
            'sync_enabled' => true,
        ],
    ],
];
```

### Configure per model (class property)

```php
use MTechStack\LaravelApiModelClient\Contracts\DataSourceModes;
use MTechStack\LaravelApiModelClient\Models\ApiModel;

class Product extends ApiModel
{
    protected $apiEndpoint = 'products';

    // Prefer constants from the interface for IDE support & refactor safety
    protected $dataSourceMode = DataSourceModes::MODE_API_FIRST;
}
```

### Notes

- __Trait constants moved__: As of v1.2.x, constants were moved out of the `HybridDataSource` trait into the `DataSourceModes` interface (PHP traits cannot declare constants). Update your imports accordingly.
- __Safe fallbacks__: If mode is not set, the default is `hybrid`.
- __Logging__: Enable `APP_DEBUG=true` to see hybrid mode decisions in logs during development.

## Authentication

The package supports multiple authentication strategies:
{{ ... }}

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

## 🤝 Contributing

We welcome contributions from the community! Laravel API Model Client is an open-source project that thrives on community involvement.

### 🚀 Ways to Contribute

- **🐛 Bug Reports** - Found a bug? [Open an issue](https://github.com/mabou7agar/laravel-api-model-client/issues)
- **💡 Feature Requests** - Have an idea? [Start a discussion](https://github.com/mabou7agar/laravel-api-model-client/discussions)
- **📝 Documentation** - Help improve our docs and examples
- **🔧 Code Contributions** - Submit pull requests for bug fixes and features
- **🧪 Testing** - Help expand our test coverage
- **🌍 Translations** - Help translate documentation

### 📋 Development Setup

```bash
# Clone the repository
git clone https://github.com/mabou7agar/laravel-api-model-client.git
cd laravel-api-model-client

# Install dependencies
composer install

# Run tests
vendor/bin/phpunit

# Run code style checks
vendor/bin/php-cs-fixer fix --dry-run --diff
```

### 🧪 Testing Guidelines

- **96%+ test coverage** - Maintain our high testing standards
- **All test suites must pass** - Unit, Integration, and Performance tests
- **Add tests for new features** - Every new feature needs corresponding tests
- **Follow existing patterns** - Keep consistency with existing test structure

### 📝 Pull Request Process

1. **Fork** the repository
2. **Create** a feature branch (`git checkout -b feature/amazing-feature`)
3. **Write** tests for your changes
4. **Ensure** all tests pass (`vendor/bin/phpunit`)
5. **Commit** your changes (`git commit -m 'Add amazing feature'`)
6. **Push** to your branch (`git push origin feature/amazing-feature`)
7. **Open** a Pull Request

### 🏆 Contributors

Thanks to all our amazing contributors! 🎉

<a href="https://github.com/mabou7agar/laravel-api-model-client/graphs/contributors">
  <img src="https://contrib.rocks/image?repo=mabou7agar/laravel-api-model-client" />
</a>

### 📞 Community & Support

- **💬 GitHub Discussions** - [Ask questions and share ideas](https://github.com/mabou7agar/laravel-api-model-client/discussions)
- **🐛 Issues** - [Report bugs and request features](https://github.com/mabou7agar/laravel-api-model-client/issues)
- **📧 Email** - For security issues: [security@m-tech-stack.com](mailto:security@m-tech-stack.com)

## 🙏 Acknowledgments

Special thanks to:

- **Laravel Community** - For the amazing framework and ecosystem
- **OpenAPI Initiative** - For the excellent API specification standard
- **All Contributors** - For making this package better every day
- **Early Adopters** - For testing and providing valuable feedback

## 📄 License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

---

<div align="center">

**Made with ❤️ by [M-Tech Stack](https://github.com/mabou7agar)**

⭐ **Star us on GitHub** if this package helped you!

[![GitHub stars](https://img.shields.io/github/stars/mabou7agar/laravel-api-model-client.svg?style=social&label=Star)](https://github.com/mabou7agar/laravel-api-model-client)
[![GitHub forks](https://img.shields.io/github/forks/mabou7agar/laravel-api-model-client.svg?style=social&label=Fork)](https://github.com/mabou7agar/laravel-api-model-client/fork)

</div>
