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
        if ($this->getSchemaValue() !== null) {
            return; // Already loaded
        }

        $cacheKey = $this->getOpenApiCacheKey();
        
        // Try to get from static cache first
        if (isset(static::$openApiCache[$cacheKey])) {
            $this->setSchemaValue(static::$openApiCache[$cacheKey]);
            return;
        }

        // Try to get from Laravel cache
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            $this->setSchemaValue($cached);
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
                $schemaArr = [
                    'model_mapping' => $modelMapping,
                    'validation_rules' => $result['validation_rules']['schemas'][$modelName] ?? [],
                    'endpoints' => $this->extractModelEndpoints($result['endpoints'], $modelMapping),
                    'schemas' => $result['schemas'],
                ];

                $this->setSchemaValue($schemaArr);
                // Cache the result
                $cacheKey = $this->getOpenApiCacheKey();
                Cache::put($cacheKey, $schemaArr, config('openapi.cache.ttl', 3600));
                static::$openApiCache[$cacheKey] = $schemaArr;
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
     * Get the currently loaded OpenAPI schema (raw or parsed)
     */
    public function getOpenApiSchema(): ?array
    {
        if ($this->getSchemaValue() === null) {
            $this->loadOpenApiSchema();
        }
        $schema = $this->getSchemaValue();
        // Cache the raw document for quick reuse in tests/tools
        if (is_array($schema) && isset($schema['openapi'])) {
            $cacheKey = 'openapi_schema_' . md5(json_encode($schema));
            if (!Cache::has($cacheKey)) {
                Cache::put($cacheKey, $schema, config('openapi.cache.ttl', 3600));
            }
        }
        return $schema;
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
     * Compute fillable attributes from the OpenAPI schema
     */
    public function getOpenApiFillable(): array
    {
        $attributes = $this->getOpenApiAttributes();
        return collect($attributes)
            ->filter(fn ($attr) => ($attr['name'] ?? null) !== 'id' && !($attr['readOnly'] ?? false))
            ->pluck('name')
            ->values()
            ->toArray();
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
     * Compute cast mappings inferred from the OpenAPI schema
     */
    public function getOpenApiCasts(): array
    {
        $attributes = $this->getOpenApiAttributes();
        $casts = [];
        foreach ($attributes as $attribute) {
            $cast = $this->getOpenApiCastType($attribute);
            if ($cast) {
                $casts[$attribute['name']] = $cast;
            }
        }
        return $casts;
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
                        return 'string';
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
        if (is_array($this->openApiSchema) && isset($this->openApiSchema['model_mapping'])) {
            return $this->openApiSchema['validation_rules'] ?? [];
        }

        // Raw schema: derive from component schema
        $modelSchema = $this->getRawModelComponentSchema();
        return $this->buildValidationRulesFromModelSchema($modelSchema);
    }

    /**
     * Get validation rules for specific operation
     */
    protected function getOperationValidationRules(string $operation): array
    {
        if (is_array($this->openApiSchema) && isset($this->openApiSchema['model_mapping'])) {
            $endpoints = $this->openApiSchema['endpoints'] ?? [];
            foreach ($endpoints as $endpoint) {
                if ($endpoint['type'] === $operation) {
                    $operationId = $endpoint['operation_id'];
                    return app(OpenApiSchemaParser::class)->getValidationRulesForEndpoint($operationId);
                }
            }
            return [];
        }

        // Raw schema: build for create/update bodies
        $doc = $this->openApiSchema;
        foreach (($doc['paths'] ?? []) as $path => $ops) {
            foreach ($ops as $method => $op) {
                $m = strtolower($method);
                $opType = match ($m) {
                    'post' => 'create',
                    'put', 'patch' => 'update',
                    'get' => (str_contains($path, '{') ? 'show' : 'index'),
                    'delete' => 'delete',
                    default => null,
                };
                if ($opType !== $operation) {
                    continue;
                }
                $schema = $op['requestBody']['content']['application/json']['schema'] ?? null;
                if (!$schema) {
                    return [];
                }
                if (isset($schema['$ref'])) {
                    $modelSchema = $this->getRawModelComponentSchema($this->schemaRefToName($schema['$ref']));
                } else {
                    $modelSchema = $schema;
                }
                return $this->buildValidationRulesFromModelSchema($modelSchema);
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
        // Parsed schema
        if (is_array($this->openApiSchema) && isset($this->openApiSchema['model_mapping'])) {
            return $this->openApiSchema['model_mapping']['relationships'] ?? [];
        }

        // Raw schema fallback: infer from component properties
        $modelSchema = $this->getRawModelComponentSchema();
        if (!$modelSchema) {
            return [];
        }

        $relationships = [];
        foreach (($modelSchema['properties'] ?? []) as $name => $prop) {
            if (($prop['type'] ?? null) === 'array' && isset($prop['items']['$ref'])) {
                $relationships[] = [
                    'name' => $name,
                    'type' => 'hasMany',
                    'related_model' => $this->schemaRefToName($prop['items']['$ref']),
                ];
            } elseif (isset($prop['$ref'])) {
                $relationships[] = [
                    'name' => $name,
                    'type' => 'belongsTo',
                    'related_model' => $this->schemaRefToName($prop['$ref']),
                ];
            }
        }

        return $relationships;
    }

    /**
     * Get OpenAPI attributes
     */
    public function getOpenApiAttributes(): array
    {
        if (!$this->hasOpenApiSchema()) {
            return [];
        }
        // Parsed schema
        if (is_array($this->openApiSchema) && isset($this->openApiSchema['model_mapping'])) {
            return $this->openApiSchema['model_mapping']['attributes'] ?? [];
        }

        // Raw schema: build from component schema
        $modelSchema = $this->getRawModelComponentSchema();
        if (!$modelSchema) {
            return [];
        }

        $attributes = [];
        foreach (($modelSchema['properties'] ?? []) as $name => $schema) {
            $attr = ['name' => $name];
            if (isset($schema['$ref'])) {
                $attr['type'] = 'object';
                $attr['$ref'] = $schema['$ref'];
            } else {
                foreach (['type', 'format', 'enum', 'maxLength', 'minLength', 'readOnly'] as $key) {
                    if (isset($schema[$key])) {
                        if ($key === 'maxLength') {
                            $attr['max_length'] = $schema[$key];
                        } elseif ($key === 'minLength') {
                            $attr['min_length'] = $schema[$key];
                        } else {
                            $attr[$key] = $schema[$key];
                        }
                    }
                }
                if (($schema['type'] ?? null) === 'array' && isset($schema['items'])) {
                    $attr['items'] = $schema['items'];
                }
            }
            $attributes[] = $attr;
        }

        return $attributes;
    }

    /**
     * Get OpenAPI base endpoint
     */
    public function getOpenApiBaseEndpoint(): ?string
    {
        if (!$this->hasOpenApiSchema()) {
            return null;
        }
        if (is_array($this->openApiSchema) && isset($this->openApiSchema['model_mapping'])) {
            return $this->openApiSchema['model_mapping']['base_endpoint'] ?? null;
        }

        // Raw schema: infer from paths referencing this model
        $doc = $this->openApiSchema;
        $modelName = $this->getRawModelName();
        $paths = $doc['paths'] ?? [];
        $candidates = [];
        foreach ($paths as $path => $ops) {
            foreach ($ops as $op) {
                $reqRef = $op['requestBody']['content']['application/json']['schema']['$ref'] ?? null;
                $resSchema = $op['responses']['200']['content']['application/json']['schema'] ?? null;
                $resRef = isset($resSchema['$ref']) ? $resSchema['$ref'] : ($resSchema['items']['$ref'] ?? null);
                if (($reqRef && $this->schemaRefToName($reqRef) === $modelName) ||
                    ($resRef && $this->schemaRefToName($resRef) === $modelName)) {
                    $candidates[] = $path;
                }
            }
        }
        $base = collect($candidates)
            ->filter(fn ($p) => !str_contains($p, '{'))
            ->sortBy(fn ($p) => strlen($p))
            ->first();
        return $base ?: (collect($candidates)->sortBy(fn ($p) => strlen($p))->first() ?? null);
    }

    /**
     * Get OpenAPI operations
     */
    public function getOpenApiOperations(): array
    {
        if (!$this->hasOpenApiSchema()) {
            return [];
        }
        if (is_array($this->openApiSchema) && isset($this->openApiSchema['model_mapping'])) {
            return $this->openApiSchema['model_mapping']['operations'] ?? [];
        }

        // Raw schema: infer common operations
        $doc = $this->openApiSchema;
        $paths = $doc['paths'] ?? [];
        $operations = [];
        $base = $this->getOpenApiBaseEndpoint();

        foreach ($paths as $path => $ops) {
            // If we know the base, restrict to paths under it
            if ($base && !str_starts_with($path, $base)) {
                continue;
            }
            foreach ($ops as $method => $op) {
                $method = strtolower($method);
                $type = null;
                if ($method === 'get' && str_contains($path, '{')) {
                    $type = 'show';
                } elseif ($method === 'get') {
                    $type = 'index';
                } elseif ($method === 'post') {
                    $type = 'create';
                } elseif (in_array($method, ['put', 'patch'])) {
                    $type = 'update';
                } elseif ($method === 'delete') {
                    $type = 'delete';
                }

                if ($type) {
                    $operations[] = [
                        'type' => $type,
                        'path' => $path,
                        'operation_id' => $op['operationId'] ?? ($method . '_' . trim($path, '/')),
                        'endpoint_data' => $op,
                    ];
                }
            }
        }

        return $operations;
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
     * Convenience alias used in tests/user-land
     */
    public function resolveOpenApiEndpoint(string $operation): ?string
    {
        return $this->resolveEndpointForOperation($operation);
    }

    /**
     * Check if model has OpenAPI schema
     */
    public function hasOpenApiSchema(): bool
    {
        if ($this->getSchemaValue() === null) {
            // Attempt to lazy-load if a source is available (supports test overrides)
            $this->loadOpenApiSchema();
        }
        $schema = $this->getSchemaValue();
        // Validate raw document structure if present
        if (is_array($schema) && isset($schema['openapi'])) {
            if (!$this->isValidRawOpenApiDoc($schema)) {
                return false;
            }
        }
        return $schema !== null;
    }

    /** Safely get/set schema property (handles uninitialized typed properties in subclasses) */
    protected function getSchemaValue(): ?array
    {
        try {
            $rp = new \ReflectionProperty($this, 'openApiSchema');
            if (!$rp->isInitialized($this)) {
                return null;
            }
            return $rp->getValue($this);
        } catch (\ReflectionException $e) {
            return $this->openApiSchema ?? null;
        }
    }

    protected function setSchemaValue(?array $schema): void
    {
        try {
            $rp = new \ReflectionProperty($this, 'openApiSchema');
            $rp->setAccessible(true);
            $rp->setValue($this, $schema);
        } catch (\ReflectionException $e) {
            $this->openApiSchema = $schema;
        }
    }

    /**
     * Minimal validation for a raw OpenAPI document
     */
    protected function isValidRawOpenApiDoc(array $doc): bool
    {
        if (!isset($doc['paths']) || !is_array($doc['paths'])) {
            return false;
        }
        if (empty($doc['paths'])) {
            return false;
        }
        if (!isset($doc['info']) || !is_array($doc['info'])) {
            return false;
        }
        return true;
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
     * Get all OpenAPI parameter definitions aggregated across operations
     */
    public function getOpenApiParameterDefinitions(): array
    {
        if (!$this->hasOpenApiSchema()) {
            return [];
        }
        $definitions = [];

        if (is_array($this->openApiSchema) && isset($this->openApiSchema['model_mapping'])) {
            $endpoints = $this->openApiSchema['endpoints'] ?? [];
            foreach ($endpoints as $endpoint) {
                $endpointData = $endpoint['endpoint_data'] ?? [];
                $params = $endpointData['parameters'] ?? [];
                foreach ($params as $param) {
                    $name = $param['name'] ?? null;
                    if (!$name) {
                        continue;
                    }
                    $schema = $param['schema'] ?? [];
                    $definitions[$name] = array_merge($schema, [
                        'in' => $param['in'] ?? 'query',
                        'required' => $param['required'] ?? false,
                        'description' => $param['description'] ?? null,
                        'style' => $param['style'] ?? 'simple',
                        'explode' => $param['explode'] ?? false,
                    ]);
                }
            }
            return $definitions;
        }

        // Raw schema: collect from relevant operations
        $doc = $this->openApiSchema;
        $modelName = $this->getRawModelName();
        foreach (($doc['paths'] ?? []) as $path => $ops) {
            foreach ($ops as $op) {
                $reqRef = $op['requestBody']['content']['application/json']['schema']['$ref'] ?? null;
                $resSchema = $op['responses']['200']['content']['application/json']['schema'] ?? null;
                $resRef = isset($resSchema['$ref']) ? $resSchema['$ref'] : ($resSchema['items']['$ref'] ?? null);
                $matches = ($reqRef && $this->schemaRefToName($reqRef) === $modelName) ||
                           ($resRef && $this->schemaRefToName($resRef) === $modelName);
                if (!$matches) {
                    continue;
                }
                foreach (($op['parameters'] ?? []) as $param) {
                    $name = $param['name'] ?? null;
                    if (!$name) {
                        continue;
                    }
                    $schema = $param['schema'] ?? [];
                    $definitions[$name] = array_merge($schema, [
                        'in' => $param['in'] ?? 'query',
                        'required' => $param['required'] ?? false,
                        'description' => $param['description'] ?? null,
                        'style' => $param['style'] ?? 'simple',
                        'explode' => $param['explode'] ?? false,
                    ]);
                }
            }
        }

        return $definitions;
    }

    /**
     * Get a single OpenAPI parameter definition by name
     */
    public function getOpenApiParameterDefinition(string $attributeName): ?array
    {
        if (!$this->hasOpenApiSchema()) {
            return null;
        }

        $definitions = $this->getOpenApiParameterDefinitions();
        return $definitions[$attributeName] ?? null;
    }

    /**
     * Whether the loaded schema is parser-produced (has model_mapping)
     */
    protected function isParsedOpenApiSchema(): bool
    {
        return is_array($this->openApiSchema) && isset($this->openApiSchema['model_mapping']);
    }

    /**
     * Convert a schema $ref (e.g., #/components/schemas/Pet) to the component name
     */
    protected function schemaRefToName(string $ref): string
    {
        $parts = explode('/', $ref);
        return $parts[count($parts) - 1] ?? $ref;
    }

    /**
     * Get raw component schema for this model (or a specific component name)
     */
    protected function getRawModelComponentSchema(?string $name = null): ?array
    {
        $doc = $this->openApiSchema;
        $name = $name ?: $this->getRawModelName();
        return $doc['components']['schemas'][$name] ?? null;
    }

    /**
     * Determine the model name to use when working with a raw OpenAPI document
     */
    protected function getRawModelName(): ?string
    {
        $doc = $this->openApiSchema;
        $components = $doc['components']['schemas'] ?? [];
        if (empty($components)) {
            return null;
        }

        $preferred = $this->getOpenApiModelName();
        if (isset($components[$preferred])) {
            return $preferred;
        }

        // Try to find a schema referenced by any path
        $paths = $doc['paths'] ?? [];
        foreach (array_keys($components) as $name) {
            foreach ($paths as $path => $ops) {
                foreach ($ops as $op) {
                    $reqRef = $op['requestBody']['content']['application/json']['schema']['$ref'] ?? null;
                    $resSchema = $op['responses']['200']['content']['application/json']['schema'] ?? null;
                    $resRef = isset($resSchema['$ref']) ? $resSchema['$ref'] : ($resSchema['items']['$ref'] ?? null);
                    if (($reqRef && $this->schemaRefToName($reqRef) === $name) ||
                        ($resRef && $this->schemaRefToName($resRef) === $name)) {
                        return $name;
                    }
                }
            }
        }

        // Fallback to the first declared schema
        return array_keys($components)[0];
    }

    /**
     * Build Laravel validation rules from a model component schema
     */
    protected function buildValidationRulesFromModelSchema(?array $modelSchema): array
    {
        if (!$modelSchema) {
            return [];
        }

        $rules = [];
        $properties = $modelSchema['properties'] ?? [];

        foreach ($properties as $name => $schema) {
            if (isset($schema['$ref'])) {
                // Nested object; no direct top-level rule
                continue;
            }

            if (($schema['type'] ?? null) === 'array' && isset($schema['items'])) {
                $rules[$name] = 'array';
                $item = $schema['items'];
                if (isset($item['$ref'])) {
                    $nestedSchema = $this->getRawModelComponentSchema($this->schemaRefToName($item['$ref']));
                    foreach (($nestedSchema['properties'] ?? []) as $child => $childSchema) {
                        $rules["{$name}.*.{$child}"] = implode('|', $this->convertOpenApiSchemaToRules($childSchema));
                    }
                } else {
                    $rules["{$name}.*"] = implode('|', $this->convertOpenApiSchemaToRules($item));
                }
                continue;
            }

            $rules[$name] = implode('|', $this->convertOpenApiSchemaToRules($schema));
        }

        return $rules;
    }

    /**
     * Convert a simple OpenAPI property schema to Laravel validation rules
     */
    protected function convertOpenApiSchemaToRules(array $schema): array
    {
        $rules = [];
        $type = $schema['type'] ?? 'string';
        $format = $schema['format'] ?? null;

        switch ($type) {
            case 'integer':
                $rules[] = 'integer';
                break;
            case 'number':
                $rules[] = 'numeric';
                break;
            case 'boolean':
                $rules[] = 'boolean';
                break;
            case 'array':
                $rules[] = 'array';
                break;
            default:
                $rules[] = 'string';
        }

        if (in_array($format, ['date', 'date-time'])) {
            $rules[] = 'date';
        }

        if (isset($schema['maxLength'])) {
            $rules[] = 'max:' . $schema['maxLength'];
        }
        if (isset($schema['minLength'])) {
            $rules[] = 'min:' . $schema['minLength'];
        }
        if (isset($schema['maximum'])) {
            $rules[] = 'max:' . $schema['maximum'];
        }
        if (isset($schema['minimum'])) {
            $rules[] = 'min:' . $schema['minimum'];
        }
        if (isset($schema['enum'])) {
            $rules[] = 'in:' . implode(',', $schema['enum']);
        }

        return $rules;
    }
}
