<?php

namespace MTechStack\LaravelApiModelClient\Console\Generators;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Carbon\Carbon;

class ModelGenerator
{
    /**
     * Generate a complete ApiModel class
     */
    public function generate(string $className, array $modelData, array $schemaData, array $config): string
    {
        $namespace = rtrim($config['namespace'], '\\');
        $properties = $modelData['properties'] ?? [];
        $description = $modelData['description'] ?? '';
        
        // Generate different parts of the model
        $classDoc = $this->generateClassDocumentation($className, $description, $properties);
        $fillable = $this->generateFillableArray($properties);
        $casts = $this->generateCastsArray($properties);
        $relationships = $this->generateRelationshipMethods($properties, $schemaData, $config);
        $validationRules = $this->generateValidationRules($properties);
        $parameterMethods = $this->generateParameterMethods($properties);
        $accessors = $this->generateAccessors($properties);
        $mutators = $this->generateMutators($properties);
        
        return $this->buildModelClass(
            $namespace,
            $className,
            $classDoc,
            $fillable,
            $casts,
            $relationships,
            $validationRules,
            $parameterMethods,
            $accessors,
            $mutators,
            $config
        );
    }

    /**
     * Update existing model file
     */
    public function updateExisting(string $filePath, string $newContent, array $config): string
    {
        $existingContent = File::get($filePath);
        
        // For now, we'll use a simple merge strategy
        // In a more sophisticated implementation, we could parse the existing file
        // and merge specific sections while preserving custom code
        
        if ($config['force']) {
            return $newContent;
        }
        
        // Simple update strategy: add a comment about the update
        $timestamp = Carbon::now()->format('Y-m-d H:i:s');
        $updateComment = "// Updated by api-client:generate-models on {$timestamp}\n";
        
        return str_replace(
            '<?php',
            "<?php\n\n{$updateComment}",
            $newContent
        );
    }

    /**
     * Generate class-level PHPDoc documentation
     */
    protected function generateClassDocumentation(string $className, string $description, array $properties): string
    {
        $doc = "/**\n";
        $doc .= " * {$className} Model\n";
        
        if ($description) {
            $doc .= " *\n";
            $doc .= " * " . $this->formatDescription($description) . "\n";
        }
        
        $doc .= " *\n";
        $doc .= " * Generated from OpenAPI schema\n";
        $doc .= " *\n";
        
        // Add @property annotations for each property
        foreach ($properties as $propertyName => $propertyData) {
            $phpType = $this->getPhpType($propertyData);
            $propertyDescription = $propertyData['description'] ?? '';
            
            $doc .= " * @property {$phpType} \${$propertyName}";
            if ($propertyDescription) {
                $doc .= " " . $this->formatDescription($propertyDescription);
            }
            $doc .= "\n";
        }
        
        $doc .= " *\n";
        $doc .= " * @package " . str_replace('\\', '\\\\', $className) . "\n";
        $doc .= " */";
        
        return $doc;
    }

    /**
     * Generate fillable array from properties
     */
    protected function generateFillableArray(array $properties): string
    {
        $fillableProperties = [];
        
        foreach ($properties as $propertyName => $propertyData) {
            // Skip read-only properties
            if (isset($propertyData['readOnly']) && $propertyData['readOnly']) {
                continue;
            }
            
            // Skip computed properties
            if ($this->isComputedProperty($propertyData)) {
                continue;
            }
            
            $fillableProperties[] = "'{$propertyName}'";
        }
        
        if (empty($fillableProperties)) {
            return "    protected \$fillable = [];";
        }
        
        $fillableString = "    protected \$fillable = [\n";
        foreach ($fillableProperties as $property) {
            $fillableString .= "        {$property},\n";
        }
        $fillableString .= "    ];";
        
        return $fillableString;
    }

    /**
     * Generate casts array from property types and formats
     */
    protected function generateCastsArray(array $properties): string
    {
        $casts = [];
        
        foreach ($properties as $propertyName => $propertyData) {
            $cast = $this->getCastType($propertyData);
            if ($cast) {
                $casts[] = "'{$propertyName}' => '{$cast}'";
            }
        }
        
        if (empty($casts)) {
            return "    protected \$casts = [];";
        }
        
        $castsString = "    protected \$casts = [\n";
        foreach ($casts as $cast) {
            $castsString .= "        {$cast},\n";
        }
        $castsString .= "    ];";
        
        return $castsString;
    }

    /**
     * Generate relationship methods
     */
    protected function generateRelationshipMethods(array $properties, array $schemaData, array $config): string
    {
        $methods = [];
        
        foreach ($properties as $propertyName => $propertyData) {
            $relationshipMethod = $this->generateRelationshipMethod($propertyName, $propertyData, $schemaData, $config);
            if ($relationshipMethod) {
                $methods[] = $relationshipMethod;
            }
        }
        
        return implode("\n\n", $methods);
    }

    /**
     * Generate a single relationship method
     */
    protected function generateRelationshipMethod(string $propertyName, array $propertyData, array $schemaData, array $config): ?string
    {
        // BelongsTo relationship (property has $ref)
        if (isset($propertyData['$ref'])) {
            $relatedModel = $this->extractModelNameFromRef($propertyData['$ref']);
            $relatedClass = $this->generateClassName($relatedModel, $config);
            
            $methodName = Str::camel($propertyName);
            $description = $propertyData['description'] ?? "Get the associated {$relatedModel}";
            
            return $this->buildRelationshipMethod(
                $methodName,
                'belongsTo',
                $relatedClass,
                $description,
                $propertyName
            );
        }
        
        // HasMany relationship (array of objects with $ref)
        if (isset($propertyData['type']) && $propertyData['type'] === 'array' && isset($propertyData['items']['$ref'])) {
            $relatedModel = $this->extractModelNameFromRef($propertyData['items']['$ref']);
            $relatedClass = $this->generateClassName($relatedModel, $config);
            
            $methodName = Str::camel($propertyName);
            $description = $propertyData['description'] ?? "Get the associated {$relatedModel} collection";
            
            return $this->buildRelationshipMethod(
                $methodName,
                'hasMany',
                $relatedClass,
                $description,
                $propertyName
            );
        }
        
        // Embedded relationship (nested object)
        if (isset($propertyData['type']) && $propertyData['type'] === 'object' && isset($propertyData['properties'])) {
            $methodName = Str::camel($propertyName);
            $description = $propertyData['description'] ?? "Get the embedded {$propertyName} object";
            
            return $this->buildEmbeddedMethod($methodName, $description, $propertyName, $propertyData);
        }
        
        return null;
    }

    /**
     * Build relationship method code
     */
    protected function buildRelationshipMethod(string $methodName, string $relationType, string $relatedClass, string $description, string $propertyName): string
    {
        $returnType = $relationType === 'hasMany' ? 'HasMany' : 'BelongsTo';
        
        $method = "    /**\n";
        $method .= "     * {$description}\n";
        $method .= "     *\n";
        $method .= "     * @return \\Illuminate\\Database\\Eloquent\\Relations\\{$returnType}\n";
        $method .= "     */\n";
        $method .= "    public function {$methodName}(): {$returnType}\n";
        $method .= "    {\n";
        
        if ($relationType === 'belongsTo') {
            $method .= "        return \$this->belongsTo({$relatedClass}::class);\n";
        } else {
            $method .= "        return \$this->hasMany({$relatedClass}::class);\n";
        }
        
        $method .= "    }";
        
        return $method;
    }

    /**
     * Build embedded object method
     */
    protected function buildEmbeddedMethod(string $methodName, string $description, string $propertyName, array $propertyData): string
    {
        $method = "    /**\n";
        $method .= "     * {$description}\n";
        $method .= "     *\n";
        $method .= "     * @return array|null\n";
        $method .= "     */\n";
        $method .= "    public function {$methodName}(): ?array\n";
        $method .= "    {\n";
        $method .= "        return \$this->getAttribute('{$propertyName}');\n";
        $method .= "    }";
        
        return $method;
    }

    /**
     * Generate validation rules method
     */
    protected function generateValidationRules(array $properties): string
    {
        $rules = [];
        
        foreach ($properties as $propertyName => $propertyData) {
            $propertyRules = $this->generatePropertyValidationRules($propertyData);
            if (!empty($propertyRules)) {
                $rules[] = "'{$propertyName}' => [" . implode(', ', array_map(fn($rule) => "'{$rule}'", $propertyRules)) . "]";
            }
        }
        
        if (empty($rules)) {
            return "";
        }
        
        $method = "    /**\n";
        $method .= "     * Get validation rules for this model\n";
        $method .= "     *\n";
        $method .= "     * @param string|null \$operation The operation type (create, update, etc.)\n";
        $method .= "     * @return array\n";
        $method .= "     */\n";
        $method .= "    public function getValidationRules(string \$operation = null): array\n";
        $method .= "    {\n";
        $method .= "        return [\n";
        
        foreach ($rules as $rule) {
            $method .= "            {$rule},\n";
        }
        
        $method .= "        ];\n";
        $method .= "    }";
        
        return $method;
    }

    /**
     * Generate parameter handling methods
     */
    protected function generateParameterMethods(array $properties): string
    {
        $methods = [];
        
        // Generate scope methods for filterable properties
        foreach ($properties as $propertyName => $propertyData) {
            if ($this->isFilterableProperty($propertyData)) {
                $methods[] = $this->generateScopeMethod($propertyName, $propertyData);
            }
        }
        
        return implode("\n\n", array_filter($methods));
    }

    /**
     * Generate scope method for a property
     */
    protected function generateScopeMethod(string $propertyName, array $propertyData): string
    {
        $methodName = 'scope' . Str::studly($propertyName);
        $description = $propertyData['description'] ?? "Filter by {$propertyName}";
        $phpType = $this->getPhpType($propertyData);
        
        $method = "    /**\n";
        $method .= "     * {$description}\n";
        $method .= "     *\n";
        $method .= "     * @param \\Illuminate\\Database\\Eloquent\\Builder \$query\n";
        $method .= "     * @param {$phpType} \$value\n";
        $method .= "     * @return \\Illuminate\\Database\\Eloquent\\Builder\n";
        $method .= "     */\n";
        $method .= "    public function {$methodName}(\$query, \$value)\n";
        $method .= "    {\n";
        $method .= "        return \$query->where('{$propertyName}', \$value);\n";
        $method .= "    }";
        
        return $method;
    }

    /**
     * Generate accessor methods
     */
    protected function generateAccessors(array $properties): string
    {
        $accessors = [];
        
        foreach ($properties as $propertyName => $propertyData) {
            $accessor = $this->generateAccessor($propertyName, $propertyData);
            if ($accessor) {
                $accessors[] = $accessor;
            }
        }
        
        return implode("\n\n", $accessors);
    }

    /**
     * Generate accessor for a property
     */
    protected function generateAccessor(string $propertyName, array $propertyData): ?string
    {
        // Generate accessor for date properties
        if ($this->isDateProperty($propertyData)) {
            $methodName = 'get' . Str::studly($propertyName) . 'Attribute';
            
            $method = "    /**\n";
            $method .= "     * Get {$propertyName} as Carbon instance\n";
            $method .= "     *\n";
            $method .= "     * @param string|null \$value\n";
            $method .= "     * @return \\Carbon\\Carbon|null\n";
            $method .= "     */\n";
            $method .= "    public function {$methodName}(\$value): ?Carbon\n";
            $method .= "    {\n";
            $method .= "        return \$value ? Carbon::parse(\$value) : null;\n";
            $method .= "    }";
            
            return $method;
        }
        
        return null;
    }

    /**
     * Generate mutator methods
     */
    protected function generateMutators(array $properties): string
    {
        $mutators = [];
        
        foreach ($properties as $propertyName => $propertyData) {
            $mutator = $this->generateMutator($propertyName, $propertyData);
            if ($mutator) {
                $mutators[] = $mutator;
            }
        }
        
        return implode("\n\n", $mutators);
    }

    /**
     * Generate mutator for a property
     */
    protected function generateMutator(string $propertyName, array $propertyData): ?string
    {
        // Generate mutator for email properties (normalize to lowercase)
        if ($this->isEmailProperty($propertyData)) {
            $methodName = 'set' . Str::studly($propertyName) . 'Attribute';
            
            $method = "    /**\n";
            $method .= "     * Set {$propertyName} attribute (normalize email)\n";
            $method .= "     *\n";
            $method .= "     * @param string|null \$value\n";
            $method .= "     * @return void\n";
            $method .= "     */\n";
            $method .= "    public function {$methodName}(\$value): void\n";
            $method .= "    {\n";
            $method .= "        \$this->attributes['{$propertyName}'] = \$value ? strtolower(trim(\$value)) : null;\n";
            $method .= "    }";
            
            return $method;
        }
        
        return null;
    }

    /**
     * Build the complete model class
     */
    protected function buildModelClass(
        string $namespace,
        string $className,
        string $classDoc,
        string $fillable,
        string $casts,
        string $relationships,
        string $validationRules,
        string $parameterMethods,
        string $accessors,
        string $mutators,
        array $config
    ): string {
        $timestamp = Carbon::now()->format('Y-m-d H:i:s');
        
        $class = "<?php\n\n";
        $class .= "namespace {$namespace};\n\n";
        $class .= "use MTechStack\\LaravelApiModelClient\\Models\\ApiModel;\n";
        $class .= "use MTechStack\\LaravelApiModelClient\\Traits\\HasOpenApiSchema;\n";
        $class .= "use Illuminate\\Database\\Eloquent\\Relations\\BelongsTo;\n";
        $class .= "use Illuminate\\Database\\Eloquent\\Relations\\HasMany;\n";
        $class .= "use Carbon\\Carbon;\n\n";
        $class .= "/**\n";
        $class .= " * Generated by api-client:generate-models on {$timestamp}\n";
        $class .= " */\n";
        $class .= "{$classDoc}\n";
        $class .= "class {$className} extends ApiModel\n";
        $class .= "{\n";
        $class .= "    use HasOpenApiSchema;\n\n";
        
        // Add properties
        $class .= "{$fillable}\n\n";
        $class .= "{$casts}\n\n";
        
        // Add OpenAPI schema configuration
        $class .= "    /**\n";
        $class .= "     * OpenAPI schema source\n";
        $class .= "     */\n";
        $class .= "    protected ?string \$openApiSchemaSource = null;\n\n";
        
        // Add validation rules method
        if ($validationRules) {
            $class .= "{$validationRules}\n\n";
        }
        
        // Add relationship methods
        if ($relationships) {
            $class .= "{$relationships}\n\n";
        }
        
        // Add parameter methods
        if ($parameterMethods) {
            $class .= "{$parameterMethods}\n\n";
        }
        
        // Add accessors
        if ($accessors) {
            $class .= "{$accessors}\n\n";
        }
        
        // Add mutators
        if ($mutators) {
            $class .= "{$mutators}\n\n";
        }
        
        $class .= "}\n";
        
        return $class;
    }

    // Helper methods
    
    protected function getPhpType(array $propertyData): string
    {
        $type = $propertyData['type'] ?? 'mixed';
        $format = $propertyData['format'] ?? null;
        $nullable = !isset($propertyData['required']) || !$propertyData['required'];
        
        $phpType = match($type) {
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
        
        return $nullable ? "{$phpType}|null" : $phpType;
    }

    protected function getCastType(array $propertyData): ?string
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

    protected function generatePropertyValidationRules(array $propertyData): array
    {
        $rules = [];
        $type = $propertyData['type'] ?? null;
        
        // Required rule
        if (isset($propertyData['required']) && $propertyData['required']) {
            $rules[] = 'required';
        } else {
            $rules[] = 'nullable';
        }
        
        // Type rules
        switch ($type) {
            case 'integer':
                $rules[] = 'integer';
                if (isset($propertyData['minimum'])) {
                    $rules[] = 'min:' . $propertyData['minimum'];
                }
                if (isset($propertyData['maximum'])) {
                    $rules[] = 'max:' . $propertyData['maximum'];
                }
                break;
                
            case 'number':
                $rules[] = 'numeric';
                if (isset($propertyData['minimum'])) {
                    $rules[] = 'min:' . $propertyData['minimum'];
                }
                if (isset($propertyData['maximum'])) {
                    $rules[] = 'max:' . $propertyData['maximum'];
                }
                break;
                
            case 'string':
                $rules[] = 'string';
                if (isset($propertyData['minLength'])) {
                    $rules[] = 'min:' . $propertyData['minLength'];
                }
                if (isset($propertyData['maxLength'])) {
                    $rules[] = 'max:' . $propertyData['maxLength'];
                }
                if (isset($propertyData['format'])) {
                    switch ($propertyData['format']) {
                        case 'email':
                            $rules[] = 'email';
                            break;
                        case 'date':
                            $rules[] = 'date';
                            break;
                        case 'date-time':
                            $rules[] = 'date';
                            break;
                    }
                }
                if (isset($propertyData['enum'])) {
                    $rules[] = 'in:' . implode(',', $propertyData['enum']);
                }
                break;
                
            case 'boolean':
                $rules[] = 'boolean';
                break;
                
            case 'array':
                $rules[] = 'array';
                break;
        }
        
        return $rules;
    }

    protected function isComputedProperty(array $propertyData): bool
    {
        return isset($propertyData['readOnly']) && $propertyData['readOnly'];
    }

    protected function isFilterableProperty(array $propertyData): bool
    {
        $type = $propertyData['type'] ?? null;
        return in_array($type, ['string', 'integer', 'number', 'boolean']);
    }

    protected function isDateProperty(array $propertyData): bool
    {
        $format = $propertyData['format'] ?? null;
        return in_array($format, ['date', 'date-time']);
    }

    protected function isEmailProperty(array $propertyData): bool
    {
        $format = $propertyData['format'] ?? null;
        return $format === 'email';
    }

    protected function extractModelNameFromRef(string $ref): string
    {
        // Extract model name from OpenAPI $ref
        // e.g., "#/components/schemas/Pet" -> "Pet"
        return basename($ref);
    }

    protected function generateClassName(string $modelName, array $config): string
    {
        $className = Str::studly($modelName);
        
        if ($config['prefix']) {
            $className = Str::studly($config['prefix']) . $className;
        }
        
        if ($config['suffix']) {
            $className = $className . Str::studly($config['suffix']);
        }
        
        return $className;
    }

    protected function formatDescription(string $description): string
    {
        // Clean up description for PHPDoc
        return trim(preg_replace('/\s+/', ' ', $description));
    }
}
