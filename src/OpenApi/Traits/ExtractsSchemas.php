<?php

namespace MTechStack\LaravelApiModelClient\OpenApi\Traits;

use cebe\openapi\spec\Schema;
use cebe\openapi\spec\Reference;
use Illuminate\Support\Facades\Log;

/**
 * Trait for extracting schema information from OpenAPI specifications
 */
trait ExtractsSchemas
{
    /**
     * Extract schemas from components
     */
    protected function extractSchemas(): void
    {
        $this->schemas = [];

        if (!$this->openApiSpec->components || !$this->openApiSpec->components->schemas) {
            Log::info("No component schemas found in OpenAPI specification");
            return;
        }

        foreach ($this->openApiSpec->components->schemas as $schemaName => $schema) {
            $this->schemas[$schemaName] = $this->extractSchemaInfo($schema);
        }

        Log::info("Extracted schemas", ['count' => count($this->schemas)]);
    }

    /**
     * Extract schema information with support for complex types and references
     */
    protected function extractSchemaInfo($schema): ?array
    {
        if (!$schema) {
            return null;
        }

        if ($schema instanceof Reference) {
            return [
                'type' => 'reference',
                'ref' => $schema->getReference(),
                'resolved' => $this->extractSchemaInfo($schema->resolve()),
            ];
        }

        if (!$schema instanceof Schema) {
            return null;
        }

        $schemaInfo = [
            'type' => $schema->type ?? 'object',
            'format' => $schema->format ?? null,
            'description' => $schema->description ?? '',
            'nullable' => $schema->nullable ?? false,
            'deprecated' => $schema->deprecated ?? false,
        ];

        // Handle different schema types
        switch ($schema->type) {
            case 'array':
                $schemaInfo['items'] = $this->extractSchemaInfo($schema->items);
                $schemaInfo['minItems'] = $schema->minItems ?? null;
                $schemaInfo['maxItems'] = $schema->maxItems ?? null;
                $schemaInfo['uniqueItems'] = $schema->uniqueItems ?? null;
                break;

            case 'object':
                $schemaInfo['properties'] = [];
                if ($schema->properties) {
                    foreach ($schema->properties as $propName => $propSchema) {
                        $schemaInfo['properties'][$propName] = $this->extractSchemaInfo($propSchema);
                    }
                }
                $schemaInfo['required'] = $schema->required ?? [];
                $schemaInfo['additionalProperties'] = $schema->additionalProperties ?? null;
                $schemaInfo['minProperties'] = $schema->minProperties ?? null;
                $schemaInfo['maxProperties'] = $schema->maxProperties ?? null;
                break;

            case 'string':
                $schemaInfo['minLength'] = $schema->minLength ?? null;
                $schemaInfo['maxLength'] = $schema->maxLength ?? null;
                $schemaInfo['pattern'] = $schema->pattern ?? null;
                $schemaInfo['enum'] = $schema->enum ?? null;
                break;

            case 'number':
            case 'integer':
                $schemaInfo['minimum'] = $schema->minimum ?? null;
                $schemaInfo['maximum'] = $schema->maximum ?? null;
                $schemaInfo['exclusiveMinimum'] = $schema->exclusiveMinimum ?? null;
                $schemaInfo['exclusiveMaximum'] = $schema->exclusiveMaximum ?? null;
                $schemaInfo['multipleOf'] = $schema->multipleOf ?? null;
                break;
        }

        // Handle composition keywords (allOf, anyOf, oneOf)
        if ($schema->allOf) {
            $schemaInfo['allOf'] = array_map([$this, 'extractSchemaInfo'], $schema->allOf);
        }
        if ($schema->anyOf) {
            $schemaInfo['anyOf'] = array_map([$this, 'extractSchemaInfo'], $schema->anyOf);
        }
        if ($schema->oneOf) {
            $schemaInfo['oneOf'] = array_map([$this, 'extractSchemaInfo'], $schema->oneOf);
        }
        if ($schema->not) {
            $schemaInfo['not'] = $this->extractSchemaInfo($schema->not);
        }

        // Additional metadata
        $schemaInfo['example'] = $schema->example ?? null;
        $schemaInfo['default'] = $schema->default ?? null;
        $schemaInfo['title'] = $schema->title ?? null;
        $schemaInfo['readOnly'] = $schema->readOnly ?? null;
        $schemaInfo['writeOnly'] = $schema->writeOnly ?? null;

        return $schemaInfo;
    }

    /**
     * Find schema references recursively
     */
    protected function findSchemaReferences(?array $schema): array
    {
        if (!$schema) {
            return [];
        }

        $refs = [];

        if ($schema['type'] === 'reference') {
            $refName = basename($schema['ref']);
            $refs[] = $refName;
        }

        // Recursively find references in nested schemas
        if (isset($schema['properties'])) {
            foreach ($schema['properties'] as $property) {
                $refs = array_merge($refs, $this->findSchemaReferences($property));
            }
        }

        if (isset($schema['items'])) {
            $refs = array_merge($refs, $this->findSchemaReferences($schema['items']));
        }

        // Handle composition keywords
        foreach (['allOf', 'anyOf', 'oneOf'] as $combiner) {
            if (isset($schema[$combiner])) {
                foreach ($schema[$combiner] as $subSchema) {
                    $refs = array_merge($refs, $this->findSchemaReferences($subSchema));
                }
            }
        }

        if (isset($schema['not'])) {
            $refs = array_merge($refs, $this->findSchemaReferences($schema['not']));
        }

        return array_unique($refs);
    }

    /**
     * Extract schema references from endpoint
     */
    protected function extractSchemaReferences(array $endpoint): array
    {
        $refs = [];

        // Extract from request body
        if ($endpoint['request_body']) {
            foreach ($endpoint['request_body']['content'] as $content) {
                $refs = array_merge($refs, $this->findSchemaReferences($content['schema']));
            }
        }

        // Extract from responses
        foreach ($endpoint['responses'] as $response) {
            foreach ($response['content'] as $content) {
                $refs = array_merge($refs, $this->findSchemaReferences($content['schema']));
            }
        }

        // Extract from parameters
        foreach ($endpoint['parameters'] as $parameter) {
            $refs = array_merge($refs, $this->findSchemaReferences($parameter['schema']));
        }

        return array_unique($refs);
    }

    /**
     * Extract models from OpenAPI schema for code generation
     */
    public function extractModels(array $schema): array
    {
        $models = [];
        
        // Primary: Extract from parsed schema array
        if (isset($schema['schemas']) && is_array($schema['schemas'])) {
            foreach ($schema['schemas'] as $modelName => $modelSchema) {
                $schemaArray = is_array($modelSchema) ? $modelSchema : $this->convertSchemaToArray($modelSchema);
                if ($this->isModelSchema($schemaArray)) {
                    $models[$modelName] = $this->processModelSchema($schemaArray, $modelName, $schema);
                }
            }
        }
        // Fallback: Extract from components/schemas structure
        elseif (isset($schema['components']['schemas'])) {
            foreach ($schema['components']['schemas'] as $modelName => $modelSchema) {
                $schemaArray = is_array($modelSchema) ? $modelSchema : $this->convertSchemaToArray($modelSchema);
                if ($this->isModelSchema($schemaArray)) {
                    $models[$modelName] = $this->processModelSchema($schemaArray, $modelName, $schema);
                }
            }
        }
        // If we have an OpenAPI spec object, extract from it
        elseif ($this->openApiSpec && $this->openApiSpec->components && $this->openApiSpec->components->schemas) {
            foreach ($this->openApiSpec->components->schemas as $modelName => $modelSchema) {
                $schemaArray = $this->convertSchemaToArray($modelSchema);
                if ($this->isModelSchema($schemaArray)) {
                    $models[$modelName] = $this->processModelSchema($schemaArray, $modelName, $schema);
                }
            }
        }
        
        // Extract inline models from paths
        if (isset($schema['paths'])) {
            $inlineModels = $this->extractInlineModels($schema['paths']);
            $models = array_merge($models, $inlineModels);
        }
        
        return $models;
    }

    /**
     * Convert OpenAPI schema object to array
     */
    protected function convertSchemaToArray($schema): array
    {
        if (is_array($schema)) {
            return $schema;
        }
        
        // Handle cebe/php-openapi Schema objects
        if (is_object($schema)) {
            $result = [];
            
            // Basic properties
            if (isset($schema->type)) $result['type'] = $schema->type;
            if (isset($schema->format)) $result['format'] = $schema->format;
            if (isset($schema->description)) $result['description'] = $schema->description;
            if (isset($schema->example)) $result['example'] = $schema->example;
            if (isset($schema->default)) $result['default'] = $schema->default;
            if (isset($schema->nullable)) $result['nullable'] = $schema->nullable;
            if (isset($schema->readOnly)) $result['readOnly'] = $schema->readOnly;
            if (isset($schema->writeOnly)) $result['writeOnly'] = $schema->writeOnly;
            
            // String constraints
            if (isset($schema->minLength)) $result['minLength'] = $schema->minLength;
            if (isset($schema->maxLength)) $result['maxLength'] = $schema->maxLength;
            if (isset($schema->pattern)) $result['pattern'] = $schema->pattern;
            if (isset($schema->enum)) $result['enum'] = $schema->enum;
            
            // Numeric constraints
            if (isset($schema->minimum)) $result['minimum'] = $schema->minimum;
            if (isset($schema->maximum)) $result['maximum'] = $schema->maximum;
            if (isset($schema->exclusiveMinimum)) $result['exclusiveMinimum'] = $schema->exclusiveMinimum;
            if (isset($schema->exclusiveMaximum)) $result['exclusiveMaximum'] = $schema->exclusiveMaximum;
            if (isset($schema->multipleOf)) $result['multipleOf'] = $schema->multipleOf;
            
            // Array constraints
            if (isset($schema->minItems)) $result['minItems'] = $schema->minItems;
            if (isset($schema->maxItems)) $result['maxItems'] = $schema->maxItems;
            if (isset($schema->uniqueItems)) $result['uniqueItems'] = $schema->uniqueItems;
            
            // Object constraints
            if (isset($schema->minProperties)) $result['minProperties'] = $schema->minProperties;
            if (isset($schema->maxProperties)) $result['maxProperties'] = $schema->maxProperties;
            if (isset($schema->required)) $result['required'] = $schema->required;
            if (isset($schema->additionalProperties)) $result['additionalProperties'] = $schema->additionalProperties;
            
            // Handle properties
            if (isset($schema->properties)) {
                $result['properties'] = [];
                foreach ($schema->properties as $propName => $propSchema) {
                    $result['properties'][$propName] = $this->convertSchemaToArray($propSchema);
                }
            }
            
            // Handle items (for arrays)
            if (isset($schema->items)) {
                $result['items'] = $this->convertSchemaToArray($schema->items);
            }
            
            // Handle references
            if (method_exists($schema, 'getReference') && $schema->getReference()) {
                $result['$ref'] = $schema->getReference();
            }
            
            return $result;
        }
        
        return [];
    }

    /**
     * Check if schema represents a model (object with properties)
     */
    protected function isModelSchema(array $schema): bool
    {
        return isset($schema['type']) && 
               $schema['type'] === 'object' && 
               isset($schema['properties']) &&
               is_array($schema['properties']);
    }

    /**
     * Process a model schema and enrich it with metadata
     */
    protected function processModelSchema(array $modelSchema, string $modelName, array $fullSchema): array
    {
        $processed = $modelSchema;
        
        // Add model name
        $processed['modelName'] = $modelName;
        
        // Process properties
        if (isset($processed['properties'])) {
            $processed['properties'] = $this->processModelProperties(
                $processed['properties'], 
                $fullSchema
            );
        }
        
        // Extract required fields
        $processed['requiredFields'] = $processed['required'] ?? [];
        
        // Detect relationships
        $processed['relationships'] = $this->detectModelRelationships($processed, $fullSchema);
        
        // Generate API endpoints for this model
        $processed['endpoints'] = $this->findModelEndpoints($modelName, $fullSchema);
        
        // Add validation metadata
        $processed['validationRules'] = $this->generateValidationRulesForModel($processed);
        
        return $processed;
    }

    /**
     * Process model properties and resolve references
     */
    protected function processModelProperties(array $properties, array $fullSchema): array
    {
        $processed = [];
        
        foreach ($properties as $propertyName => $propertySchema) {
            $processed[$propertyName] = $this->processModelProperty(
                $propertySchema, 
                $propertyName, 
                $fullSchema
            );
        }
        
        return $processed;
    }

    /**
     * Process a single model property
     */
    protected function processModelProperty(array $propertySchema, string $propertyName, array $fullSchema): array
    {
        $processed = $propertySchema;
        
        // Resolve $ref if present
        if (isset($propertySchema['$ref'])) {
            $resolved = $this->resolveReference($propertySchema['$ref'], $fullSchema);
            if ($resolved) {
                $processed = array_merge($processed, $resolved);
                $processed['originalRef'] = $propertySchema['$ref'];
            }
        }
        
        // Process array items
        if (isset($propertySchema['type']) && $propertySchema['type'] === 'array' && isset($propertySchema['items'])) {
            $processed['items'] = $this->processModelProperty($propertySchema['items'], $propertyName . '_item', $fullSchema);
        }
        
        // Add property metadata
        $processed['propertyName'] = $propertyName;
        $processed['phpType'] = $this->determinePhpType($processed);
        $processed['laravelCast'] = $this->determineLaravelCast($processed);
        
        return $processed;
    }

    /**
     * Detect relationships in a model
     */
    protected function detectModelRelationships(array $modelSchema, array $fullSchema): array
    {
        $relationships = [];
        
        if (!isset($modelSchema['properties'])) {
            return $relationships;
        }
        
        foreach ($modelSchema['properties'] as $propertyName => $propertyData) {
            // BelongsTo relationship (property has $ref)
            if (isset($propertyData['$ref'])) {
                $relatedModel = $this->extractModelNameFromRef($propertyData['$ref']);
                $relationships[$propertyName] = [
                    'type' => 'belongsTo',
                    'relatedModel' => $relatedModel,
                    'foreignKey' => $propertyName . '_id',
                    'localKey' => 'id'
                ];
            }
            
            // HasMany relationship (array of objects with $ref)
            if (isset($propertyData['type']) && $propertyData['type'] === 'array' && isset($propertyData['items']['$ref'])) {
                $relatedModel = $this->extractModelNameFromRef($propertyData['items']['$ref']);
                $relationships[$propertyName] = [
                    'type' => 'hasMany',
                    'relatedModel' => $relatedModel,
                    'foreignKey' => strtolower($modelSchema['modelName']) . '_id',
                    'localKey' => 'id'
                ];
            }
            
            // Embedded relationship (nested object)
            if (isset($propertyData['type']) && $propertyData['type'] === 'object' && isset($propertyData['properties'])) {
                $relationships[$propertyName] = [
                    'type' => 'embedded',
                    'properties' => array_keys($propertyData['properties'])
                ];
            }
        }
        
        return $relationships;
    }

    /**
     * Find API endpoints related to a model
     */
    protected function findModelEndpoints(string $modelName, array $fullSchema): array
    {
        $endpoints = [];
        
        if (!isset($fullSchema['paths'])) {
            return $endpoints;
        }
        
        $modelNameLower = strtolower($modelName);
        $modelNamePlural = \Illuminate\Support\Str::plural($modelNameLower);
        
        foreach ($fullSchema['paths'] as $path => $pathData) {
            // Check if path is related to this model
            if (str_contains(strtolower($path), $modelNameLower) || 
                str_contains(strtolower($path), $modelNamePlural)) {
                
                foreach ($pathData as $method => $operationData) {
                    if (in_array($method, ['get', 'post', 'put', 'patch', 'delete'])) {
                        $endpoints[] = [
                            'path' => $path,
                            'method' => strtoupper($method),
                            'operationId' => $operationData['operationId'] ?? null,
                            'summary' => $operationData['summary'] ?? null,
                            'parameters' => $operationData['parameters'] ?? []
                        ];
                    }
                }
            }
        }
        
        return $endpoints;
    }

    /**
     * Extract inline models from API paths
     */
    protected function extractInlineModels(array $paths): array
    {
        $models = [];
        
        foreach ($paths as $path => $pathData) {
            foreach ($pathData as $method => $operationData) {
                if (!in_array($method, ['get', 'post', 'put', 'patch', 'delete'])) {
                    continue;
                }
                
                // Check request body for inline schemas
                if (isset($operationData['requestBody']['content'])) {
                    foreach ($operationData['requestBody']['content'] as $contentType => $contentData) {
                        if (isset($contentData['schema']) && $this->isModelSchema($contentData['schema'])) {
                            $modelName = $this->generateInlineModelName($path, $method, 'Request');
                            $models[$modelName] = $this->processModelSchema($contentData['schema'], $modelName, []);
                        }
                    }
                }
                
                // Check responses for inline schemas
                if (isset($operationData['responses'])) {
                    foreach ($operationData['responses'] as $statusCode => $responseData) {
                        if (isset($responseData['content'])) {
                            foreach ($responseData['content'] as $contentType => $contentData) {
                                if (isset($contentData['schema']) && $this->isModelSchema($contentData['schema'])) {
                                    $modelName = $this->generateInlineModelName($path, $method, 'Response' . $statusCode);
                                    $models[$modelName] = $this->processModelSchema($contentData['schema'], $modelName, []);
                                }
                            }
                        }
                    }
                }
            }
        }
        
        return $models;
    }

    /**
     * Generate name for inline model
     */
    protected function generateInlineModelName(string $path, string $method, string $suffix): string
    {
        $pathParts = array_filter(explode('/', $path));
        $pathName = implode('', array_map('ucfirst', $pathParts));
        $methodName = ucfirst(strtolower($method));
        
        return $pathName . $methodName . $suffix;
    }

    /**
     * Determine PHP type for a property
     */
    protected function determinePhpType(array $propertyData): string
    {
        $type = $propertyData['type'] ?? 'mixed';
        $format = $propertyData['format'] ?? null;
        
        return match($type) {
            'integer' => 'int',
            'number' => 'float',
            'boolean' => 'bool',
            'array' => 'array',
            'object' => 'array',
            'string' => match($format) {
                'date', 'date-time' => '\\Carbon\\Carbon',
                default => 'string'
            },
            default => 'mixed'
        };
    }

    /**
     * Determine Laravel cast type for a property
     */
    protected function determineLaravelCast(array $propertyData): ?string
    {
        $type = $propertyData['type'] ?? null;
        $format = $propertyData['format'] ?? null;
        
        return match($type) {
            'integer' => 'integer',
            'number' => 'float',
            'boolean' => 'boolean',
            'array', 'object' => 'array',
            'string' => match($format) {
                'date' => 'date',
                'date-time' => 'datetime',
                default => null
            },
            default => null
        };
    }

    /**
     * Generate validation rules for a model
     */
    protected function generateValidationRulesForModel(array $modelSchema): array
    {
        $rules = [];
        
        if (!isset($modelSchema['properties'])) {
            return $rules;
        }
        
        $requiredFields = $modelSchema['required'] ?? [];
        
        foreach ($modelSchema['properties'] as $propertyName => $propertyData) {
            $propertyRules = [];
            
            // Required rule
            if (in_array($propertyName, $requiredFields)) {
                $propertyRules[] = 'required';
            } else {
                $propertyRules[] = 'nullable';
            }
            
            // Type-specific rules
            $type = $propertyData['type'] ?? null;
            switch ($type) {
                case 'integer':
                    $propertyRules[] = 'integer';
                    if (isset($propertyData['minimum'])) {
                        $propertyRules[] = 'min:' . $propertyData['minimum'];
                    }
                    if (isset($propertyData['maximum'])) {
                        $propertyRules[] = 'max:' . $propertyData['maximum'];
                    }
                    break;
                    
                case 'number':
                    $propertyRules[] = 'numeric';
                    if (isset($propertyData['minimum'])) {
                        $propertyRules[] = 'min:' . $propertyData['minimum'];
                    }
                    if (isset($propertyData['maximum'])) {
                        $propertyRules[] = 'max:' . $propertyData['maximum'];
                    }
                    break;
                    
                case 'string':
                    $propertyRules[] = 'string';
                    if (isset($propertyData['minLength'])) {
                        $propertyRules[] = 'min:' . $propertyData['minLength'];
                    }
                    if (isset($propertyData['maxLength'])) {
                        $propertyRules[] = 'max:' . $propertyData['maxLength'];
                    }
                    if (isset($propertyData['format'])) {
                        switch ($propertyData['format']) {
                            case 'email':
                                $propertyRules[] = 'email';
                                break;
                            case 'date':
                            case 'date-time':
                                $propertyRules[] = 'date';
                                break;
                        }
                    }
                    if (isset($propertyData['enum'])) {
                        $propertyRules[] = 'in:' . implode(',', $propertyData['enum']);
                    }
                    break;
                    
                case 'boolean':
                    $propertyRules[] = 'boolean';
                    break;
                    
                case 'array':
                    $propertyRules[] = 'array';
                    break;
            }
            
            if (!empty($propertyRules)) {
                $rules[$propertyName] = $propertyRules;
            }
        }
        
        return $rules;
    }

    /**
     * Extract model name from OpenAPI $ref
     */
    protected function extractModelNameFromRef(string $ref): string
    {
        // Extract model name from OpenAPI $ref
        // e.g., "#/components/schemas/Pet" -> "Pet"
        return basename($ref);
    }

    /**
     * Resolve reference to actual schema
     */
    protected function resolveReference(string $ref, array $fullSchema): ?array
    {
        // Simple reference resolution for #/components/schemas/ModelName
        if (str_starts_with($ref, '#/components/schemas/')) {
            $modelName = basename($ref);
            return $fullSchema['components']['schemas'][$modelName] ?? null;
        }
        
        return null;
    }
}
