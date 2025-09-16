# Best Practices and Performance Optimization

This guide covers best practices for using the Laravel API Model Client with OpenAPI integration, focusing on performance optimization, security, maintainability, and scalability.

## Table of Contents

1. [Performance Optimization](#performance-optimization)
2. [Caching Strategies](#caching-strategies)
3. [Security Best Practices](#security-best-practices)
4. [Code Organization](#code-organization)
5. [Error Handling](#error-handling)
6. [Testing Strategies](#testing-strategies)
7. [Monitoring and Logging](#monitoring-and-logging)
8. [Scalability Considerations](#scalability-considerations)
9. [API Rate Limiting](#api-rate-limiting)
10. [Data Consistency](#data-consistency)

## Performance Optimization

### 1. Schema Caching

Always enable schema caching in production:

```php
// config/api-client.php
'caching' => [
    'schema_cache' => [
        'enabled' => true,
        'ttl' => 86400, // 24 hours
        'store' => 'redis', // Use Redis for better performance
        'prefix' => 'openapi_schema_',
    ],
    'validation_cache' => [
        'enabled' => true,
        'ttl' => 3600, // 1 hour
        'store' => 'redis',
    ],
],
```

### 2. Query Optimization

#### Use Selective Field Loading

```php
// Good: Load only needed fields
$products = Product::select(['id', 'name', 'price', 'status'])
    ->whereOpenApi('status', 'active')
    ->get();

// Bad: Load all fields unnecessarily
$products = Product::whereOpenApi('status', 'active')->get();
```

#### Implement Proper Pagination

```php
// Good: Use pagination for large datasets
$products = Product::whereOpenApi('category_id', 1)
    ->paginateOpenApi(20);

// Better: Use cursor pagination for very large datasets
$products = Product::whereOpenApi('category_id', 1)
    ->cursorPaginate(20);
```

#### Batch Operations

```php
// Good: Batch create operations
$products = Product::createMany([
    ['name' => 'Product 1', 'price' => 10.00],
    ['name' => 'Product 2', 'price' => 15.00],
    ['name' => 'Product 3', 'price' => 20.00],
]);

// Good: Batch updates
Product::whereOpenApi('status', 'draft')
    ->update(['status' => 'active']);
```

### 3. Connection Pooling

Configure connection pooling for high-traffic applications:

```php
// config/api-client.php
'connection' => [
    'pool_size' => 20,
    'max_connections' => 100,
    'timeout' => 30,
    'retry_attempts' => 3,
    'retry_delay' => 1000, // milliseconds
    'keep_alive' => true,
],
```

### 4. Memory Management

#### Process Large Datasets Efficiently

```php
// Good: Use chunking for large datasets
Product::whereOpenApi('status', 'active')
    ->chunk(100, function ($products) {
        foreach ($products as $product) {
            $product->processData();
        }
    });

// Better: Use lazy collections for memory efficiency
Product::whereOpenApi('status', 'active')
    ->lazy()
    ->each(function ($product) {
        $product->processData();
    });
```

#### Avoid N+1 Queries

```php
// Bad: N+1 query problem
$orders = Order::all();
foreach ($orders as $order) {
    echo $order->customer->name; // Triggers additional API call
}

// Good: Eager loading
$orders = Order::with('customer')->get();
foreach ($orders as $order) {
    echo $order->customer->name; // No additional API calls
}
```

## Caching Strategies

### 1. Multi-Level Caching

Implement multiple caching layers:

```php
class Product extends ApiModel
{
    use HasOpenApiSchema;

    // Level 1: Model-level caching
    protected bool $enableCaching = true;
    protected int $cacheTtl = 3600;
    protected array $cacheTags = ['products'];

    // Level 2: Query result caching
    public static function getActiveProducts()
    {
        return Cache::tags(['products', 'active'])
            ->remember('active_products', 1800, function () {
                return static::whereOpenApi('status', 'active')->get();
            });
    }

    // Level 3: Computed property caching
    public function getFormattedPriceAttribute()
    {
        return Cache::remember(
            "product_{$this->id}_formatted_price",
            3600,
            fn() => '$' . number_format($this->price, 2)
        );
    }
}
```

### 2. Cache Invalidation Strategy

```php
class Product extends ApiModel
{
    protected static function booted()
    {
        // Invalidate cache on model changes
        static::saved(function ($product) {
            Cache::tags(['products', "product_{$product->id}"])->flush();
        });

        static::deleted(function ($product) {
            Cache::tags(['products', "product_{$product->id}"])->flush();
        });
    }

    // Manual cache invalidation
    public function invalidateCache()
    {
        Cache::tags([
            'products',
            "product_{$this->id}",
            "category_{$this->category_id}"
        ])->flush();
    }
}
```

### 3. Cache Warming

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Api\Product;

class WarmApiCache extends Command
{
    protected $signature = 'cache:warm-api';
    protected $description = 'Warm API model caches';

    public function handle()
    {
        $this->info('Warming API caches...');

        // Warm frequently accessed data
        Product::getActiveProducts();
        Product::getFeaturedProducts();
        Product::getTopSellingProducts();

        $this->info('API caches warmed successfully!');
    }
}
```

## Security Best Practices

### 1. API Token Management

```php
// config/api-client.php
'schemas' => [
    'primary' => [
        'authentication' => [
            'type' => 'bearer',
            'token' => env('API_CLIENT_TOKEN'), // Never hardcode tokens
            'refresh_token' => env('API_CLIENT_REFRESH_TOKEN'),
            'token_expiry' => env('API_CLIENT_TOKEN_EXPIRY'),
        ],
    ],
],

// Implement token refresh
class ApiTokenManager
{
    public function refreshTokenIfNeeded(string $schema): void
    {
        $config = config("api-client.schemas.{$schema}");
        $expiry = $config['authentication']['token_expiry'] ?? null;
        
        if ($expiry && now()->isAfter($expiry)) {
            $this->refreshToken($schema);
        }
    }

    private function refreshToken(string $schema): void
    {
        // Implement token refresh logic
        $refreshToken = config("api-client.schemas.{$schema}.authentication.refresh_token");
        
        // Make refresh request and update configuration
        // Store new tokens securely
    }
}
```

### 2. Input Validation and Sanitization

```php
class Product extends ApiModel
{
    public function validateParameters(array $data, string $operation = 'create'): \Illuminate\Validation\Validator
    {
        // Get OpenAPI validation rules
        $rules = parent::getValidationRules($operation);
        
        // Add security-focused validation
        $rules['name'][] = 'regex:/^[a-zA-Z0-9\s\-_]+$/'; // Prevent XSS
        $rules['description'][] = 'string|max:1000'; // Limit length
        
        // Sanitize input
        $data = $this->sanitizeInput($data);
        
        return validator($data, $rules);
    }

    private function sanitizeInput(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $data[$key] = strip_tags($value); // Remove HTML tags
                $data[$key] = trim($data[$key]); // Remove whitespace
            }
        }
        
        return $data;
    }
}
```

### 3. Rate Limiting

```php
class ApiModel extends BaseApiModel
{
    protected function makeApiRequest(string $method, string $endpoint, array $data = []): array
    {
        // Implement rate limiting
        $rateLimiter = app('rate-limiter');
        $key = "api_requests_{$this->getSchemaSource()}";
        
        if ($rateLimiter->tooManyAttempts($key, 100)) { // 100 requests per minute
            throw new TooManyRequestsException('API rate limit exceeded');
        }
        
        $rateLimiter->hit($key, 60); // 60 seconds window
        
        return parent::makeApiRequest($method, $endpoint, $data);
    }
}
```

## Code Organization

### 1. Model Structure

Organize your models with clear separation of concerns:

```php
<?php

namespace App\Models\Api;

use MTechStack\LaravelApiModelClient\Models\ApiModel;
use MTechStack\LaravelApiModelClient\Traits\HasOpenApiSchema;
use App\Traits\HasApiCaching;
use App\Traits\HasApiValidation;
use App\Traits\HasApiTransformation;

class Product extends ApiModel
{
    use HasOpenApiSchema,
        HasApiCaching,
        HasApiValidation,
        HasApiTransformation;

    protected string $openApiSchemaSource = 'ecommerce';
    
    // Configuration
    protected function configure(): void
    {
        $this->setCacheConfig(['ttl' => 3600, 'tags' => ['products']]);
        $this->setValidationConfig(['strictness' => 'moderate']);
    }

    // Business logic methods
    public function activate(): bool
    {
        return $this->update(['status' => 'active']);
    }

    public function deactivate(): bool
    {
        return $this->update(['status' => 'inactive']);
    }

    // Query scopes
    public function scopeActive($query)
    {
        return $query->whereOpenApi('status', 'active');
    }

    // Relationships
    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}
```

### 2. Service Layer Pattern

```php
<?php

namespace App\Services\Api;

use App\Models\Api\Product;
use Illuminate\Support\Collection;

class ProductService
{
    public function __construct(private Product $product)
    {
    }

    public function getActiveProducts(int $limit = 20): Collection
    {
        return $this->product
            ->active()
            ->orderByOpenApi('created_at', 'desc')
            ->limitOpenApi($limit)
            ->get();
    }

    public function createProduct(array $data): Product
    {
        // Validate data
        $validator = $this->product->validateParameters($data, 'create');
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        // Create product
        $product = $this->product->create($data);

        // Trigger events
        event(new ProductCreated($product));

        return $product;
    }

    public function bulkUpdateStatus(array $productIds, string $status): int
    {
        return $this->product
            ->whereIn('id', $productIds)
            ->update(['status' => $status]);
    }
}
```

### 3. Repository Pattern

```php
<?php

namespace App\Repositories\Api;

use App\Models\Api\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ProductRepositoryInterface
{
    public function findActive(int $limit = 20): Collection;
    public function findByCategory(int $categoryId): Collection;
    public function search(string $query): Collection;
    public function paginate(int $perPage = 15): LengthAwarePaginator;
}

class ProductRepository implements ProductRepositoryInterface
{
    public function __construct(private Product $model)
    {
    }

    public function findActive(int $limit = 20): Collection
    {
        return $this->model
            ->active()
            ->limitOpenApi($limit)
            ->get();
    }

    public function findByCategory(int $categoryId): Collection
    {
        return $this->model
            ->whereOpenApi('category_id', $categoryId)
            ->active()
            ->get();
    }

    public function search(string $query): Collection
    {
        return $this->model
            ->whereOpenApi('name', 'like', "%{$query}%")
            ->orWhereOpenApi('description', 'like', "%{$query}%")
            ->active()
            ->get();
    }

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->model
            ->active()
            ->paginateOpenApi($perPage);
    }
}
```

## Error Handling

### 1. Custom Exception Classes

```php
<?php

namespace App\Exceptions\Api;

use Exception;

class ApiConnectionException extends Exception
{
    public function __construct(string $message, int $code = 0, ?Exception $previous = null)
    {
        parent::__construct("API Connection Error: {$message}", $code, $previous);
    }
}

class ApiValidationException extends Exception
{
    private array $errors;

    public function __construct(array $errors, string $message = 'Validation failed')
    {
        $this->errors = $errors;
        parent::__construct($message);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}

class ApiRateLimitException extends Exception
{
    private int $retryAfter;

    public function __construct(int $retryAfter, string $message = 'Rate limit exceeded')
    {
        $this->retryAfter = $retryAfter;
        parent::__construct($message);
    }

    public function getRetryAfter(): int
    {
        return $this->retryAfter;
    }
}
```

### 2. Global Error Handler

```php
<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use App\Exceptions\Api\ApiConnectionException;
use App\Exceptions\Api\ApiValidationException;
use App\Exceptions\Api\ApiRateLimitException;

class Handler extends ExceptionHandler
{
    public function register()
    {
        $this->reportable(function (ApiConnectionException $e) {
            Log::error('API Connection Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        });

        $this->renderable(function (ApiValidationException $e, $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => 'Validation failed',
                    'errors' => $e->getErrors(),
                ], 422);
            }
        });

        $this->renderable(function (ApiRateLimitException $e, $request) {
            return response()->json([
                'error' => 'Rate limit exceeded',
                'retry_after' => $e->getRetryAfter(),
            ], 429)->header('Retry-After', $e->getRetryAfter());
        });
    }
}
```

### 3. Circuit Breaker Pattern

```php
<?php

namespace App\Services\Api;

use Illuminate\Support\Facades\Cache;

class CircuitBreaker
{
    private string $service;
    private int $failureThreshold;
    private int $timeout;
    private int $retryTimeout;

    public function __construct(string $service, int $failureThreshold = 5, int $timeout = 60, int $retryTimeout = 300)
    {
        $this->service = $service;
        $this->failureThreshold = $failureThreshold;
        $this->timeout = $timeout;
        $this->retryTimeout = $retryTimeout;
    }

    public function call(callable $callback)
    {
        $state = $this->getState();

        if ($state === 'open') {
            if ($this->shouldAttemptReset()) {
                $this->setState('half-open');
            } else {
                throw new ServiceUnavailableException("Service {$this->service} is unavailable");
            }
        }

        try {
            $result = $callback();
            $this->onSuccess();
            return $result;
        } catch (Exception $e) {
            $this->onFailure();
            throw $e;
        }
    }

    private function getState(): string
    {
        return Cache::get("circuit_breaker_{$this->service}_state", 'closed');
    }

    private function setState(string $state): void
    {
        Cache::put("circuit_breaker_{$this->service}_state", $state, $this->timeout);
    }

    private function getFailureCount(): int
    {
        return Cache::get("circuit_breaker_{$this->service}_failures", 0);
    }

    private function incrementFailureCount(): void
    {
        $count = $this->getFailureCount() + 1;
        Cache::put("circuit_breaker_{$this->service}_failures", $count, $this->timeout);

        if ($count >= $this->failureThreshold) {
            $this->setState('open');
            Cache::put("circuit_breaker_{$this->service}_opened_at", now(), $this->retryTimeout);
        }
    }

    private function resetFailureCount(): void
    {
        Cache::forget("circuit_breaker_{$this->service}_failures");
    }

    private function shouldAttemptReset(): bool
    {
        $openedAt = Cache::get("circuit_breaker_{$this->service}_opened_at");
        return $openedAt && now()->diffInSeconds($openedAt) >= $this->retryTimeout;
    }

    private function onSuccess(): void
    {
        $this->resetFailureCount();
        $this->setState('closed');
    }

    private function onFailure(): void
    {
        $this->incrementFailureCount();
    }
}
```

## Testing Strategies

### 1. Unit Testing

```php
<?php

namespace Tests\Unit\Models\Api;

use Tests\TestCase;
use App\Models\Api\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_can_validate_product_data()
    {
        $product = new Product();
        
        $validData = [
            'name' => 'Test Product',
            'price' => 19.99,
            'status' => 'active',
        ];

        $validator = $product->validateParameters($validData);
        $this->assertTrue($validator->passes());
    }

    /** @test */
    public function it_rejects_invalid_product_data()
    {
        $product = new Product();
        
        $invalidData = [
            'name' => '', // Required field
            'price' => -10, // Invalid price
            'status' => 'invalid_status', // Invalid enum
        ];

        $validator = $product->validateParameters($invalidData);
        $this->assertTrue($validator->fails());
    }

    /** @test */
    public function it_can_scope_active_products()
    {
        $this->mockApiResponse('/products?status=active', [
            ['id' => 1, 'name' => 'Product 1', 'status' => 'active'],
            ['id' => 2, 'name' => 'Product 2', 'status' => 'active'],
        ]);

        $products = Product::active()->get();
        
        $this->assertCount(2, $products);
        $this->assertTrue($products->every(fn($p) => $p->status === 'active'));
    }
}
```

### 2. Integration Testing

```php
<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\Api\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ProductIntegrationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_can_create_product_via_api()
    {
        $this->mockApiResponse('POST', '/products', [
            'id' => 1,
            'name' => 'New Product',
            'price' => 29.99,
            'status' => 'active',
        ]);

        $product = Product::create([
            'name' => 'New Product',
            'price' => 29.99,
            'status' => 'active',
        ]);

        $this->assertInstanceOf(Product::class, $product);
        $this->assertEquals('New Product', $product->name);
        $this->assertEquals(29.99, $product->price);
    }

    /** @test */
    public function it_handles_api_errors_gracefully()
    {
        $this->mockApiError('POST', '/products', 422, [
            'error' => 'Validation failed',
            'errors' => ['name' => ['The name field is required.']],
        ]);

        $this->expectException(ApiValidationException::class);

        Product::create(['price' => 29.99]);
    }
}
```

### 3. Performance Testing

```php
<?php

namespace Tests\Performance;

use Tests\TestCase;
use App\Models\Api\Product;

class ProductPerformanceTest extends TestCase
{
    /** @test */
    public function it_performs_bulk_operations_efficiently()
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage();

        // Create 1000 products
        $products = [];
        for ($i = 0; $i < 1000; $i++) {
            $products[] = [
                'name' => "Product {$i}",
                'price' => rand(10, 100),
                'status' => 'active',
            ];
        }

        Product::createMany($products);

        $endTime = microtime(true);
        $endMemory = memory_get_usage();

        $executionTime = $endTime - $startTime;
        $memoryUsage = $endMemory - $startMemory;

        // Assert performance criteria
        $this->assertLessThan(5.0, $executionTime, 'Bulk creation should complete within 5 seconds');
        $this->assertLessThan(50 * 1024 * 1024, $memoryUsage, 'Memory usage should be under 50MB');
    }
}
```

## Monitoring and Logging

### 1. Structured Logging

```php
<?php

namespace App\Traits;

use Illuminate\Support\Facades\Log;

trait HasApiLogging
{
    protected function logApiRequest(string $method, string $endpoint, array $data = []): void
    {
        Log::info('API Request', [
            'model' => static::class,
            'method' => $method,
            'endpoint' => $endpoint,
            'data_size' => strlen(json_encode($data)),
            'timestamp' => now()->toISOString(),
        ]);
    }

    protected function logApiResponse(array $response, float $responseTime): void
    {
        Log::info('API Response', [
            'model' => static::class,
            'response_size' => strlen(json_encode($response)),
            'response_time' => $responseTime,
            'timestamp' => now()->toISOString(),
        ]);
    }

    protected function logApiError(\Exception $exception, string $context = ''): void
    {
        Log::error('API Error', [
            'model' => static::class,
            'exception' => get_class($exception),
            'message' => $exception->getMessage(),
            'context' => $context,
            'trace' => $exception->getTraceAsString(),
            'timestamp' => now()->toISOString(),
        ]);
    }
}
```

### 2. Performance Monitoring

```php
<?php

namespace App\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ApiPerformanceMonitoring
{
    public function handle(Request $request, Closure $next)
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage();

        $response = $next($request);

        $endTime = microtime(true);
        $endMemory = memory_get_usage();

        $executionTime = ($endTime - $startTime) * 1000; // Convert to milliseconds
        $memoryUsage = $endMemory - $startMemory;

        // Log performance metrics
        Log::info('API Performance', [
            'endpoint' => $request->path(),
            'method' => $request->method(),
            'execution_time_ms' => $executionTime,
            'memory_usage_bytes' => $memoryUsage,
            'peak_memory_bytes' => memory_get_peak_usage(),
        ]);

        // Add performance headers
        $response->headers->set('X-Execution-Time', $executionTime);
        $response->headers->set('X-Memory-Usage', $memoryUsage);

        return $response;
    }
}
```

### 3. Health Checks

```php
<?php

namespace App\Http\Controllers\Api;

use App\Models\Api\Product;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    public function check(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'api_connectivity' => $this->checkApiConnectivity(),
            'cache' => $this->checkCache(),
            'schema_validation' => $this->checkSchemaValidation(),
        ];

        $healthy = collect($checks)->every(fn($check) => $check['status'] === 'ok');

        return response()->json([
            'status' => $healthy ? 'healthy' : 'unhealthy',
            'checks' => $checks,
            'timestamp' => now()->toISOString(),
        ], $healthy ? 200 : 503);
    }

    private function checkDatabase(): array
    {
        try {
            \DB::connection()->getPdo();
            return ['status' => 'ok', 'message' => 'Database connection successful'];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    private function checkApiConnectivity(): array
    {
        try {
            Product::take(1)->get();
            return ['status' => 'ok', 'message' => 'API connectivity successful'];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    private function checkCache(): array
    {
        try {
            \Cache::put('health_check', 'ok', 60);
            $value = \Cache::get('health_check');
            return ['status' => $value === 'ok' ? 'ok' : 'error', 'message' => 'Cache check'];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    private function checkSchemaValidation(): array
    {
        try {
            $product = new Product();
            $validator = $product->validateParameters(['name' => 'Test']);
            return ['status' => 'ok', 'message' => 'Schema validation working'];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
}
```

This comprehensive best practices guide covers performance optimization, security, code organization, error handling, testing, and monitoring. Following these practices will help you build robust, scalable, and maintainable applications with the Laravel API Model Client and OpenAPI integration.
