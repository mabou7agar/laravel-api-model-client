<?php

namespace MTechStack\LaravelApiModelClient\Helpers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Enhanced parameter substitution system for API endpoints
 * Supports both direct endpoint definitions and relationship-based approaches
 */
class EndpointParameterResolver
{
    /**
     * Resolve endpoint parameters by checking model attributes, query parameters, and relationships
     *
     * @param string $endpoint
     * @param Model $model
     * @param array $queryParams
     * @param array $additionalParams
     * @return string
     */
    public static function resolve(string $endpoint, Model $model, array $queryParams = [], array $additionalParams = []): string
    {
        // Extract all parameters from the endpoint
        $parameters = self::extractParameters($endpoint);
        
        if (empty($parameters)) {
            return $endpoint;
        }
        
        $resolvedEndpoint = $endpoint;
        
        foreach ($parameters as $parameter) {
            $value = self::resolveParameter($parameter, $model, $queryParams, $additionalParams);
            
            if ($value !== null) {
                $resolvedEndpoint = str_replace('{' . $parameter . '}', $value, $resolvedEndpoint);
            }
        }
        
        return $resolvedEndpoint;
    }
    
    /**
     * Extract parameter names from endpoint URL
     *
     * @param string $endpoint
     * @return array
     */
    public static function extractParameters(string $endpoint): array
    {
        preg_match_all('/\{([^}]+)\}/', $endpoint, $matches);
        return $matches[1] ?? [];
    }
    
    /**
     * Resolve a single parameter value using multiple strategies
     *
     * @param string $parameter
     * @param Model $model
     * @param array $queryParams
     * @param array $additionalParams
     * @return string|null
     */
    protected static function resolveParameter(string $parameter, Model $model, array $queryParams, array $additionalParams): ?string
    {
        // Strategy 1: Check additional parameters first (highest priority)
        if (isset($additionalParams[$parameter])) {
            return (string) $additionalParams[$parameter];
        }
        
        // Strategy 2: Check query parameters
        if (isset($queryParams[$parameter])) {
            return (string) $queryParams[$parameter];
        }
        
        // Strategy 3: Check model attributes
        if ($model->hasAttribute($parameter)) {
            $value = $model->getAttribute($parameter);
            if ($value !== null) {
                return (string) $value;
            }
        }
        
        // Strategy 4: Check for related model attributes (smart resolution)
        $relatedValue = self::resolveRelatedParameter($parameter, $model);
        if ($relatedValue !== null) {
            return $relatedValue;
        }
        
        // Strategy 5: Check for common ID patterns
        $idValue = self::resolveIdPattern($parameter, $model, $queryParams);
        if ($idValue !== null) {
            return $idValue;
        }
        
        return null;
    }
    
    /**
     * Resolve parameter from related models or relationships
     *
     * @param string $parameter
     * @param Model $model
     * @return string|null
     */
    protected static function resolveRelatedParameter(string $parameter, Model $model): ?string
    {
        // Check if parameter follows a pattern like "product_id", "category_id", etc.
        if (Str::endsWith($parameter, '_id')) {
            $relationName = Str::beforeLast($parameter, '_id');
            
            // Check if model has this attribute directly
            if ($model->hasAttribute($parameter)) {
                $value = $model->getAttribute($parameter);
                if ($value !== null) {
                    return (string) $value;
                }
            }
            
            // Check if there's a loaded relationship
            if ($model->relationLoaded($relationName)) {
                $relatedModel = $model->getRelation($relationName);
                if ($relatedModel && $relatedModel->getKey()) {
                    return (string) $relatedModel->getKey();
                }
            }
            
            // Check if there's a relationship method
            if (method_exists($model, $relationName)) {
                try {
                    $relatedModel = $model->$relationName;
                    if ($relatedModel && $relatedModel->getKey()) {
                        return (string) $relatedModel->getKey();
                    }
                } catch (\Exception $e) {
                    // Relationship might not be loaded or accessible
                }
            }
        }
        
        return null;
    }
    
    /**
     * Resolve common ID patterns
     *
     * @param string $parameter
     * @param Model $model
     * @param array $queryParams
     * @return string|null
     */
    protected static function resolveIdPattern(string $parameter, Model $model, array $queryParams): ?string
    {
        // Handle common patterns like "id", "product_id", etc.
        switch ($parameter) {
            case 'id':
                // Use model's primary key
                if ($model->getKey()) {
                    return (string) $model->getKey();
                }
                break;
                
            default:
                // Check if parameter exists in query params with different casing
                foreach ($queryParams as $key => $value) {
                    if (strtolower($key) === strtolower($parameter)) {
                        return (string) $value;
                    }
                }
                break;
        }
        
        return null;
    }
    
    /**
     * Check if endpoint has unresolved parameters
     *
     * @param string $endpoint
     * @return bool
     */
    public static function hasUnresolvedParameters(string $endpoint): bool
    {
        return preg_match('/\{[^}]+\}/', $endpoint) === 1;
    }
    
    /**
     * Get list of unresolved parameters
     *
     * @param string $endpoint
     * @return array
     */
    public static function getUnresolvedParameters(string $endpoint): array
    {
        preg_match_all('/\{([^}]+)\}/', $endpoint, $matches);
        return $matches[1] ?? [];
    }
    
    /**
     * Validate that all required parameters are resolved
     *
     * @param string $originalEndpoint
     * @param string $resolvedEndpoint
     * @param array $requiredParams
     * @return array
     */
    public static function validateResolution(string $originalEndpoint, string $resolvedEndpoint, array $requiredParams = []): array
    {
        $unresolved = self::getUnresolvedParameters($resolvedEndpoint);
        $errors = [];
        
        foreach ($unresolved as $param) {
            if (in_array($param, $requiredParams) || empty($requiredParams)) {
                $errors[] = "Required parameter '{$param}' could not be resolved in endpoint: {$originalEndpoint}";
            }
        }
        
        return $errors;
    }
    
    /**
     * Build endpoint with fallback strategies
     *
     * @param string $endpoint
     * @param Model $model
     * @param array $queryParams
     * @param array $additionalParams
     * @param bool $throwOnUnresolved
     * @return string
     * @throws \InvalidArgumentException
     */
    public static function buildEndpoint(
        string $endpoint, 
        Model $model, 
        array $queryParams = [], 
        array $additionalParams = [], 
        bool $throwOnUnresolved = false
    ): string {
        $originalEndpoint = $endpoint;
        $resolvedEndpoint = self::resolve($endpoint, $model, $queryParams, $additionalParams);
        
        if ($throwOnUnresolved && self::hasUnresolvedParameters($resolvedEndpoint)) {
            $unresolved = self::getUnresolvedParameters($resolvedEndpoint);
            throw new \InvalidArgumentException(
                "Could not resolve parameters [" . implode(', ', $unresolved) . "] in endpoint: {$originalEndpoint}"
            );
        }
        
        return $resolvedEndpoint;
    }
}
