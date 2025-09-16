<?php

namespace MTechStack\LaravelApiModelClient\Helpers;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use MTechStack\LaravelApiModelClient\OpenApi\OpenApiSchemaParser;

/**
 * Helper class for OpenAPI integration utilities
 */
class OpenApiHelper
{
    /**
     * Get the OpenAPI schema source for a given model class
     */
    public static function getSchemaSourceForModel(string $modelClass): ?string
    {
        // Check model-specific mappings first
        $modelSchemas = Config::get('openapi.model_schemas', []);
        
        if (isset($modelSchemas[$modelClass])) {
            return $modelSchemas[$modelClass];
        }
        
        // Fall back to default schema
        return Config::get('openapi.default_schema');
    }

    /**
     * Generate a model class name from an OpenAPI schema name
     */
    public static function generateModelClassName(string $schemaName): string
    {
        // Convert schema name to StudlyCase
        $className = Str::studly($schemaName);
        
        // Remove common suffixes that might be redundant
        $className = preg_replace('/(?:Model|Schema|Entity)$/', '', $className);
        
        return $className;
    }

    /**
     * Generate a table name from an OpenAPI schema name
     */
    public static function generateTableName(string $schemaName): string
    {
        // Convert to snake_case and pluralize
        $tableName = Str::snake($schemaName);
        return Str::plural($tableName);
    }

    /**
     * Convert OpenAPI type to Laravel cast type
     */
    public static function convertOpenApiTypeToLaravelCast(array $property): string
    {
        $type = $property['type'] ?? 'string';
        $format = $property['format'] ?? null;

        // Handle array types
        if ($type === 'array') {
            return 'array';
        }

        // Handle object types
        if ($type === 'object') {
            return 'json';
        }

        // Handle specific formats
        if ($format) {
            switch ($format) {
                case 'date':
                    return 'date';
                case 'date-time':
                    return 'datetime';
                case 'email':
                    return 'string';
                case 'uuid':
                    return 'string';
                case 'binary':
                    return 'string';
                case 'int32':
                case 'int64':
                    return 'integer';
                case 'float':
                case 'double':
                    return 'float';
                default:
                    break;
            }
        }

        // Handle basic types
        switch ($type) {
            case 'integer':
                return 'integer';
            case 'number':
                return 'float';
            case 'boolean':
                return 'boolean';
            case 'string':
            default:
                return 'string';
        }
    }

    /**
     * Generate Laravel validation rules from OpenAPI property
     */
    public static function generateValidationRulesFromProperty(array $property, bool $required = false): array
    {
        $rules = [];
        $type = $property['type'] ?? 'string';
        $format = $property['format'] ?? null;

        // Add required rule
        if ($required) {
            $rules[] = 'required';
        } else {
            $rules[] = 'nullable';
        }

        // Add type validation
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
            case 'object':
                $rules[] = 'array'; // JSON objects are validated as arrays in Laravel
                break;
            case 'string':
                $rules[] = 'string';
                break;
        }

        // Add format validation
        if ($format) {
            switch ($format) {
                case 'email':
                    $rules[] = 'email';
                    break;
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
            }
        }

        // Add constraint validations
        if (isset($property['minimum'])) {
            $rules[] = 'min:' . $property['minimum'];
        }
        if (isset($property['maximum'])) {
            $rules[] = 'max:' . $property['maximum'];
        }
        if (isset($property['minLength'])) {
            $rules[] = 'min:' . $property['minLength'];
        }
        if (isset($property['maxLength'])) {
            $rules[] = 'max:' . $property['maxLength'];
        }
        if (isset($property['enum'])) {
            $rules[] = 'in:' . implode(',', $property['enum']);
        }
        if (isset($property['pattern'])) {
            $rules[] = 'regex:/' . str_replace('/', '\/', $property['pattern']) . '/';
        }

        // Handle array item validation
        if ($type === 'array' && isset($property['items'])) {
            $itemRules = self::generateValidationRulesFromProperty($property['items']);
            if (!empty($itemRules)) {
                $rules[] = 'array';
                // Add array item validation (Laravel 8+ syntax)
                foreach ($itemRules as $itemRule) {
                    if ($itemRule !== 'nullable' && $itemRule !== 'required') {
                        $rules[] = 'array:' . $itemRule;
                    }
                }
            }
        }

        return $rules;
    }

    /**
     * Determine relationship type from OpenAPI property
     */
    public static function determineRelationshipType(array $property, string $propertyName): ?array
    {
        $type = $property['type'] ?? null;
        
        // Check for reference to another schema
        if (isset($property['$ref'])) {
            $refSchema = self::extractSchemaNameFromRef($property['$ref']);
            return [
                'type' => 'belongsTo',
                'related_model' => $refSchema,
                'foreign_key' => $propertyName . '_id',
                'local_key' => 'id'
            ];
        }

        // Check for array of references (hasMany relationship)
        if ($type === 'array' && isset($property['items']['$ref'])) {
            $refSchema = self::extractSchemaNameFromRef($property['items']['$ref']);
            return [
                'type' => 'hasMany',
                'related_model' => $refSchema,
                'foreign_key' => Str::snake(class_basename(static::class)) . '_id',
                'local_key' => 'id'
            ];
        }

        // Check for embedded objects
        if ($type === 'object' && isset($property['properties'])) {
            return [
                'type' => 'embedded',
                'properties' => $property['properties']
            ];
        }

        return null;
    }

    /**
     * Extract schema name from OpenAPI reference
     */
    public static function extractSchemaNameFromRef(string $ref): string
    {
        // Extract schema name from #/components/schemas/SchemaName
        $parts = explode('/', $ref);
        return end($parts);
    }

    /**
     * Generate endpoint path from OpenAPI operation
     */
    public static function generateEndpointPath(string $path, string $method, array $operation): string
    {
        // Remove path parameters for base endpoint
        $basePath = preg_replace('/\{[^}]+\}/', '', $path);
        $basePath = rtrim($basePath, '/');
        
        return $basePath ?: '/';
    }

    /**
     * Map HTTP method and path to CRUD operation type
     */
    public static function mapToCrudOperation(string $method, string $path): string
    {
        $method = strtolower($method);
        $hasIdParameter = str_contains($path, '{id}') || str_contains($path, '{');

        switch ($method) {
            case 'get':
                return $hasIdParameter ? 'show' : 'index';
            case 'post':
                return 'create';
            case 'put':
            case 'patch':
                return 'update';
            case 'delete':
                return 'delete';
            default:
                return 'custom';
        }
    }

    /**
     * Generate fillable attributes from OpenAPI schema
     */
    public static function generateFillableFromSchema(array $schema): array
    {
        $fillable = [];
        $properties = $schema['properties'] ?? [];
        
        foreach ($properties as $propertyName => $property) {
            // Skip auto-generated fields
            if (in_array($propertyName, ['id', 'created_at', 'updated_at'])) {
                continue;
            }
            
            // Skip read-only properties
            if (isset($property['readOnly']) && $property['readOnly']) {
                continue;
            }
            
            $fillable[] = $propertyName;
        }
        
        return $fillable;
    }

    /**
     * Generate casts array from OpenAPI schema
     */
    public static function generateCastsFromSchema(array $schema): array
    {
        $casts = [];
        $properties = $schema['properties'] ?? [];
        
        foreach ($properties as $propertyName => $property) {
            $castType = self::convertOpenApiTypeToLaravelCast($property);
            
            // Only add non-string casts (string is default)
            if ($castType !== 'string') {
                $casts[$propertyName] = $castType;
            }
        }
        
        return $casts;
    }

    /**
     * Check if OpenAPI schema is valid
     */
    public static function isValidOpenApiSchema(array $schema): bool
    {
        // Check for required OpenAPI fields
        if (!isset($schema['openapi']) && !isset($schema['swagger'])) {
            return false;
        }
        
        if (!isset($schema['info'])) {
            return false;
        }
        
        if (!isset($schema['paths']) && !isset($schema['components']['schemas'])) {
            return false;
        }
        
        return true;
    }

    /**
     * Get OpenAPI version from schema
     */
    public static function getOpenApiVersion(array $schema): ?string
    {
        return $schema['openapi'] ?? $schema['swagger'] ?? null;
    }

    /**
     * Check if OpenAPI version is supported
     */
    public static function isSupportedVersion(string $version): bool
    {
        $supportedVersions = Config::get('openapi.supported_versions', ['3.0.0', '3.0.1', '3.0.2', '3.0.3', '3.1.0']);
        
        // Check exact match first
        if (in_array($version, $supportedVersions)) {
            return true;
        }
        
        // Check major.minor match
        $versionParts = explode('.', $version);
        $majorMinor = $versionParts[0] . '.' . ($versionParts[1] ?? '0');
        
        foreach ($supportedVersions as $supportedVersion) {
            $supportedParts = explode('.', $supportedVersion);
            $supportedMajorMinor = $supportedParts[0] . '.' . ($supportedParts[1] ?? '0');
            
            if ($majorMinor === $supportedMajorMinor) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Sanitize property name for PHP class usage
     */
    public static function sanitizePropertyName(string $name): string
    {
        // Convert to snake_case
        $name = Str::snake($name);
        
        // Remove invalid characters
        $name = preg_replace('/[^a-zA-Z0-9_]/', '_', $name);
        
        // Ensure it doesn't start with a number
        if (preg_match('/^[0-9]/', $name)) {
            $name = '_' . $name;
        }
        
        return $name;
    }

    /**
     * Generate method name for relationship
     */
    public static function generateRelationshipMethodName(string $propertyName, string $relationType): string
    {
        $methodName = Str::camel($propertyName);
        
        // For hasMany relationships, ensure the method name is singular
        if ($relationType === 'hasMany') {
            $methodName = Str::singular($methodName);
        }
        
        return $methodName;
    }

    /**
     * Parse OpenAPI parameter location
     */
    public static function parseParameterLocation(array $parameter): array
    {
        $location = $parameter['in'] ?? 'query';
        $name = $parameter['name'] ?? '';
        $required = $parameter['required'] ?? false;
        $schema = $parameter['schema'] ?? [];
        
        return [
            'location' => $location,
            'name' => $name,
            'required' => $required,
            'schema' => $schema,
            'description' => $parameter['description'] ?? null
        ];
    }

    /**
     * Generate API endpoint URL with parameters
     */
    public static function buildEndpointUrl(string $basePath, array $pathParameters = []): string
    {
        $url = $basePath;
        
        foreach ($pathParameters as $name => $value) {
            $url = str_replace('{' . $name . '}', $value, $url);
        }
        
        return $url;
    }

    /**
     * Extract required fields from OpenAPI schema
     */
    public static function extractRequiredFields(array $schema): array
    {
        return $schema['required'] ?? [];
    }

    /**
     * Check if property is required in schema
     */
    public static function isPropertyRequired(string $propertyName, array $schema): bool
    {
        $required = self::extractRequiredFields($schema);
        return in_array($propertyName, $required);
    }
}
