<?php

namespace MTechStack\LaravelApiModelClient\Traits;

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use MTechStack\LaravelApiModelClient\OpenApi\OpenApiSchemaParser;
use MTechStack\LaravelApiModelClient\OpenApi\Facades\OpenApi;

/**
 * Trait for integrating OpenAPI schema support into ApiModel
 */
trait HasOpenApiSchema
{
    /**
     * OpenAPI schema definition for this model
     */
    protected ?array $openApiSchema = null;

    /**
     * Parsed OpenAPI data cache
     */
    protected static array $openApiCache = [];

    /**
     * OpenAPI schema source (file path or URL)
     */
    protected ?string $openApiSchemaSource = null;

    /**
     * Model name in OpenAPI schema
     */
    protected ?string $openApiModelName = null;

    /**
     * Initialize OpenAPI schema integration
     */
    protected function initializeHasOpenApiSchema(): void
    {
        $this->loadOpenApiSchema();
        $this->applyOpenApiConfiguration();
    }

    /**
     * Load OpenAPI schema for this model
     */
    protected function loadOpenApiSchema(): void
    {
        if ($this->openApiSchema !== null) {
            return; // Already loaded
        }

        $cacheKey = $this->getOpenApiCacheKey();
        
        // Try to get from static cache first
        if (isset(static::$openApiCache[$cacheKey])) {
            $this->openApiSchema = static::$openApiCache[$cacheKey];
            return;
        }

        // Try to get from Laravel cache
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            $this->openApiSchema = $cached;
            static::$openApiCache[$cacheKey] = $cached;
            return;
        }

        // Load and parse schema
        $this->parseOpenApiSchema();
    }

    /**
     * Parse OpenAPI schema from source
     */
    protected function parseOpenApiSchema(): void
    {
        $source = $this->getOpenApiSchemaSource();
        if (!$source) {
            return;
        }

        try {
            $parser = app(OpenApiSchemaParser::class);
            $result = $parser->parse($source);
            
            $modelName = $this->getOpenApiModelName();
            $modelMapping = $result['model_mappings'][$modelName] ?? null;
            
            if ($modelMapping) {
                $this->openApiSchema = [
                    'model_mapping' => $modelMapping,
                    'validation_rules' => $result['validation_rules']['schemas'][$modelName] ?? [],
                    'endpoints' => $this->extractModelEndpoints($result['endpoints'], $modelMapping),
                    'schemas' => $result['schemas'],
                ];

                // Cache the result
                $cacheKey = $this->getOpenApiCacheKey();
                Cache::put($cacheKey, $this->openApiSchema, config('openapi.cache.ttl', 3600));
                static::$openApiCache[$cacheKey] = $this->openApiSchema;
            }
        } catch (\Exception $e) {
            \Log::warning("Failed to load OpenAPI schema for " . static::class, [
                'source' => $source,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Apply OpenAPI configuration to the model
     */
    protected function applyOpenApiConfiguration(): void
    {
        if (!$this->hasOpenApiSchema()) {
            return;
        }

        $this->applyOpenApiFillable();
        $this->applyOpenApiCasts();
        $this->applyOpenApiEndpoint();
    }

    /**
     * Apply fillable attributes from OpenAPI schema
     */
    protected function applyOpenApiFillable(): void
    {
        if (!empty($this->fillable)) {
            return; // Don't override if already set
        }

        $attributes = $this->getOpenApiAttributes();
        $fillable = [];

        foreach ($attributes as $attribute) {
            if ($attribute['name'] !== 'id' && !($attribute['readOnly'] ?? false)) {
                $fillable[] = $attribute['name'];
            }
        }

        if (!empty($fillable)) {
            $this->fillable = $fillable;
        }
    }

    /**
     * Apply casts from OpenAPI schema
     */
    protected function applyOpenApiCasts(): void
    {
        $attributes = $this->getOpenApiAttributes();
        $casts = $this->casts ?? [];

        foreach ($attributes as $attribute) {
            $name = $attribute['name'];
            if (isset($casts[$name])) {
                continue; // Don't override existing casts
            }

            $cast = $this->getOpenApiCastType($attribute);
            if ($cast) {
                $casts[$name] = $cast;
            }
        }

        $this->casts = $casts;
    }

    /**
     * Apply API endpoint from OpenAPI schema
     */
    protected function applyOpenApiEndpoint(): void
    {
        if (property_exists($this, 'apiEndpoint') && !empty($this->apiEndpoint)) {
            return; // Don't override if already set
        }

        $baseEndpoint = $this->getOpenApiBaseEndpoint();
        if ($baseEndpoint) {
            $this->apiEndpoint = $baseEndpoint;
        }
    }

    /**
     * Get OpenAPI cast type for attribute
     */
    protected function getOpenApiCastType(array $attribute): ?string
    {
        $type = $attribute['type'] ?? 'string';
        $format = $attribute['format'] ?? null;

        switch ($type) {
            case 'integer':
                return 'integer';
            case 'number':
                return 'float';
            case 'boolean':
                return 'boolean';
            case 'array':
                return 'array';
            case 'object':
                return 'object';
            case 'string':
                switch ($format) {
                    case 'date':
                        return 'date';
                    case 'date-time':
                        return 'datetime';
                    case 'time':
                        return 'time';
                    default:
                        return null;
                }
            default:
                return null;
        }
    }

    /**
     * Validate parameters against OpenAPI schema
     */
    public function validateParameters(array $parameters, string $operation = null): \Illuminate\Validation\Validator
    {
        $rules = $this->getValidationRules($operation);
        return Validator::make($parameters, $rules);
    }

    /**
     * Get validation rules for operation or model
     */
    public function getValidationRules(string $operation = null): array
    {
        if (!$this->hasOpenApiSchema()) {
            return [];
        }

        if ($operation) {
            return $this->getOperationValidationRules($operation);
        }

        return $this->openApiSchema['validation_rules'] ?? [];
    }

    /**
     * Get validation rules for specific operation
     */
    protected function getOperationValidationRules(string $operation): array
    {
        $endpoints = $this->openApiSchema['endpoints'] ?? [];
        
        foreach ($endpoints as $endpoint) {
            if ($endpoint['type'] === $operation) {
                $operationId = $endpoint['operation_id'];
                return app(OpenApiSchemaParser::class)->getValidationRulesForEndpoint($operationId);
            }
        }

        return [];
    }

    /**
     * Create dynamic query scopes based on schema parameters
     */
    public function scopeWithOpenApiFilters($query, array $filters = [])
    {
        if (!$this->hasOpenApiSchema()) {
            return $query;
        }

        $attributes = $this->getOpenApiAttributes();
        $validFilters = [];

        foreach ($attributes as $attribute) {
            $name = $attribute['name'];
            if (isset($filters[$name])) {
                $validFilters[$name] = $filters[$name];
            }
        }

        foreach ($validFilters as $key => $value) {
            $query->where($key, $value);
        }

        return $query;
    }

    /**
     * Get OpenAPI-defined relationships
     */
    public function getOpenApiRelationships(): array
    {
        if (!$this->hasOpenApiSchema()) {
            return [];
        }

        return $this->openApiSchema['model_mapping']['relationships'] ?? [];
    }

    /**
     * Get OpenAPI attributes
     */
    public function getOpenApiAttributes(): array
    {
        if (!$this->hasOpenApiSchema()) {
            return [];
        }

        return $this->openApiSchema['model_mapping']['attributes'] ?? [];
    }

    /**
     * Get OpenAPI base endpoint
     */
    public function getOpenApiBaseEndpoint(): ?string
    {
        if (!$this->hasOpenApiSchema()) {
            return null;
        }

        return $this->openApiSchema['model_mapping']['base_endpoint'] ?? null;
    }

    /**
     * Get OpenAPI operations
     */
    public function getOpenApiOperations(): array
    {
        if (!$this->hasOpenApiSchema()) {
            return [];
        }

        return $this->openApiSchema['model_mapping']['operations'] ?? [];
    }

    /**
     * Resolve endpoint for operation
     */
    public function resolveEndpointForOperation(string $operation, array $parameters = []): ?string
    {
        $operations = $this->getOpenApiOperations();
        
        foreach ($operations as $op) {
            if ($op['type'] === $operation) {
                $path = $op['path'];
                
                // Replace path parameters
                foreach ($parameters as $key => $value) {
                    $path = str_replace('{' . $key . '}', $value, $path);
                }
                
                return $path;
            }
        }

        return null;
    }

    /**
     * Check if model has OpenAPI schema
     */
    public function hasOpenApiSchema(): bool
    {
        return $this->openApiSchema !== null;
    }

    /**
     * Get OpenAPI schema source
     */
    protected function getOpenApiSchemaSource(): ?string
    {
        // Check property first
        if ($this->openApiSchemaSource) {
            return $this->openApiSchemaSource;
        }

        // Check config for model-specific schema
        $modelClass = static::class;
        $schemas = config('openapi.model_schemas', []);
        
        if (isset($schemas[$modelClass])) {
            return $schemas[$modelClass];
        }

        // Check for default schema
        return config('openapi.default_schema');
    }

    /**
     * Get OpenAPI model name
     */
    protected function getOpenApiModelName(): string
    {
        if ($this->openApiModelName) {
            return $this->openApiModelName;
        }

        return class_basename(static::class);
    }

    /**
     * Get OpenAPI cache key
     */
    protected function getOpenApiCacheKey(): string
    {
        $source = $this->getOpenApiSchemaSource();
        $modelName = $this->getOpenApiModelName();
        
        return 'openapi_model_' . md5($source . '_' . $modelName);
    }

    /**
     * Extract model-specific endpoints from parsed results
     */
    protected function extractModelEndpoints(array $allEndpoints, array $modelMapping): array
    {
        $modelEndpoints = [];
        $operations = $modelMapping['operations'] ?? [];

        foreach ($operations as $operation) {
            $operationId = $operation['operation_id'];
            if (isset($allEndpoints[$operationId])) {
                $modelEndpoints[] = array_merge($operation, [
                    'endpoint_data' => $allEndpoints[$operationId]
                ]);
            }
        }

        return $modelEndpoints;
    }

    /**
     * Create dynamic query methods based on OpenAPI parameters
     */
    public function __call($method, $parameters)
    {
        // Check for OpenAPI-based query scopes
        if ($this->hasOpenApiSchema() && Str::startsWith($method, 'whereBy')) {
            $attribute = Str::snake(substr($method, 7));
            $attributes = collect($this->getOpenApiAttributes())->pluck('name')->toArray();
            
            if (in_array($attribute, $attributes)) {
                return $this->where($attribute, $parameters[0] ?? null);
            }
        }

        // Check for OpenAPI-based relationship methods
        if ($this->hasOpenApiSchema()) {
            $relationships = $this->getOpenApiRelationships();
            foreach ($relationships as $relationship) {
                if ($relationship['name'] === $method) {
                    return $this->createOpenApiRelationship($relationship);
                }
            }
        }

        // Fall back to parent __call
        return parent::__call($method, $parameters);
    }

    /**
     * Create OpenAPI-defined relationship
     */
    protected function createOpenApiRelationship(array $relationship)
    {
        $type = $relationship['type'];
        $relatedModel = $relationship['related_model'] ?? null;

        if (!$relatedModel) {
            return null;
        }

        // Try to resolve the related model class
        $relatedClass = $this->resolveRelatedModelClass($relatedModel);
        if (!$relatedClass) {
            return null;
        }

        switch ($type) {
            case 'belongsTo':
                return $this->belongsTo(
                    $relatedClass,
                    $relationship['foreign_key'] ?? null,
                    $relationship['local_key'] ?? null
                );

            case 'hasMany':
                return $this->hasMany(
                    $relatedClass,
                    $relationship['foreign_key'] ?? null,
                    $relationship['local_key'] ?? null
                );

            case 'hasOne':
                return $this->hasOne(
                    $relatedClass,
                    $relationship['foreign_key'] ?? null,
                    $relationship['local_key'] ?? null
                );

            case 'embedded':
                // For embedded relationships, return the attribute value
                return $this->getAttribute($relationship['name']);

            default:
                return null;
        }
    }

    /**
     * Resolve related model class name
     */
    protected function resolveRelatedModelClass(string $modelName): ?string
    {
        // Try current namespace first
        $currentNamespace = (new \ReflectionClass($this))->getNamespaceName();
        $fullClassName = $currentNamespace . '\\' . $modelName;
        
        if (class_exists($fullClassName)) {
            return $fullClassName;
        }

        // Try App\Models namespace
        $appModelClass = 'App\\Models\\' . $modelName;
        if (class_exists($appModelClass)) {
            return $appModelClass;
        }

        // Try configured namespace
        $configuredNamespace = config('openapi.model_generation.namespace', 'App\\Models');
        $configuredClass = $configuredNamespace . '\\' . $modelName;
        
        if (class_exists($configuredClass)) {
            return $configuredClass;
        }

        return null;
    }

    /**
     * Override getApiEndpoint to use OpenAPI-resolved endpoint
     */
    public function getApiEndpoint(): string
    {
        // If OpenAPI schema is available, use it
        if ($this->hasOpenApiSchema()) {
            $baseEndpoint = $this->getOpenApiBaseEndpoint();
            if ($baseEndpoint) {
                return $baseEndpoint;
            }
        }

        // Fall back to original implementation
        if (property_exists($this, 'apiEndpoint')) {
            return $this->apiEndpoint;
        }

        throw new \RuntimeException('API endpoint not defined for model ' . get_class($this));
    }

    /**
     * Get endpoint for specific operation with parameters
     */
    public function getEndpointForOperation(string $operation, array $parameters = []): ?string
    {
        if (!$this->hasOpenApiSchema()) {
            return null;
        }

        return $this->resolveEndpointForOperation($operation, $parameters);
    }

    /**
     * Validate model data against OpenAPI schema
     */
    public function validateAgainstSchema(array $data = null): \Illuminate\Validation\Validator
    {
        $data = $data ?? $this->getAttributes();
        $rules = $this->getValidationRules();
        
        return Validator::make($data, $rules);
    }

    /**
     * Check if operation is supported by OpenAPI schema
     */
    public function supportsOperation(string $operation): bool
    {
        if (!$this->hasOpenApiSchema()) {
            return false;
        }

        $operations = collect($this->getOpenApiOperations())->pluck('type')->toArray();
        return in_array($operation, $operations);
    }

    /**
     * Get OpenAPI parameter definition for attribute
     */
    public function getOpenApiParameterDefinition(string $attributeName): ?array
    {
        $attributes = $this->getOpenApiAttributes();
        
        foreach ($attributes as $attribute) {
            if ($attribute['name'] === $attributeName) {
                return $attribute;
            }
        }

        return null;
    }
}
