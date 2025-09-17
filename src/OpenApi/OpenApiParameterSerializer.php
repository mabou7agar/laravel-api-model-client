<?php

namespace MTechStack\LaravelApiModelClient\OpenApi;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * OpenAPI Parameter Serializer
 * 
 * Handles serialization and conversion of parameters according to OpenAPI specifications
 */
class OpenApiParameterSerializer
{
    /**
     * Serialize parameters according to OpenAPI style definitions
     */
    public static function serialize(array $parameters, array $parameterDefinitions): array
    {
        $serialized = [];
        
        foreach ($parameters as $name => $value) {
            $definition = $parameterDefinitions[$name] ?? null;
            
            if (!$definition) {
                $serialized[$name] = $value;
                continue;
            }
            
            $serialized[$name] = self::serializeParameter($value, $definition);
        }
        
        return $serialized;
    }
    
    /**
     * Serialize a single parameter based on its OpenAPI definition
     */
    public static function serializeParameter($value, array $definition)
    {
        $type = $definition['type'] ?? 'string';
        $format = $definition['format'] ?? null;
        $style = $definition['style'] ?? 'simple';
        $explode = $definition['explode'] ?? false;
        
        // Handle null values
        if ($value === null) {
            return null;
        }
        
        // Auto-detect arrays and objects if type not explicitly set
        if ($type === 'string' && is_array($value)) {
            $type = 'array';
        } elseif ($type === 'string' && is_object($value)) {
            $type = 'object';
        }
        
        // Convert type first
        $convertedValue = self::convertType($value, $type, $format);
        
        // Apply serialization style for arrays
        if ($type === 'array') {
            return self::serializeArray($convertedValue, $style, $explode);
        }
        
        // Apply serialization style for objects
        if ($type === 'object') {
            return self::serializeObject($convertedValue, $style, $explode);
        }
        
        return $convertedValue;
    }
    
    /**
     * Convert parameter value to the correct type
     */
    public static function convertType($value, string $type, ?string $format = null)
    {
        switch ($type) {
            case 'integer':
                return self::convertToInteger($value);
                
            case 'number':
                return self::convertToNumber($value);
                
            case 'boolean':
                return self::convertToBoolean($value);
                
            case 'string':
                return self::convertToString($value, $format);
                
            case 'array':
                return self::convertToArray($value);
                
            case 'object':
                return self::convertToObject($value);
                
            default:
                return $value;
        }
    }
    
    /**
     * Convert value to integer
     */
    protected static function convertToInteger($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        
        if (is_numeric($value)) {
            return (int) $value;
        }
        
        throw new \InvalidArgumentException("Cannot convert '{$value}' to integer");
    }
    
    /**
     * Convert value to number (float)
     */
    protected static function convertToNumber($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        
        if (is_numeric($value)) {
            return (float) $value;
        }
        
        throw new \InvalidArgumentException("Cannot convert '{$value}' to number");
    }
    
    /**
     * Convert value to boolean
     */
    protected static function convertToBoolean($value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }
        
        if (is_bool($value)) {
            return $value;
        }
        
        if (is_string($value)) {
            $lower = strtolower($value);
            if (in_array($lower, ['true', '1', 'yes', 'on'])) {
                return true;
            }
            if (in_array($lower, ['false', '0', 'no', 'off'])) {
                return false;
            }
        }
        
        if (is_numeric($value)) {
            return (bool) $value;
        }
        
        throw new \InvalidArgumentException("Cannot convert '{$value}' to boolean");
    }
    
    /**
     * Convert value to string with format handling
     */
    protected static function convertToString($value, ?string $format = null): ?string
    {
        if ($value === null) {
            return null;
        }
        
        if (is_string($value)) {
            return self::formatString($value, $format);
        }
        
        if ($value instanceof Carbon) {
            return self::formatDate($value, $format);
        }
        
        if ($value instanceof \DateTime) {
            return self::formatDate(Carbon::instance($value), $format);
        }
        
        // Handle arrays - should not be converted to string here, return null
        if (is_array($value)) {
            return null;
        }
        
        return (string) $value;
    }
    
    /**
     * Format string according to OpenAPI format
     */
    protected static function formatString(string $value, ?string $format = null): string
    {
        switch ($format) {
            case 'date':
                try {
                    return Carbon::parse($value)->format('Y-m-d');
                } catch (\Exception $e) {
                    return $value;
                }
                
            case 'date-time':
                try {
                    return Carbon::parse($value)->toISOString();
                } catch (\Exception $e) {
                    return $value;
                }
                
            case 'email':
                return strtolower(trim($value));
                
            case 'uri':
            case 'url':
                return trim($value);
                
            case 'uuid':
                return strtolower($value);
                
            case 'byte':
                return base64_encode($value);
                
            default:
                return $value;
        }
    }
    
    /**
     * Format date according to OpenAPI format
     */
    protected static function formatDate(Carbon $date, ?string $format = null): string
    {
        switch ($format) {
            case 'date':
                return $date->format('Y-m-d');
                
            case 'date-time':
                return $date->toISOString();
                
            default:
                return $date->toISOString();
        }
    }
    
    /**
     * Convert value to array
     */
    protected static function convertToArray($value): array
    {
        if ($value === null) {
            return [];
        }
        
        if (is_array($value)) {
            return $value;
        }
        
        if ($value instanceof Collection) {
            return $value->toArray();
        }
        
        if (is_string($value)) {
            // Try to parse as JSON first
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
            
            // Parse as comma-separated values
            return array_map('trim', explode(',', $value));
        }
        
        return [$value];
    }
    
    /**
     * Convert value to object
     */
    protected static function convertToObject($value): array
    {
        if ($value === null) {
            return [];
        }
        
        if (is_array($value)) {
            return $value;
        }
        
        if ($value instanceof Collection) {
            return $value->toArray();
        }
        
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }
        
        if (is_object($value)) {
            return json_decode(json_encode($value), true);
        }
        
        throw new \InvalidArgumentException("Cannot convert value to object");
    }
    
    /**
     * Serialize array according to OpenAPI style
     */
    protected static function serializeArray(array $value, string $style, bool $explode): string
    {
        switch ($style) {
            case 'form':
                return $explode ? 
                    implode('&', array_map(fn($v) => urlencode($v), $value)) :
                    implode(',', $value);
                    
            case 'spaceDelimited':
                return implode(' ', $value);
                
            case 'pipeDelimited':
                return implode('|', $value);
                
            case 'simple':
            default:
                return implode(',', $value);
        }
    }
    
    /**
     * Serialize object according to OpenAPI style
     */
    protected static function serializeObject(array $value, string $style, bool $explode): string
    {
        switch ($style) {
            case 'form':
                if ($explode) {
                    $parts = [];
                    foreach ($value as $key => $val) {
                        $parts[] = urlencode($key) . '=' . urlencode($val);
                    }
                    return implode('&', $parts);
                } else {
                    $parts = [];
                    foreach ($value as $key => $val) {
                        $parts[] = $key;
                        $parts[] = $val;
                    }
                    return implode(',', $parts);
                }
                
            case 'simple':
            default:
                $parts = [];
                foreach ($value as $key => $val) {
                    $parts[] = $key;
                    $parts[] = $val;
                }
                return implode(',', $parts);
        }
    }
    
    /**
     * Validate parameter value against OpenAPI constraints
     */
    public static function validateParameter($value, array $definition): array
    {
        $errors = [];
        $type = $definition['type'] ?? 'string';
        
        // Required validation
        if (($definition['required'] ?? false) && ($value === null || $value === '')) {
            $errors[] = 'Parameter is required';
            return $errors;
        }
        
        // Skip further validation if value is null and not required
        if ($value === null) {
            return $errors;
        }
        
        // Type-specific validations
        switch ($type) {
            case 'integer':
            case 'number':
                $errors = array_merge($errors, self::validateNumeric($value, $definition));
                break;
                
            case 'string':
                $errors = array_merge($errors, self::validateString($value, $definition));
                break;
                
            case 'array':
                $errors = array_merge($errors, self::validateArray($value, $definition));
                break;
                
            case 'object':
                $errors = array_merge($errors, self::validateObject($value, $definition));
                break;
        }
        
        return $errors;
    }
    
    /**
     * Validate numeric parameter
     */
    protected static function validateNumeric($value, array $definition): array
    {
        $errors = [];
        
        if (!is_numeric($value)) {
            $errors[] = 'Value must be numeric';
            return $errors;
        }
        
        $numValue = (float) $value;
        
        if (isset($definition['minimum'])) {
            $exclusive = $definition['exclusiveMinimum'] ?? false;
            if ($exclusive ? $numValue <= $definition['minimum'] : $numValue < $definition['minimum']) {
                $errors[] = "Value must be " . ($exclusive ? 'greater than' : 'greater than or equal to') . " {$definition['minimum']}";
            }
        }
        
        if (isset($definition['maximum'])) {
            $exclusive = $definition['exclusiveMaximum'] ?? false;
            if ($exclusive ? $numValue >= $definition['maximum'] : $numValue > $definition['maximum']) {
                $errors[] = "Value must be " . ($exclusive ? 'less than' : 'less than or equal to') . " {$definition['maximum']}";
            }
        }
        
        if (isset($definition['multipleOf']) && fmod($numValue, $definition['multipleOf']) !== 0.0) {
            $errors[] = "Value must be a multiple of {$definition['multipleOf']}";
        }
        
        return $errors;
    }
    
    /**
     * Validate string parameter
     */
    protected static function validateString($value, array $definition): array
    {
        $errors = [];
        $strValue = (string) $value;
        
        if (isset($definition['minLength']) && strlen($strValue) < $definition['minLength']) {
            $errors[] = "String must be at least {$definition['minLength']} characters long";
        }
        
        if (isset($definition['maxLength']) && strlen($strValue) > $definition['maxLength']) {
            $errors[] = "String must be no more than {$definition['maxLength']} characters long";
        }
        
        if (isset($definition['pattern']) && !preg_match('/' . $definition['pattern'] . '/', $strValue)) {
            $errors[] = "String does not match required pattern";
        }
        
        if (isset($definition['enum']) && !in_array($strValue, $definition['enum'])) {
            $errors[] = "Value must be one of: " . implode(', ', $definition['enum']);
        }
        
        // Format-specific validations
        $format = $definition['format'] ?? null;
        switch ($format) {
            case 'email':
                if (!filter_var($strValue, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = "Value must be a valid email address";
                }
                break;
                
            case 'uri':
            case 'url':
                if (!filter_var($strValue, FILTER_VALIDATE_URL)) {
                    $errors[] = "Value must be a valid URL";
                }
                break;
                
            case 'uuid':
                if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $strValue)) {
                    $errors[] = "Value must be a valid UUID";
                }
                break;
                
            case 'date':
                try {
                    Carbon::createFromFormat('Y-m-d', $strValue);
                } catch (\Exception $e) {
                    $errors[] = "Value must be a valid date in YYYY-MM-DD format";
                }
                break;
                
            case 'date-time':
                try {
                    Carbon::parse($strValue);
                } catch (\Exception $e) {
                    $errors[] = "Value must be a valid date-time in ISO 8601 format";
                }
                break;
        }
        
        return $errors;
    }
    
    /**
     * Validate array parameter
     */
    protected static function validateArray($value, array $definition): array
    {
        $errors = [];
        
        if (!is_array($value)) {
            $errors[] = 'Value must be an array';
            return $errors;
        }
        
        if (isset($definition['minItems']) && count($value) < $definition['minItems']) {
            $errors[] = "Array must have at least {$definition['minItems']} items";
        }
        
        if (isset($definition['maxItems']) && count($value) > $definition['maxItems']) {
            $errors[] = "Array must have no more than {$definition['maxItems']} items";
        }
        
        if (isset($definition['uniqueItems']) && $definition['uniqueItems'] && count($value) !== count(array_unique($value))) {
            $errors[] = "Array items must be unique";
        }
        
        // Validate individual items if schema is provided
        if (isset($definition['items'])) {
            foreach ($value as $index => $item) {
                $itemErrors = self::validateParameter($item, $definition['items']);
                foreach ($itemErrors as $error) {
                    $errors[] = "Item {$index}: {$error}";
                }
            }
        }
        
        return $errors;
    }
    
    /**
     * Validate object parameter
     */
    protected static function validateObject($value, array $definition): array
    {
        $errors = [];
        
        if (!is_array($value)) {
            $errors[] = 'Value must be an object';
            return $errors;
        }
        
        // Validate required properties
        $required = $definition['required'] ?? [];
        foreach ($required as $prop) {
            if (!array_key_exists($prop, $value)) {
                $errors[] = "Required property '{$prop}' is missing";
            }
        }
        
        // Validate individual properties if schema is provided
        $properties = $definition['properties'] ?? [];
        foreach ($value as $prop => $propValue) {
            if (isset($properties[$prop])) {
                $propErrors = self::validateParameter($propValue, $properties[$prop]);
                foreach ($propErrors as $error) {
                    $errors[] = "Property '{$prop}': {$error}";
                }
            }
        }
        
        return $errors;
    }
    
    /**
     * Parse query string parameters according to OpenAPI style
     */
    public static function parseQueryParameters(string $queryString, array $parameterDefinitions): array
    {
        $parsed = [];
        parse_str($queryString, $rawParams);
        
        foreach ($rawParams as $name => $value) {
            $definition = $parameterDefinitions[$name] ?? null;
            
            if (!$definition) {
                $parsed[$name] = $value;
                continue;
            }
            
            $parsed[$name] = self::parseParameter($value, $definition);
        }
        
        return $parsed;
    }
    
    /**
     * Parse a single parameter value according to its definition
     */
    protected static function parseParameter($value, array $definition)
    {
        $type = $definition['type'] ?? 'string';
        $style = $definition['style'] ?? 'simple';
        $explode = $definition['explode'] ?? false;
        
        // Parse array values
        if ($type === 'array') {
            return self::parseArrayParameter($value, $style, $explode);
        }
        
        // Parse object values
        if ($type === 'object') {
            return self::parseObjectParameter($value, $style, $explode);
        }
        
        // Convert single values
        return self::convertType($value, $type, $definition['format'] ?? null);
    }
    
    /**
     * Parse array parameter according to style
     */
    protected static function parseArrayParameter($value, string $style, bool $explode): array
    {
        if (is_array($value)) {
            return $value;
        }
        
        $stringValue = (string) $value;
        
        switch ($style) {
            case 'spaceDelimited':
                return explode(' ', $stringValue);
                
            case 'pipeDelimited':
                return explode('|', $stringValue);
                
            case 'form':
            case 'simple':
            default:
                return explode(',', $stringValue);
        }
    }
    
    /**
     * Parse object parameter according to style
     */
    protected static function parseObjectParameter($value, string $style, bool $explode): array
    {
        if (is_array($value)) {
            return $value;
        }
        
        $stringValue = (string) $value;
        
        // Try JSON decode first
        $decoded = json_decode($stringValue, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }
        
        // Parse according to style
        switch ($style) {
            case 'form':
                if ($explode) {
                    $result = [];
                    parse_str($stringValue, $result);
                    return $result;
                } else {
                    $parts = explode(',', $stringValue);
                    $result = [];
                    for ($i = 0; $i < count($parts); $i += 2) {
                        if (isset($parts[$i + 1])) {
                            $result[$parts[$i]] = $parts[$i + 1];
                        }
                    }
                    return $result;
                }
                
            case 'simple':
            default:
                $parts = explode(',', $stringValue);
                $result = [];
                for ($i = 0; $i < count($parts); $i += 2) {
                    if (isset($parts[$i + 1])) {
                        $result[$parts[$i]] = $parts[$i + 1];
                    }
                }
                return $result;
        }
    }
}
