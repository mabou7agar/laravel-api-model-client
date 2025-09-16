<?php

namespace MTechStack\LaravelApiModelClient\Testing;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use MTechStack\LaravelApiModelClient\OpenApi\OpenApiSchemaParser;

/**
 * Coverage Analyzer for API testing
 */
class CoverageAnalyzer
{
    protected OpenApiSchemaParser $parser;

    public function __construct()
    {
        $this->parser = new OpenApiSchemaParser();
    }

    /**
     * Analyze test coverage
     */
    public function analyze(?string $schema, array $models, array $endpoints, bool $dryRun): array
    {
        if ($dryRun) {
            return [
                'dry_run' => true,
                'summary' => [
                    'schema_coverage' => 85.5,
                    'endpoint_coverage' => 78.2,
                    'model_coverage' => 92.1,
                    'overall_coverage' => 85.3,
                ],
            ];
        }

        $results = [];
        $schemas = $this->getSchemas($schema);

        foreach ($schemas as $schemaName => $schemaConfig) {
            $results[$schemaName] = $this->analyzeSchema($schemaConfig, $models, $endpoints);
        }

        return [
            'schemas' => $results,
            'summary' => $this->calculateOverallCoverage($results),
        ];
    }

    /**
     * Analyze individual schema coverage
     */
    protected function analyzeSchema(array $schemaConfig, array $models, array $endpoints): array
    {
        $source = $schemaConfig['source'] ?? null;
        if (!$source) {
            return [
                'error' => 'No schema source configured',
                'coverage' => 0,
            ];
        }

        try {
            $parsed = $this->parser->parse($source, false);
            
            $schemaCoverage = $this->analyzeSchemaDefinitions($parsed, $models);
            $endpointCoverage = $this->analyzeEndpointCoverage($parsed, $endpoints);
            $modelCoverage = $this->analyzeModelCoverage($models);
            $validationCoverage = $this->analyzeValidationCoverage($parsed, $models);

            return [
                'schema_definitions' => $schemaCoverage,
                'endpoints' => $endpointCoverage,
                'models' => $modelCoverage,
                'validation' => $validationCoverage,
                'overall_coverage' => $this->calculateSchemaCoverage([
                    $schemaCoverage,
                    $endpointCoverage,
                    $modelCoverage,
                    $validationCoverage,
                ]),
            ];
            
        } catch (\Exception $e) {
            return [
                'error' => $e->getMessage(),
                'coverage' => 0,
            ];
        }
    }

    /**
     * Analyze schema definitions coverage
     */
    protected function analyzeSchemaDefinitions(array $parsed, array $models): array
    {
        $schemas = $parsed['schemas'] ?? [];
        $totalSchemas = count($schemas);
        
        if ($totalSchemas === 0) {
            return [
                'total_schemas' => 0,
                'covered_schemas' => 0,
                'coverage_percentage' => 0,
                'uncovered_schemas' => [],
            ];
        }

        $coveredSchemas = [];
        $uncoveredSchemas = [];

        foreach ($schemas as $schemaName => $schemaDefinition) {
            $modelExists = $this->modelExists($schemaName, $models);
            
            if ($modelExists) {
                $coveredSchemas[] = $schemaName;
            } else {
                $uncoveredSchemas[] = $schemaName;
            }
        }

        return [
            'total_schemas' => $totalSchemas,
            'covered_schemas' => count($coveredSchemas),
            'coverage_percentage' => round((count($coveredSchemas) / $totalSchemas) * 100, 2),
            'covered_schema_list' => $coveredSchemas,
            'uncovered_schemas' => $uncoveredSchemas,
        ];
    }

    /**
     * Analyze endpoint coverage
     */
    protected function analyzeEndpointCoverage(array $parsed, array $endpoints): array
    {
        $schemaEndpoints = $parsed['endpoints'] ?? [];
        $totalEndpoints = count($schemaEndpoints);
        
        if ($totalEndpoints === 0) {
            return [
                'total_endpoints' => 0,
                'covered_endpoints' => 0,
                'coverage_percentage' => 0,
                'uncovered_endpoints' => [],
            ];
        }

        $coveredEndpoints = [];
        $uncoveredEndpoints = [];

        foreach ($schemaEndpoints as $endpoint => $endpointDefinition) {
            $endpointTested = in_array($endpoint, $endpoints) || 
                             $this->hasEndpointTest($endpoint);
            
            if ($endpointTested) {
                $coveredEndpoints[] = $endpoint;
            } else {
                $uncoveredEndpoints[] = $endpoint;
            }
        }

        return [
            'total_endpoints' => $totalEndpoints,
            'covered_endpoints' => count($coveredEndpoints),
            'coverage_percentage' => round((count($coveredEndpoints) / $totalEndpoints) * 100, 2),
            'covered_endpoint_list' => $coveredEndpoints,
            'uncovered_endpoints' => $uncoveredEndpoints,
            'endpoint_methods' => $this->analyzeEndpointMethods($schemaEndpoints, $coveredEndpoints),
        ];
    }

    /**
     * Analyze model coverage
     */
    protected function analyzeModelCoverage(array $models): array
    {
        $modelCoverage = [];
        $totalModels = count($models);
        $coveredModels = 0;

        foreach ($models as $modelName) {
            $coverage = $this->analyzeModelFileCoverage($modelName);
            $modelCoverage[$modelName] = $coverage;
            
            if ($coverage['coverage_percentage'] > 0) {
                $coveredModels++;
            }
        }

        return [
            'total_models' => $totalModels,
            'covered_models' => $coveredModels,
            'coverage_percentage' => $totalModels > 0 ? round(($coveredModels / $totalModels) * 100, 2) : 0,
            'model_details' => $modelCoverage,
        ];
    }

    /**
     * Analyze validation coverage
     */
    protected function analyzeValidationCoverage(array $parsed, array $models): array
    {
        $schemas = $parsed['schemas'] ?? [];
        $totalValidationRules = 0;
        $implementedValidationRules = 0;

        foreach ($schemas as $schemaName => $schemaDefinition) {
            $schemaRules = $this->countSchemaValidationRules($schemaDefinition);
            $totalValidationRules += $schemaRules;

            if ($this->modelExists($schemaName, $models)) {
                $modelRules = $this->countModelValidationRules($schemaName);
                $implementedValidationRules += min($modelRules, $schemaRules);
            }
        }

        return [
            'total_validation_rules' => $totalValidationRules,
            'implemented_validation_rules' => $implementedValidationRules,
            'coverage_percentage' => $totalValidationRules > 0 ? 
                round(($implementedValidationRules / $totalValidationRules) * 100, 2) : 0,
        ];
    }

    /**
     * Analyze endpoint methods coverage
     */
    protected function analyzeEndpointMethods(array $schemaEndpoints, array $coveredEndpoints): array
    {
        $methodCoverage = [];
        $methods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];

        foreach ($methods as $method) {
            $totalMethodEndpoints = 0;
            $coveredMethodEndpoints = 0;

            foreach ($schemaEndpoints as $endpoint => $definition) {
                if (isset($definition[strtolower($method)])) {
                    $totalMethodEndpoints++;
                    
                    if (in_array($endpoint, $coveredEndpoints)) {
                        $coveredMethodEndpoints++;
                    }
                }
            }

            $methodCoverage[$method] = [
                'total' => $totalMethodEndpoints,
                'covered' => $coveredMethodEndpoints,
                'coverage_percentage' => $totalMethodEndpoints > 0 ? 
                    round(($coveredMethodEndpoints / $totalMethodEndpoints) * 100, 2) : 0,
            ];
        }

        return $methodCoverage;
    }

    /**
     * Analyze model file coverage
     */
    protected function analyzeModelFileCoverage(string $modelName): array
    {
        $modelClass = $this->getModelClass($modelName);
        
        if (!class_exists($modelClass)) {
            return [
                'exists' => false,
                'coverage_percentage' => 0,
                'features' => [],
            ];
        }

        try {
            $model = new $modelClass();
            $reflection = new \ReflectionClass($model);
            
            $features = [
                'has_fillable' => !empty($model->getFillable()),
                'has_casts' => !empty($model->getCasts()),
                'has_relationships' => $this->hasRelationships($reflection),
                'has_validation' => $this->hasValidation($model),
                'has_openapi_integration' => $this->hasOpenApiIntegration($model),
                'has_custom_methods' => $this->hasCustomMethods($reflection),
            ];

            $implementedFeatures = count(array_filter($features));
            $totalFeatures = count($features);
            $coveragePercentage = round(($implementedFeatures / $totalFeatures) * 100, 2);

            return [
                'exists' => true,
                'coverage_percentage' => $coveragePercentage,
                'features' => $features,
                'implemented_features' => $implementedFeatures,
                'total_features' => $totalFeatures,
            ];
            
        } catch (\Exception $e) {
            return [
                'exists' => true,
                'error' => $e->getMessage(),
                'coverage_percentage' => 0,
            ];
        }
    }

    /**
     * Check if model exists
     */
    protected function modelExists(string $schemaName, array $models): bool
    {
        // Check if model name is in the provided models list
        if (in_array($schemaName, $models)) {
            return true;
        }

        // Check if model class exists
        $modelClass = $this->getModelClass($schemaName);
        return class_exists($modelClass);
    }

    /**
     * Check if endpoint has test
     */
    protected function hasEndpointTest(string $endpoint): bool
    {
        // This is a simplified check - in a real implementation,
        // you might scan test files for endpoint references
        $testDirectories = [
            base_path('tests/Feature'),
            base_path('tests/Unit'),
        ];

        foreach ($testDirectories as $directory) {
            if (!File::exists($directory)) {
                continue;
            }

            $files = File::allFiles($directory);
            foreach ($files as $file) {
                if (str_contains($file->getContents(), $endpoint)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Count schema validation rules
     */
    protected function countSchemaValidationRules(array $schemaDefinition): int
    {
        $count = 0;
        $properties = $schemaDefinition['properties'] ?? [];

        foreach ($properties as $property => $definition) {
            // Count various validation constraints
            if (isset($definition['required'])) $count++;
            if (isset($definition['type'])) $count++;
            if (isset($definition['format'])) $count++;
            if (isset($definition['minimum'])) $count++;
            if (isset($definition['maximum'])) $count++;
            if (isset($definition['minLength'])) $count++;
            if (isset($definition['maxLength'])) $count++;
            if (isset($definition['pattern'])) $count++;
            if (isset($definition['enum'])) $count++;
        }

        return $count;
    }

    /**
     * Count model validation rules
     */
    protected function countModelValidationRules(string $modelName): int
    {
        $modelClass = $this->getModelClass($modelName);
        
        if (!class_exists($modelClass)) {
            return 0;
        }

        try {
            $model = new $modelClass();
            
            // Check for validation rules method
            if (method_exists($model, 'rules')) {
                $rules = $model->rules();
                return is_array($rules) ? count($rules) : 0;
            }

            if (method_exists($model, 'getValidationRules')) {
                $rules = $model->getValidationRules();
                return is_array($rules) ? count($rules) : 0;
            }

            // Count fillable properties as basic validation
            return count($model->getFillable());
            
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Check if model has relationships
     */
    protected function hasRelationships(\ReflectionClass $reflection): bool
    {
        $methods = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC);
        
        foreach ($methods as $method) {
            if ($method->getNumberOfParameters() === 0 && 
                !in_array($method->getName(), ['getKey', 'getTable', 'getKeyName']) &&
                !str_starts_with($method->getName(), 'get') &&
                !str_starts_with($method->getName(), 'set')) {
                
                try {
                    $model = $reflection->newInstance();
                    $result = $method->invoke($model);
                    if (is_object($result) && method_exists($result, 'getRelated')) {
                        return true;
                    }
                } catch (\Exception $e) {
                    // Ignore errors
                }
            }
        }

        return false;
    }

    /**
     * Check if model has validation
     */
    protected function hasValidation($model): bool
    {
        return method_exists($model, 'rules') || 
               method_exists($model, 'getValidationRules') ||
               method_exists($model, 'validateParameters');
    }

    /**
     * Check if model has OpenAPI integration
     */
    protected function hasOpenApiIntegration($model): bool
    {
        $traits = class_uses_recursive(get_class($model));
        return in_array('MTechStack\\LaravelApiModelClient\\Traits\\HasOpenApiSchema', $traits);
    }

    /**
     * Check if model has custom methods
     */
    protected function hasCustomMethods(\ReflectionClass $reflection): bool
    {
        $methods = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC);
        $customMethods = 0;

        foreach ($methods as $method) {
            // Skip inherited methods from parent classes
            if ($method->getDeclaringClass()->getName() !== $reflection->getName()) {
                continue;
            }

            // Skip magic methods and accessors/mutators
            if (str_starts_with($method->getName(), '__') ||
                str_starts_with($method->getName(), 'get') ||
                str_starts_with($method->getName(), 'set')) {
                continue;
            }

            $customMethods++;
        }

        return $customMethods > 0;
    }

    /**
     * Calculate overall coverage
     */
    protected function calculateOverallCoverage(array $results): array
    {
        $totalCoverage = [];
        $schemaCount = 0;

        foreach ($results as $schemaResult) {
            if (isset($schemaResult['error'])) {
                continue;
            }

            $schemaCount++;
            
            if (isset($schemaResult['schema_definitions']['coverage_percentage'])) {
                $totalCoverage['schema'] = ($totalCoverage['schema'] ?? 0) + $schemaResult['schema_definitions']['coverage_percentage'];
            }
            
            if (isset($schemaResult['endpoints']['coverage_percentage'])) {
                $totalCoverage['endpoint'] = ($totalCoverage['endpoint'] ?? 0) + $schemaResult['endpoints']['coverage_percentage'];
            }
            
            if (isset($schemaResult['models']['coverage_percentage'])) {
                $totalCoverage['model'] = ($totalCoverage['model'] ?? 0) + $schemaResult['models']['coverage_percentage'];
            }
            
            if (isset($schemaResult['validation']['coverage_percentage'])) {
                $totalCoverage['validation'] = ($totalCoverage['validation'] ?? 0) + $schemaResult['validation']['coverage_percentage'];
            }
        }

        if ($schemaCount === 0) {
            return [
                'schema_coverage' => 0,
                'endpoint_coverage' => 0,
                'model_coverage' => 0,
                'validation_coverage' => 0,
                'overall_coverage' => 0,
            ];
        }

        $schemaCoverage = round(($totalCoverage['schema'] ?? 0) / $schemaCount, 2);
        $endpointCoverage = round(($totalCoverage['endpoint'] ?? 0) / $schemaCount, 2);
        $modelCoverage = round(($totalCoverage['model'] ?? 0) / $schemaCount, 2);
        $validationCoverage = round(($totalCoverage['validation'] ?? 0) / $schemaCount, 2);

        return [
            'schema_coverage' => $schemaCoverage,
            'endpoint_coverage' => $endpointCoverage,
            'model_coverage' => $modelCoverage,
            'validation_coverage' => $validationCoverage,
            'overall_coverage' => round(($schemaCoverage + $endpointCoverage + $modelCoverage + $validationCoverage) / 4, 2),
        ];
    }

    /**
     * Calculate schema coverage
     */
    protected function calculateSchemaCoverage(array $coverageResults): float
    {
        $totalCoverage = 0;
        $count = 0;

        foreach ($coverageResults as $result) {
            if (isset($result['coverage_percentage'])) {
                $totalCoverage += $result['coverage_percentage'];
                $count++;
            }
        }

        return $count > 0 ? round($totalCoverage / $count, 2) : 0;
    }

    /**
     * Get schemas for analysis
     */
    protected function getSchemas(?string $schema): array
    {
        $schemas = Config::get('api-client.schemas', []);
        
        if ($schema) {
            return isset($schemas[$schema]) ? [$schema => $schemas[$schema]] : [];
        }
        
        return $schemas;
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
}
