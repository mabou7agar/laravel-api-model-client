<?php

namespace MTechStack\LaravelApiModelClient\OpenApi\Traits;


/**
 * Trait for generating Laravel validation rules from OpenAPI schemas
 */
trait GeneratesValidationRules
{
    /**
     * Generate validation rules for all schemas and parameters
     */
    protected function generateValidationRules(): void
    {
        $this->validationRules = [];

        // Generate rules for component schemas
        foreach ($this->schemas as $schemaName => $schema) {
            $this->validationRules['schemas'][$schemaName] = $this->generateSchemaValidationRules($schema);
        }

        // Generate rules for endpoint parameters
        foreach ($this->endpoints as $operationId => $endpoint) {
            $this->validationRules['endpoints'][$operationId] = [
                'parameters' => $this->generateParameterValidationRules($endpoint['parameters']),
                'request_body' => $this->generateRequestBodyValidationRules($endpoint['request_body']),
            ];
        }

        $this->logInfo("Generated validation rules", [
            'schema_rules' => count($this->validationRules['schemas'] ?? []),
            'endpoint_rules' => count($this->validationRules['endpoints'] ?? [])
        ]);
    }

    /**
     * Generate Laravel validation rules from schema
     */
    protected function generateSchemaValidationRules(?array $schema): array
    {
        if (!$schema) {
            return [];
        }

        $rules = [];
        $type = $schema['type'] ?? 'string';

        // Handle references
        if ($type === 'reference') {
            return $this->generateSchemaValidationRules($schema['resolved'] ?? null);
        }

        switch ($type) {
            case 'object':
                if (isset($schema['properties'])) {
                    foreach ($schema['properties'] as $propName => $propSchema) {
                        $propRules = $this->generateSchemaValidationRules($propSchema);
                        if (!empty($propRules)) {
                            // Check if property is required
                            $isRequired = in_array($propName, $schema['required'] ?? []);
                            if ($isRequired && !in_array('nullable', $propRules)) {
                                array_unshift($propRules, 'required');
                            }
                            $rules[$propName] = $propRules;
                        }
                    }
                }
                break;

            case 'array':
                $rules[] = 'array';
                if (isset($schema['minItems'])) {
                    $rules[] = 'min:' . $schema['minItems'];
                }
                if (isset($schema['maxItems'])) {
                    $rules[] = 'max:' . $schema['maxItems'];
                }
                if (isset($schema['items'])) {
                    $itemRules = $this->generateSchemaValidationRules($schema['items']);
                    if (!empty($itemRules)) {
                        $rules['*'] = $itemRules;
                    }
                }
                break;

            case 'string':
                $rules[] = 'string';
                if (isset($schema['minLength'])) {
                    $rules[] = 'min:' . $schema['minLength'];
                }
                if (isset($schema['maxLength'])) {
                    $rules[] = 'max:' . $schema['maxLength'];
                }
                if (isset($schema['pattern'])) {
                    $rules[] = 'regex:/' . str_replace('/', '\/', $schema['pattern']) . '/';
                }
                if (isset($schema['enum'])) {
                    $rules[] = 'in:' . implode(',', $schema['enum']);
                }
                if (isset($schema['format'])) {
                    switch ($schema['format']) {
                        case 'email':
                            $rules[] = 'email';
                            break;
                        case 'uri':
                        case 'url':
                            $rules[] = 'url';
                            break;
                        case 'date':
                            $rules[] = 'date';
                            break;
                        case 'date-time':
                            $rules[] = 'date';
                            break;
                        case 'uuid':
                            $rules[] = 'uuid';
                            break;
                        case 'ipv4':
                            $rules[] = 'ip';
                            break;
                        case 'ipv6':
                            $rules[] = 'ipv6';
                            break;
                    }
                }
                break;

            case 'integer':
                $rules[] = 'integer';
                if (isset($schema['minimum'])) {
                    $rules[] = 'min:' . $schema['minimum'];
                }
                if (isset($schema['maximum'])) {
                    $rules[] = 'max:' . $schema['maximum'];
                }
                if (isset($schema['multipleOf'])) {
                    // Laravel doesn't have a direct multiple validation, but we can use a custom rule
                    $rules[] = 'numeric';
                }
                break;

            case 'number':
                $rules[] = 'numeric';
                if (isset($schema['minimum'])) {
                    $rules[] = 'min:' . $schema['minimum'];
                }
                if (isset($schema['maximum'])) {
                    $rules[] = 'max:' . $schema['maximum'];
                }
                break;

            case 'boolean':
                $rules[] = 'boolean';
                break;
        }

        // Handle nullable
        if ($schema['nullable'] ?? false) {
            $rules[] = 'nullable';
        }

        // Handle composition keywords
        if (isset($schema['allOf'])) {
            // For allOf, combine all rules
            foreach ($schema['allOf'] as $subSchema) {
                $subRules = $this->generateSchemaValidationRules($subSchema);
                $rules = array_merge($rules, $subRules);
            }
        }

        if (isset($schema['anyOf']) || isset($schema['oneOf'])) {
            // For anyOf/oneOf, this is more complex - we might need custom validation
            // For now, we'll just take the first schema as a basic implementation
            $schemas = $schema['anyOf'] ?? $schema['oneOf'];
            if (!empty($schemas)) {
                $firstSchemaRules = $this->generateSchemaValidationRules($schemas[0]);
                $rules = array_merge($rules, $firstSchemaRules);
            }
        }

        // De-duplicate scalar rules while preserving associative entries like '*' for array items
        $assoc = [];
        $scalars = [];
        foreach ($rules as $key => $value) {
            if (is_string($key)) {
                $assoc[$key] = $value;
            } elseif (is_string($value)) {
                $scalars[] = $value;
            }
        }
        $scalars = array_values(array_unique($scalars));
        return array_merge($scalars, $assoc);
    }

    /**
     * Generate validation rules for parameters
     */
    protected function generateParameterValidationRules(array $parameters): array
    {
        $rules = [];

        foreach ($parameters as $parameter) {
            $paramRules = [];
            
            if ($parameter['required']) {
                $paramRules[] = 'required';
            }

            $schemaRules = $this->generateSchemaValidationRules($parameter['schema']);
            $paramRules = array_merge($paramRules, $schemaRules);

            if (!empty($paramRules)) {
                $rules[$parameter['name']] = array_unique($paramRules);
            }
        }

        return $rules;
    }

    /**
     * Generate validation rules for request body
     */
    protected function generateRequestBodyValidationRules(?array $requestBody): array
    {
        if (!$requestBody) {
            return [];
        }

        $rules = [];

        foreach ($requestBody['content'] as $mediaType => $content) {
            if ($content['schema']) {
                $rules[$mediaType] = $this->generateSchemaValidationRules($content['schema']);
            }
        }

        return $rules;
    }

    /**
     * Generate validation rules for a specific endpoint and media type
     */
    public function getValidationRulesForEndpoint(string $operationId, string $mediaType = 'application/json'): array
    {
        $endpointRules = $this->validationRules['endpoints'][$operationId] ?? [];
        
        $rules = [];
        
        // Add parameter rules
        if (isset($endpointRules['parameters'])) {
            $rules = array_merge($rules, $endpointRules['parameters']);
        }
        
        // Add request body rules for specific media type
        if (isset($endpointRules['request_body'][$mediaType])) {
            $bodyRules = $endpointRules['request_body'][$mediaType];
            if (is_array($bodyRules)) {
                $rules = array_merge($rules, $bodyRules);
            }
        }
        
        return $rules;
    }

    /**
     * Generate validation rules for a specific schema
     */
    public function getValidationRulesForSchema(string $schemaName): array
    {
        return $this->validationRules['schemas'][$schemaName] ?? [];
    }

    /**
     * Convert OpenAPI parameter location to Laravel validation context
     */
    protected function getParameterValidationContext(string $parameterIn): string
    {
        switch ($parameterIn) {
            case 'query':
                return 'query';
            case 'header':
                return 'header';
            case 'path':
                return 'route';
            case 'cookie':
                return 'cookie';
            default:
                return 'input';
        }
    }
}
