<?php

namespace MTechStack\LaravelApiModelClient\Testing;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use MTechStack\LaravelApiModelClient\Models\ApiModel;
use MTechStack\LaravelApiModelClient\OpenApi\OpenApiSchemaParser;

/**
 * API Test Runner for comprehensive model and endpoint testing
 */
class ApiTestRunner
{
    protected OpenApiSchemaParser $parser;

    public function __construct()
    {
        $this->parser = new OpenApiSchemaParser();
    }

    /**
     * Test API models
     */
    public function testModels(array $models, ?string $schema, bool $dryRun): array
    {
        $results = [];
        
        if (empty($models)) {
            $models = $this->discoverModels($schema);
        }

        foreach ($models as $modelName) {
            if ($dryRun) {
                $results[$modelName] = [
                    'success' => true,
                    'dry_run' => true,
                    'tests' => [
                        'instantiation' => ['success' => true, 'dry_run' => true],
                        'configuration' => ['success' => true, 'dry_run' => true],
                        'query_builder' => ['success' => true, 'dry_run' => true],
                        'relationships' => ['success' => true, 'dry_run' => true],
                    ],
                ];
                continue;
            }

            $results[$modelName] = $this->testModel($modelName, $schema);
        }

        return $results;
    }

    /**
     * Test API endpoints
     */
    public function testEndpoints(array $endpoints, ?string $schema, int $timeout, bool $dryRun): array
    {
        $results = [];
        
        if (empty($endpoints)) {
            $endpoints = $this->discoverEndpoints($schema);
        }

        foreach ($endpoints as $endpoint) {
            if ($dryRun) {
                $results[$endpoint] = [
                    'success' => true,
                    'dry_run' => true,
                    'method' => 'GET',
                    'url' => 'https://api.example.com' . $endpoint,
                ];
                continue;
            }

            $results[$endpoint] = $this->testEndpoint($endpoint, $schema, $timeout);
        }

        return $results;
    }

    /**
     * Test individual model
     */
    protected function testModel(string $modelName, ?string $schema): array
    {
        $tests = [
            'instantiation' => $this->testModelInstantiation($modelName),
            'configuration' => $this->testModelConfiguration($modelName, $schema),
            'query_builder' => $this->testModelQueryBuilder($modelName),
            'relationships' => $this->testModelRelationships($modelName),
            'validation' => $this->testModelValidation($modelName),
            'openapi_integration' => $this->testModelOpenApiIntegration($modelName, $schema),
        ];

        $success = collect($tests)->every(fn($test) => $test['success']);

        return [
            'success' => $success,
            'tests' => $tests,
            'model_class' => $this->getModelClass($modelName),
        ];
    }

    /**
     * Test model instantiation
     */
    protected function testModelInstantiation(string $modelName): array
    {
        try {
            $modelClass = $this->getModelClass($modelName);
            
            if (!class_exists($modelClass)) {
                return [
                    'success' => false,
                    'error' => "Model class {$modelClass} does not exist",
                ];
            }

            $model = new $modelClass();
            
            if (!$model instanceof ApiModel) {
                return [
                    'success' => false,
                    'error' => "Model {$modelClass} is not an instance of ApiModel",
                ];
            }

            return [
                'success' => true,
                'model_class' => $modelClass,
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Test model configuration
     */
    protected function testModelConfiguration(string $modelName, ?string $schema): array
    {
        try {
            $modelClass = $this->getModelClass($modelName);
            $model = new $modelClass();

            $tests = [
                'has_endpoint' => !empty($model->getEndpoint()),
                'has_primary_key' => !empty($model->getKeyName()),
                'has_fillable' => !empty($model->getFillable()),
            ];

            // Test schema-specific configuration
            if ($schema) {
                $schemaConfig = Config::get("api-client.schemas.{$schema}");
                if ($schemaConfig) {
                    $tests['schema_configured'] = true;
                    $tests['base_url_configured'] = !empty($schemaConfig['base_url']);
                }
            }

            $success = collect($tests)->every();

            return [
                'success' => $success,
                'tests' => $tests,
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Test model query builder
     */
    protected function testModelQueryBuilder(string $modelName): array
    {
        try {
            $modelClass = $this->getModelClass($modelName);
            $model = new $modelClass();

            $tests = [
                'can_create_query' => method_exists($model, 'query'),
                'has_where_methods' => method_exists($model, 'where'),
                'has_order_methods' => method_exists($model, 'orderBy'),
                'has_limit_methods' => method_exists($model, 'limit'),
            ];

            // Test query builder instantiation
            try {
                $query = $model::query();
                $tests['query_builder_works'] = true;
            } catch (\Exception $e) {
                $tests['query_builder_works'] = false;
                $tests['query_builder_error'] = $e->getMessage();
            }

            $success = collect($tests)->filter(fn($value, $key) => !str_ends_with($key, '_error'))->every();

            return [
                'success' => $success,
                'tests' => $tests,
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Test model relationships
     */
    protected function testModelRelationships(string $modelName): array
    {
        try {
            $modelClass = $this->getModelClass($modelName);
            $model = new $modelClass();

            $relationships = [];
            $reflection = new \ReflectionClass($model);
            
            foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                if ($method->getNumberOfParameters() === 0 && 
                    !in_array($method->getName(), ['getKey', 'getTable', 'getKeyName']) &&
                    !str_starts_with($method->getName(), 'get') &&
                    !str_starts_with($method->getName(), 'set')) {
                    
                    try {
                        $result = $method->invoke($model);
                        if (is_object($result) && method_exists($result, 'getRelated')) {
                            $relationships[$method->getName()] = [
                                'type' => class_basename(get_class($result)),
                                'related' => get_class($result->getRelated()),
                            ];
                        }
                    } catch (\Exception $e) {
                        // Ignore relationship method errors for now
                    }
                }
            }

            return [
                'success' => true,
                'relationships_count' => count($relationships),
                'relationships' => $relationships,
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Test model validation
     */
    protected function testModelValidation(string $modelName): array
    {
        try {
            $modelClass = $this->getModelClass($modelName);
            $model = new $modelClass();

            $tests = [
                'has_validation_rules' => method_exists($model, 'rules') || method_exists($model, 'getValidationRules'),
                'has_fillable_validation' => !empty($model->getFillable()),
            ];

            // Test validation method if it exists
            if (method_exists($model, 'validateParameters')) {
                try {
                    $model->validateParameters([], 'create');
                    $tests['validation_method_works'] = true;
                } catch (\Exception $e) {
                    $tests['validation_method_works'] = false;
                    $tests['validation_error'] = $e->getMessage();
                }
            }

            $success = collect($tests)->filter(fn($value, $key) => !str_ends_with($key, '_error'))->every();

            return [
                'success' => $success,
                'tests' => $tests,
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Test model OpenAPI integration
     */
    protected function testModelOpenApiIntegration(string $modelName, ?string $schema): array
    {
        try {
            $modelClass = $this->getModelClass($modelName);
            $model = new $modelClass();

            $tests = [
                'has_openapi_trait' => $this->hasOpenApiTrait($model),
                'has_schema_source' => property_exists($model, 'openApiSchemaSource'),
            ];

            // Test OpenAPI-specific methods
            if ($this->hasOpenApiTrait($model)) {
                $tests['has_openapi_methods'] = method_exists($model, 'getOpenApiSchema');
                
                if (method_exists($model, 'whereOpenApi')) {
                    $tests['has_openapi_query_methods'] = true;
                }
            }

            $success = collect($tests)->every();

            return [
                'success' => $success,
                'tests' => $tests,
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Test individual endpoint
     */
    protected function testEndpoint(string $endpoint, ?string $schema, int $timeout): array
    {
        try {
            $schemaConfig = $schema ? Config::get("api-client.schemas.{$schema}") : null;
            $baseUrl = $schemaConfig['base_url'] ?? Config::get('api-client.base_url');
            
            if (!$baseUrl) {
                return [
                    'success' => false,
                    'error' => 'No base URL configured',
                ];
            }

            $url = rtrim($baseUrl, '/') . '/' . ltrim($endpoint, '/');
            $headers = $this->getAuthHeaders($schemaConfig ?? []);

            $startTime = microtime(true);
            
            $response = Http::timeout($timeout)
                ->withHeaders($headers)
                ->get($url);
            
            $endTime = microtime(true);
            $responseTime = ($endTime - $startTime) * 1000;

            return [
                'success' => $response->successful(),
                'status_code' => $response->status(),
                'response_time_ms' => round($responseTime, 2),
                'url' => $url,
                'method' => 'GET',
                'response_size' => strlen($response->body()),
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'url' => $url ?? $endpoint,
            ];
        }
    }

    /**
     * Discover available models
     */
    protected function discoverModels(?string $schema): array
    {
        $models = [];
        
        // Try to discover from schema if available
        if ($schema) {
            $schemaConfig = Config::get("api-client.schemas.{$schema}");
            if ($schemaConfig && isset($schemaConfig['source'])) {
                try {
                    $parsed = $this->parser->parse($schemaConfig['source'], false);
                    $models = array_keys($parsed['schemas'] ?? []);
                } catch (\Exception $e) {
                    // Fallback to default models
                }
            }
        }
        
        // Fallback to common model names
        if (empty($models)) {
            $models = ['Pet', 'Category', 'Tag', 'User', 'Product', 'Order'];
        }
        
        return $models;
    }

    /**
     * Discover available endpoints
     */
    protected function discoverEndpoints(?string $schema): array
    {
        $endpoints = [];
        
        // Try to discover from schema if available
        if ($schema) {
            $schemaConfig = Config::get("api-client.schemas.{$schema}");
            if ($schemaConfig && isset($schemaConfig['source'])) {
                try {
                    $parsed = $this->parser->parse($schemaConfig['source'], false);
                    $endpoints = array_keys($parsed['endpoints'] ?? []);
                } catch (\Exception $e) {
                    // Fallback to default endpoints
                }
            }
        }
        
        // Fallback to common endpoints
        if (empty($endpoints)) {
            $endpoints = ['/pets', '/categories', '/tags', '/users', '/products', '/orders'];
        }
        
        return $endpoints;
    }

    /**
     * Get model class name
     */
    protected function getModelClass(string $modelName): string
    {
        // Try different namespace patterns
        $namespaces = [
            'App\\Models\\Api\\',
            'App\\Models\\',
            'MTechStack\\LaravelApiModelClient\\Models\\',
        ];

        foreach ($namespaces as $namespace) {
            $class = $namespace . $modelName;
            if (class_exists($class)) {
                return $class;
            }
        }

        // Default to App\Models\Api namespace
        return 'App\\Models\\Api\\' . $modelName;
    }

    /**
     * Check if model has OpenAPI trait
     */
    protected function hasOpenApiTrait($model): bool
    {
        $traits = class_uses_recursive(get_class($model));
        return in_array('MTechStack\\LaravelApiModelClient\\Traits\\HasOpenApiSchema', $traits);
    }

    /**
     * Get authentication headers
     */
    protected function getAuthHeaders(array $schemaConfig): array
    {
        $auth = $schemaConfig['authentication'] ?? [];
        
        switch ($auth['type'] ?? '') {
            case 'bearer':
                return ['Authorization' => 'Bearer ' . ($auth['token'] ?? '')];
            case 'api_key':
                $key = $auth['key'] ?? 'X-API-Key';
                $value = $auth['value'] ?? $auth['token'] ?? '';
                return [$key => $value];
            case 'basic':
                $username = $auth['username'] ?? '';
                $password = $auth['password'] ?? '';
                return ['Authorization' => 'Basic ' . base64_encode("{$username}:{$password}")];
            default:
                return [];
        }
    }
}
