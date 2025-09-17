<?php

namespace MTechStack\LaravelApiModelClient\OpenApi\Traits;

use Illuminate\Support\Str;

/**
 * Trait for generating model mappings from OpenAPI endpoints and schemas
 */
trait GeneratesModelMappings
{
    /**
     * Generate model mappings from endpoints and schemas
     */
    protected function generateModelMappings(): void
    {
        $this->modelMappings = [];

        foreach ($this->endpoints as $operationId => $endpoint) {
            $modelName = $this->generateModelName($endpoint);
            $endpoint_path = $endpoint['path'];
            
            if (!isset($this->modelMappings[$modelName])) {
                $this->modelMappings[$modelName] = [
                    'model_name' => $modelName,
                    'base_endpoint' => $this->extractBaseEndpoint($endpoint_path),
                    'operations' => [],
                    'schema_refs' => [],
                    'attributes' => [],
                    'relationships' => [],
                ];
            }

            $this->modelMappings[$modelName]['operations'][] = [
                'operation_id' => $operationId,
                'method' => $endpoint['method'],
                'path' => $endpoint_path,
                'type' => $this->determineOperationType($endpoint),
                'summary' => $endpoint['summary'],
                'parameters' => $endpoint['parameters'],
            ];

            // Extract schema references
            $schemaRefs = $this->extractSchemaReferences($endpoint);
            $this->modelMappings[$modelName]['schema_refs'] = array_unique(
                array_merge($this->modelMappings[$modelName]['schema_refs'], $schemaRefs)
            );

            // Extract attributes from schemas
            $this->extractModelAttributes($modelName, $schemaRefs);
        }

        // Generate relationships between models
        $this->generateModelRelationships();

        $this->logInfo("Generated model mappings", ['count' => count($this->modelMappings)]);
    }

    /**
     * Generate model name from endpoint information
     */
    protected function generateModelName(array $endpoint): string
    {
        $tags = $endpoint['tags'];
        if (!empty($tags)) {
            return Str::studly(Str::singular($tags[0]));
        }

        $pathSegments = explode('/', trim($endpoint['path'], '/'));
        $resourceSegment = '';
        
        foreach ($pathSegments as $segment) {
            if (!str_contains($segment, '{')) {
                $resourceSegment = $segment;
                break;
            }
        }

        return Str::studly(Str::singular($resourceSegment ?: 'Resource'));
    }

    /**
     * Extract base endpoint from path
     */
    protected function extractBaseEndpoint(string $path): string
    {
        $segments = explode('/', trim($path, '/'));
        $baseSegments = [];
        
        foreach ($segments as $segment) {
            if (str_contains($segment, '{')) {
                break;
            }
            $baseSegments[] = $segment;
        }

        return '/' . implode('/', $baseSegments);
    }

    /**
     * Determine operation type from endpoint
     */
    protected function determineOperationType(array $endpoint): string
    {
        $method = strtolower($endpoint['method']);
        $path = $endpoint['path'];

        if (str_contains($path, '{')) {
            switch ($method) {
                case 'get':
                    return 'show';
                case 'put':
                case 'patch':
                    return 'update';
                case 'delete':
                    return 'destroy';
                default:
                    return $method;
            }
        } else {
            switch ($method) {
                case 'get':
                    return 'index';
                case 'post':
                    return 'store';
                default:
                    return $method;
            }
        }
    }

    /**
     * Extract model attributes from schema references
     */
    protected function extractModelAttributes(string $modelName, array $schemaRefs): void
    {
        foreach ($schemaRefs as $schemaRef) {
            if (!isset($this->schemas[$schemaRef])) {
                continue;
            }

            $schema = $this->schemas[$schemaRef];
            $attributes = $this->extractAttributesFromSchema($schema);
            
            $this->modelMappings[$modelName]['attributes'] = array_merge(
                $this->modelMappings[$modelName]['attributes'],
                $attributes
            );
        }

        // Remove duplicates
        $this->modelMappings[$modelName]['attributes'] = array_unique(
            $this->modelMappings[$modelName]['attributes'],
            SORT_REGULAR
        );
    }

    /**
     * Extract attributes from schema definition
     */
    protected function extractAttributesFromSchema(?array $schema): array
    {
        if (!$schema || !isset($schema['properties'])) {
            return [];
        }

        $attributes = [];

        foreach ($schema['properties'] as $propName => $propSchema) {
            $attribute = [
                'name' => $propName,
                'type' => $propSchema['type'] ?? 'string',
                'format' => $propSchema['format'] ?? null,
                'description' => $propSchema['description'] ?? '',
                'nullable' => $propSchema['nullable'] ?? false,
                'required' => in_array($propName, $schema['required'] ?? []),
                'example' => $propSchema['example'] ?? null,
                'default' => $propSchema['default'] ?? null,
            ];

            // Add type-specific information
            switch ($attribute['type']) {
                case 'string':
                    $attribute['min_length'] = $propSchema['minLength'] ?? null;
                    $attribute['max_length'] = $propSchema['maxLength'] ?? null;
                    $attribute['pattern'] = $propSchema['pattern'] ?? null;
                    $attribute['enum'] = $propSchema['enum'] ?? null;
                    break;

                case 'integer':
                case 'number':
                    $attribute['minimum'] = $propSchema['minimum'] ?? null;
                    $attribute['maximum'] = $propSchema['maximum'] ?? null;
                    $attribute['multiple_of'] = $propSchema['multipleOf'] ?? null;
                    break;

                case 'array':
                    $attribute['items'] = $propSchema['items'] ?? null;
                    $attribute['min_items'] = $propSchema['minItems'] ?? null;
                    $attribute['max_items'] = $propSchema['maxItems'] ?? null;
                    break;

                case 'object':
                    $attribute['properties'] = $propSchema['properties'] ?? null;
                    $attribute['additional_properties'] = $propSchema['additionalProperties'] ?? null;
                    break;
            }

            $attributes[] = $attribute;
        }

        return $attributes;
    }

    /**
     * Generate relationships between models based on schema references
     */
    protected function generateModelRelationships(): void
    {
        foreach ($this->modelMappings as $modelName => &$mapping) {
            $relationships = [];

            foreach ($mapping['attributes'] as $attribute) {
                $relationship = $this->detectRelationship($attribute, $modelName);
                if ($relationship) {
                    $relationships[] = $relationship;
                }
            }

            $mapping['relationships'] = $relationships;
        }
    }

    /**
     * Detect relationship from attribute definition
     */
    protected function detectRelationship(array $attribute, string $currentModel): ?array
    {
        $attributeName = $attribute['name'];
        $attributeType = $attribute['type'];

        // Detect foreign key relationships
        if (str_ends_with($attributeName, '_id') && $attributeType === 'integer') {
            $relatedModel = Str::studly(str_replace('_id', '', $attributeName));
            
            return [
                'type' => 'belongsTo',
                'name' => Str::camel(str_replace('_id', '', $attributeName)),
                'related_model' => $relatedModel,
                'foreign_key' => $attributeName,
                'local_key' => 'id',
            ];
        }

        // Detect array relationships
        if ($attributeType === 'array' && isset($attribute['items'])) {
            $items = $attribute['items'];
            
            // Check if items reference another schema
            if (isset($items['type']) && $items['type'] === 'reference') {
                $relatedModel = basename($items['ref']);
                
                return [
                    'type' => 'hasMany',
                    'name' => Str::camel(Str::plural($attributeName)),
                    'related_model' => $relatedModel,
                    'foreign_key' => Str::snake($currentModel) . '_id',
                    'local_key' => 'id',
                ];
            }
        }

        // Detect nested object relationships
        if ($attributeType === 'object' && isset($attribute['properties'])) {
            // This could be an embedded relationship
            return [
                'type' => 'embedded',
                'name' => Str::camel($attributeName),
                'properties' => $attribute['properties'],
            ];
        }

        return null;
    }

    /**
     * Get model mapping by name
     */
    public function getModelMapping(string $modelName): ?array
    {
        return $this->modelMappings[$modelName] ?? null;
    }

    /**
     * Get all model names
     */
    public function getModelNames(): array
    {
        return array_keys($this->modelMappings);
    }

    /**
     * Get operations for a specific model
     */
    public function getModelOperations(string $modelName): array
    {
        $mapping = $this->getModelMapping($modelName);
        return $mapping['operations'] ?? [];
    }

    /**
     * Get attributes for a specific model
     */
    public function getModelAttributes(string $modelName): array
    {
        $mapping = $this->getModelMapping($modelName);
        return $mapping['attributes'] ?? [];
    }

    /**
     * Get relationships for a specific model
     */
    public function getModelRelationships(string $modelName): array
    {
        $mapping = $this->getModelMapping($modelName);
        return $mapping['relationships'] ?? [];
    }

    /**
     * Generate Laravel model class code
     */
    public function generateModelClass(string $modelName): string
    {
        $mapping = $this->getModelMapping($modelName);
        if (!$mapping) {
            throw new \InvalidArgumentException("Model mapping not found for: {$modelName}");
        }

        $className = $modelName;
        $baseEndpoint = $mapping['base_endpoint'];
        $attributes = $mapping['attributes'];
        $relationships = $mapping['relationships'];

        $code = "<?php\n\n";
        $code .= "namespace App\\Models;\n\n";
        $code .= "use MTechStack\\LaravelApiModelClient\\Models\\ApiModel;\n\n";
        $code .= "/**\n";
        $code .= " * {$className} API Model\n";
        $code .= " * \n";
        $code .= " * Auto-generated from OpenAPI specification\n";
        $code .= " * Base endpoint: {$baseEndpoint}\n";
        $code .= " */\n";
        $code .= "class {$className} extends ApiModel\n";
        $code .= "{\n";
        $code .= "    protected \$baseEndpoint = '{$baseEndpoint}';\n\n";

        // Add fillable attributes
        $fillableAttributes = array_filter($attributes, fn($attr) => !$attr['required'] || $attr['name'] !== 'id');
        $fillableNames = array_map(fn($attr) => "'{$attr['name']}'", $fillableAttributes);
        $code .= "    protected \$fillable = [\n";
        $code .= "        " . implode(",\n        ", $fillableNames) . "\n";
        $code .= "    ];\n\n";

        // Add casts
        $casts = [];
        foreach ($attributes as $attribute) {
            switch ($attribute['type']) {
                case 'integer':
                    $casts[] = "'{$attribute['name']}' => 'integer'";
                    break;
                case 'boolean':
                    $casts[] = "'{$attribute['name']}' => 'boolean'";
                    break;
                case 'array':
                    $casts[] = "'{$attribute['name']}' => 'array'";
                    break;
                case 'object':
                    $casts[] = "'{$attribute['name']}' => 'object'";
                    break;
            }
        }

        if (!empty($casts)) {
            $code .= "    protected \$casts = [\n";
            $code .= "        " . implode(",\n        ", $casts) . "\n";
            $code .= "    ];\n\n";
        }

        // Add relationship methods
        foreach ($relationships as $relationship) {
            $code .= $this->generateRelationshipMethod($relationship);
        }

        $code .= "}\n";

        return $code;
    }

    /**
     * Generate relationship method code
     */
    protected function generateRelationshipMethod(array $relationship): string
    {
        $type = $relationship['type'];
        $name = $relationship['name'];
        $relatedModel = $relationship['related_model'] ?? '';

        $code = "    /**\n";
        $code .= "     * {$name} relationship\n";
        $code .= "     */\n";
        $code .= "    public function {$name}()\n";
        $code .= "    {\n";

        switch ($type) {
            case 'belongsTo':
                $foreignKey = $relationship['foreign_key'];
                $localKey = $relationship['local_key'];
                $code .= "        return \$this->belongsTo({$relatedModel}::class, '{$foreignKey}', '{$localKey}');\n";
                break;

            case 'hasMany':
                $foreignKey = $relationship['foreign_key'];
                $localKey = $relationship['local_key'];
                $code .= "        return \$this->hasMany({$relatedModel}::class, '{$foreignKey}', '{$localKey}');\n";
                break;

            case 'embedded':
                $code .= "        // Embedded relationship - implement custom logic\n";
                $code .= "        return \$this->getAttribute('{$name}');\n";
                break;
        }

        $code .= "    }\n\n";

        return $code;
    }
}
