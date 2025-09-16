# Troubleshooting Guide

This guide helps you diagnose and resolve common issues when using the Laravel API Model Client with OpenAPI integration.

## Table of Contents

1. [Common Issues](#common-issues)
2. [Schema-Related Problems](#schema-related-problems)
3. [Authentication Issues](#authentication-issues)
4. [Validation Problems](#validation-problems)
5. [Performance Issues](#performance-issues)
6. [Caching Problems](#caching-problems)
7. [Network and Connectivity](#network-and-connectivity)
8. [Debugging Tools](#debugging-tools)
9. [Error Messages Reference](#error-messages-reference)
10. [Getting Help](#getting-help)

## Common Issues

### Issue: "Class 'cebe\openapi\Reader' not found"

**Symptoms:**
```
Error: Class "cebe\openapi\Reader" not found
```

**Cause:** Missing OpenAPI dependency.

**Solution:**
```bash
# Install the required dependency
composer require cebe/php-openapi

# Update composer dependencies
composer update
```

**Prevention:**
Ensure your `composer.json` includes:
```json
{
    "require": {
        "cebe/php-openapi": "^1.0"
    }
}
```

### Issue: "OpenAPI schema not found"

**Symptoms:**
```
OpenApiParsingException: OpenAPI schema file not found: /path/to/schema.json
```

**Cause:** Incorrect schema path or URL.

**Solution:**
1. **Check the schema path:**
```php
// Verify the path exists
if (file_exists('/path/to/schema.json')) {
    echo "File exists";
} else {
    echo "File not found";
}
```

2. **For remote schemas, check connectivity:**
```bash
# Test URL accessibility
curl -I https://api.example.com/openapi.json
```

3. **Update configuration:**
```php
// config/api-client.php
'schemas' => [
    'primary' => [
        'source' => env('API_CLIENT_PRIMARY_SCHEMA', 'https://api.example.com/openapi.json'),
        'fallback_source' => storage_path('api-schemas/fallback.json'), // Add fallback
    ],
],
```

### Issue: "Type mismatch in model property"

**Symptoms:**
```
TypeError: Type of Model::$property must be ?array (as in class ApiModel)
```

**Cause:** Property type declaration doesn't match parent class.

**Solution:**
```php
// Incorrect
class Pet extends ApiModel
{
    protected array $openApiSchema; // Should be nullable
}

// Correct
class Pet extends ApiModel
{
    protected ?array $openApiSchema; // Matches parent class
}
```

### Issue: "Validation strictness too restrictive"

**Symptoms:**
- Valid data being rejected
- Unexpected validation errors

**Cause:** OpenAPI validation is stricter than expected.

**Solution:**
1. **Adjust validation strictness:**
```php
// config/api-client.php
'validation' => [
    'strictness' => 'lenient', // Change from 'strict' to 'lenient'
    'log_validation_warnings' => true,
],
```

2. **Override validation in model:**
```php
class Product extends ApiModel
{
    public function getValidationRules(string $operation = 'create'): array
    {
        $rules = parent::getValidationRules($operation);
        
        // Relax specific rules
        if (isset($rules['email'])) {
            $rules['email'] = str_replace('required|', '', $rules['email']);
        }
        
        return $rules;
    }
}
```

## Schema-Related Problems

### Issue: Schema parsing fails

**Symptoms:**
```
OpenApiParsingException: Failed to parse OpenAPI schema
```

**Debugging Steps:**

1. **Validate schema format:**
```bash
# For JSON schemas
cat schema.json | jq . > /dev/null && echo "Valid JSON" || echo "Invalid JSON"

# For YAML schemas
python -c "import yaml; yaml.safe_load(open('schema.yaml'))" && echo "Valid YAML" || echo "Invalid YAML"
```

2. **Check schema version:**
```php
// Debug schema content
$parser = new OpenApiSchemaParser();
try {
    $schema = json_decode(file_get_contents('/path/to/schema.json'), true);
    echo "OpenAPI Version: " . ($schema['openapi'] ?? 'Not found');
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
```

3. **Test with minimal schema:**
```json
{
    "openapi": "3.0.0",
    "info": {
        "title": "Test API",
        "version": "1.0.0"
    },
    "paths": {}
}
```

### Issue: Schema references not resolving

**Symptoms:**
- Missing relationship data
- Incomplete model generation

**Cause:** `$ref` references in schema not being resolved.

**Solution:**
1. **Check reference format:**
```json
// Correct internal reference
{
    "$ref": "#/components/schemas/Pet"
}

// Correct external reference
{
    "$ref": "https://api.example.com/schemas/pet.json"
}
```

2. **Enable reference resolution:**
```php
// config/api-client.php
'openapi' => [
    'resolve_references' => true,
    'reference_cache_ttl' => 3600,
],
```

### Issue: Large schema performance

**Symptoms:**
- Slow schema parsing
- Memory exhaustion

**Solution:**
1. **Enable schema caching:**
```php
'caching' => [
    'schema_cache' => [
        'enabled' => true,
        'ttl' => 86400, // 24 hours
        'store' => 'redis',
    ],
],
```

2. **Increase memory limit:**
```php
// In your service provider or bootstrap
ini_set('memory_limit', '512M');
```

3. **Use schema chunking:**
```php
class LargeSchemaParser extends OpenApiSchemaParser
{
    protected function parseInChunks(array $schema): array
    {
        // Process schema in smaller chunks
        $chunks = array_chunk($schema['paths'] ?? [], 100);
        $result = [];
        
        foreach ($chunks as $chunk) {
            $result = array_merge($result, $this->processChunk($chunk));
        }
        
        return $result;
    }
}
```

## Authentication Issues

### Issue: "Unauthorized" API responses

**Symptoms:**
```
401 Unauthorized
403 Forbidden
```

**Debugging Steps:**

1. **Verify credentials:**
```php
// Test authentication configuration
$config = config('api-client.schemas.primary.authentication');
var_dump($config);

// Test token validity
$token = $config['token'];
echo "Token length: " . strlen($token);
echo "Token starts with: " . substr($token, 0, 10) . "...";
```

2. **Check token expiration:**
```php
// For JWT tokens
function checkJwtExpiration($token) {
    $parts = explode('.', $token);
    if (count($parts) !== 3) return false;
    
    $payload = json_decode(base64_decode($parts[1]), true);
    $exp = $payload['exp'] ?? null;
    
    if ($exp) {
        return $exp > time() ? 'Valid' : 'Expired';
    }
    
    return 'No expiration';
}

echo checkJwtExpiration($token);
```

3. **Test authentication manually:**
```bash
# Test Bearer token
curl -H "Authorization: Bearer YOUR_TOKEN" https://api.example.com/test

# Test API key
curl -H "X-API-Key: YOUR_API_KEY" https://api.example.com/test

# Test Basic auth
curl -u "username:password" https://api.example.com/test
```

### Issue: Token refresh not working

**Symptoms:**
- Expired tokens not being refreshed
- Authentication failures after token expiry

**Solution:**
1. **Implement token refresh:**
```php
class TokenManager
{
    public function refreshToken(string $schema): void
    {
        $config = config("api-client.schemas.{$schema}");
        $refreshToken = $config['authentication']['refresh_token'];
        
        $response = Http::post($config['token_refresh_url'], [
            'refresh_token' => $refreshToken,
            'grant_type' => 'refresh_token',
        ]);
        
        if ($response->successful()) {
            $data = $response->json();
            
            // Update configuration
            config([
                "api-client.schemas.{$schema}.authentication.token" => $data['access_token'],
                "api-client.schemas.{$schema}.authentication.refresh_token" => $data['refresh_token'],
                "api-client.schemas.{$schema}.authentication.expires_at" => now()->addSeconds($data['expires_in']),
            ]);
        }
    }
}
```

2. **Automatic token refresh middleware:**
```php
class AutoRefreshToken
{
    public function handle($request, Closure $next)
    {
        $schema = $request->route('schema', 'primary');
        $expiresAt = config("api-client.schemas.{$schema}.authentication.expires_at");
        
        if ($expiresAt && now()->isAfter($expiresAt->subMinutes(5))) {
            app(TokenManager::class)->refreshToken($schema);
        }
        
        return $next($request);
    }
}
```

## Validation Problems

### Issue: Validation rules too strict

**Symptoms:**
- Valid data being rejected
- Unexpected validation failures

**Solution:**
1. **Debug validation rules:**
```php
$product = new Product();
$rules = $product->getValidationRules('create');
dd($rules); // Inspect generated rules

// Test specific validation
$validator = validator(['name' => 'Test'], $rules);
if ($validator->fails()) {
    dd($validator->errors());
}
```

2. **Override specific rules:**
```php
class Product extends ApiModel
{
    public function getValidationRules(string $operation = 'create'): array
    {
        $rules = parent::getValidationRules($operation);
        
        // Debug: Log the rules
        Log::info('Generated validation rules', $rules);
        
        // Customize rules
        $rules['description'] = ['string', 'max:1000']; // Remove 'required'
        $rules['price'] = ['numeric', 'min:0']; // Remove max constraint
        
        return $rules;
    }
}
```

### Issue: Enum validation failures

**Symptoms:**
```
The selected status is invalid.
```

**Cause:** Enum values in OpenAPI schema don't match your data.

**Solution:**
1. **Check enum values in schema:**
```php
$schema = json_decode(file_get_contents('schema.json'), true);
$statusEnum = $schema['components']['schemas']['Product']['properties']['status']['enum'];
var_dump($statusEnum);
```

2. **Map enum values:**
```php
class Product extends ApiModel
{
    protected array $enumMappings = [
        'status' => [
            'active' => 'published',
            'inactive' => 'draft',
        ],
    ];
    
    protected function transformParametersForApi(array $parameters): array
    {
        foreach ($this->enumMappings as $field => $mapping) {
            if (isset($parameters[$field]) && isset($mapping[$parameters[$field]])) {
                $parameters[$field] = $mapping[$parameters[$field]];
            }
        }
        
        return parent::transformParametersForApi($parameters);
    }
}
```

## Performance Issues

### Issue: Slow API responses

**Symptoms:**
- Long response times
- Timeouts

**Debugging Steps:**

1. **Enable query logging:**
```php
// In AppServiceProvider
public function boot()
{
    if (app()->environment('local')) {
        DB::listen(function ($query) {
            Log::info('API Query', [
                'sql' => $query->sql,
                'bindings' => $query->bindings,
                'time' => $query->time,
            ]);
        });
    }
}
```

2. **Profile API calls:**
```php
class Product extends ApiModel
{
    protected function makeApiRequest(string $method, string $endpoint, array $data = []): array
    {
        $start = microtime(true);
        
        $response = parent::makeApiRequest($method, $endpoint, $data);
        
        $duration = microtime(true) - $start;
        
        Log::info('API Request Performance', [
            'method' => $method,
            'endpoint' => $endpoint,
            'duration' => $duration,
            'data_size' => strlen(json_encode($data)),
            'response_size' => strlen(json_encode($response)),
        ]);
        
        return $response;
    }
}
```

3. **Optimize queries:**
```php
// Bad: N+1 queries
$products = Product::all();
foreach ($products as $product) {
    echo $product->category->name;
}

// Good: Eager loading
$products = Product::with('category')->get();
foreach ($products as $product) {
    echo $product->category->name;
}
```

### Issue: Memory exhaustion

**Symptoms:**
```
Fatal error: Allowed memory size exhausted
```

**Solution:**
1. **Use chunking for large datasets:**
```php
// Instead of loading all at once
$products = Product::all(); // Memory intensive

// Use chunking
Product::chunk(100, function ($products) {
    foreach ($products as $product) {
        $product->process();
    }
});
```

2. **Use lazy collections:**
```php
// Memory efficient processing
Product::lazy()->each(function ($product) {
    $product->process();
});
```

3. **Clear memory periodically:**
```php
Product::chunk(100, function ($products) {
    foreach ($products as $product) {
        $product->process();
    }
    
    // Clear memory
    unset($products);
    gc_collect_cycles();
});
```

## Caching Problems

### Issue: Stale cache data

**Symptoms:**
- Outdated information being returned
- Changes not reflected immediately

**Solution:**
1. **Clear specific cache:**
```php
// Clear all product cache
Cache::tags(['products'])->flush();

// Clear specific product cache
Cache::forget("product_{$productId}");

// Clear schema cache
Cache::tags(['openapi', 'schemas'])->flush();
```

2. **Implement cache invalidation:**
```php
class Product extends ApiModel
{
    protected static function booted()
    {
        static::saved(function ($product) {
            $product->clearCache();
        });
        
        static::deleted(function ($product) {
            $product->clearCache();
        });
    }
    
    public function clearCache()
    {
        Cache::tags([
            'products',
            "product_{$this->id}",
            "category_{$this->category_id}"
        ])->flush();
    }
}
```

### Issue: Cache configuration problems

**Symptoms:**
- Cache not working
- Performance not improving

**Debugging:**
```php
// Test cache functionality
Cache::put('test_key', 'test_value', 60);
$value = Cache::get('test_key');
echo $value === 'test_value' ? 'Cache working' : 'Cache not working';

// Check cache driver
echo 'Cache driver: ' . config('cache.default');

// Test Redis connection (if using Redis)
try {
    Redis::ping();
    echo 'Redis connected';
} catch (Exception $e) {
    echo 'Redis error: ' . $e->getMessage();
}
```

## Network and Connectivity

### Issue: Connection timeouts

**Symptoms:**
```
cURL error 28: Operation timed out
```

**Solution:**
1. **Increase timeout values:**
```php
// config/api-client.php
'connection' => [
    'timeout' => 60, // Increase from default 30
    'connect_timeout' => 10,
    'retry_attempts' => 3,
],
```

2. **Implement retry logic:**
```php
class ApiModel extends BaseApiModel
{
    protected function makeApiRequest(string $method, string $endpoint, array $data = []): array
    {
        $maxRetries = 3;
        $retryDelay = 1; // seconds
        
        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                return parent::makeApiRequest($method, $endpoint, $data);
            } catch (ConnectionException $e) {
                if ($attempt === $maxRetries) {
                    throw $e;
                }
                
                sleep($retryDelay * $attempt); // Exponential backoff
            }
        }
    }
}
```

### Issue: SSL certificate problems

**Symptoms:**
```
cURL error 60: SSL certificate problem
```

**Solution:**
1. **For development (not recommended for production):**
```php
// config/api-client.php
'connection' => [
    'verify_ssl' => false, // Only for development
],
```

2. **For production (recommended):**
```bash
# Update CA certificates
sudo apt-get update && sudo apt-get install ca-certificates

# Or specify certificate bundle
curl_setopt($ch, CURLOPT_CAINFO, '/path/to/cacert.pem');
```

## Debugging Tools

### 1. Debug Mode

Enable debug mode for detailed error information:

```php
// config/api-client.php
'debug' => [
    'enabled' => env('API_CLIENT_DEBUG', false),
    'log_requests' => true,
    'log_responses' => true,
    'log_validation' => true,
],
```

### 2. Schema Validator

Create a schema validation tool:

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use MTechStack\LaravelApiModelClient\OpenApi\OpenApiSchemaParser;

class ValidateSchema extends Command
{
    protected $signature = 'api:validate-schema {schema?}';
    protected $description = 'Validate OpenAPI schema';

    public function handle()
    {
        $schemaName = $this->argument('schema') ?? 'primary';
        $config = config("api-client.schemas.{$schemaName}");
        
        if (!$config) {
            $this->error("Schema '{$schemaName}' not found");
            return 1;
        }
        
        try {
            $parser = new OpenApiSchemaParser();
            $result = $parser->parse($config['source']);
            
            $this->info("✓ Schema validation successful");
            $this->line("Endpoints found: " . count($result['endpoints']));
            $this->line("Schemas found: " . count($result['schemas']));
            
            return 0;
        } catch (\Exception $e) {
            $this->error("✗ Schema validation failed: " . $e->getMessage());
            return 1;
        }
    }
}
```

### 3. API Connection Tester

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TestApiConnection extends Command
{
    protected $signature = 'api:test-connection {schema?}';
    protected $description = 'Test API connection';

    public function handle()
    {
        $schemaName = $this->argument('schema') ?? 'primary';
        $config = config("api-client.schemas.{$schemaName}");
        
        $this->info("Testing connection to: " . $config['base_url']);
        
        try {
            $response = Http::timeout(10)
                ->withHeaders($this->getHeaders($config))
                ->get($config['base_url'] . '/health');
            
            if ($response->successful()) {
                $this->info("✓ Connection successful");
                $this->line("Status: " . $response->status());
                $this->line("Response time: " . $response->transferStats->getTransferTime() . "s");
            } else {
                $this->warn("Connection returned status: " . $response->status());
            }
        } catch (\Exception $e) {
            $this->error("✗ Connection failed: " . $e->getMessage());
        }
    }
    
    private function getHeaders(array $config): array
    {
        $auth = $config['authentication'] ?? [];
        
        switch ($auth['type'] ?? '') {
            case 'bearer':
                return ['Authorization' => 'Bearer ' . $auth['token']];
            case 'api_key':
                return [$auth['key'] => $auth['value']];
            default:
                return [];
        }
    }
}
```

## Error Messages Reference

### Common Error Codes

| Error Code | Message | Cause | Solution |
|------------|---------|-------|----------|
| 400 | Bad Request | Invalid request data | Check request parameters and validation |
| 401 | Unauthorized | Invalid or missing authentication | Verify API credentials |
| 403 | Forbidden | Insufficient permissions | Check API permissions and scopes |
| 404 | Not Found | Resource doesn't exist | Verify endpoint URL and resource ID |
| 422 | Unprocessable Entity | Validation failed | Check validation rules and data format |
| 429 | Too Many Requests | Rate limit exceeded | Implement rate limiting and backoff |
| 500 | Internal Server Error | Server-side error | Check server logs and contact API provider |
| 503 | Service Unavailable | Service temporarily down | Implement retry logic with backoff |

### Package-Specific Errors

| Exception | Cause | Solution |
|-----------|-------|----------|
| `OpenApiParsingException` | Schema parsing failed | Validate schema format and accessibility |
| `SchemaValidationException` | Invalid OpenAPI schema | Check schema compliance with OpenAPI spec |
| `ConfigurationException` | Invalid configuration | Review configuration settings |
| `ValidationException` | Parameter validation failed | Check validation rules and input data |

## Getting Help

### 1. Enable Debug Logging

```php
// config/logging.php
'channels' => [
    'api_debug' => [
        'driver' => 'daily',
        'path' => storage_path('logs/api-debug.log'),
        'level' => 'debug',
        'days' => 7,
    ],
],

// Use in your models
Log::channel('api_debug')->info('Debug info', $data);
```

### 2. Create Minimal Reproduction

When reporting issues, create a minimal example:

```php
<?php
// minimal-reproduction.php

require_once 'vendor/autoload.php';

use MTechStack\LaravelApiModelClient\Models\ApiModel;
use MTechStack\LaravelApiModelClient\Traits\HasOpenApiSchema;

class TestModel extends ApiModel
{
    use HasOpenApiSchema;
    
    protected string $openApiSchemaSource = 'test';
    protected string $endpoint = '/test';
}

// Reproduce the issue
try {
    $model = new TestModel();
    $result = $model->validateParameters(['test' => 'data']);
    var_dump($result);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}
```

### 3. Gather System Information

```php
<?php
// system-info.php

echo "PHP Version: " . PHP_VERSION . "\n";
echo "Laravel Version: " . app()->version() . "\n";
echo "Package Version: " . \Composer\InstalledVersions::getVersion('m-tech-stack/laravel-api-model-client') . "\n";
echo "OpenAPI Package: " . \Composer\InstalledVersions::getVersion('cebe/php-openapi') . "\n";
echo "Memory Limit: " . ini_get('memory_limit') . "\n";
echo "Max Execution Time: " . ini_get('max_execution_time') . "\n";

// Test basic functionality
try {
    $parser = new \MTechStack\LaravelApiModelClient\OpenApi\OpenApiSchemaParser();
    echo "✓ OpenAPI parser loads successfully\n";
} catch (Exception $e) {
    echo "✗ OpenAPI parser error: " . $e->getMessage() . "\n";
}
```

### 4. Community Resources

- **GitHub Issues**: Report bugs and feature requests
- **Documentation**: Check the latest documentation
- **Stack Overflow**: Tag questions with `laravel-api-model-client`
- **Laravel Community**: Discuss in Laravel forums and Discord

When seeking help, always include:
- Your Laravel and PHP versions
- Package version
- Minimal code reproduction
- Full error messages and stack traces
- Relevant configuration
- Steps to reproduce the issue

This troubleshooting guide should help you resolve most common issues. If you encounter problems not covered here, please create a GitHub issue with detailed information.
