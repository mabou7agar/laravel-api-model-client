<?php

namespace MTechStack\LaravelApiModelClient\Configuration;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\File;
use MTechStack\LaravelApiModelClient\OpenApi\OpenApiSchemaParser;
use MTechStack\LaravelApiModelClient\Exceptions\ConfigurationException;
use Carbon\Carbon;

/**
 * Configuration validator and health checker for OpenAPI integration
 */
class ConfigurationValidator
{
    protected array $config;
    protected array $errors = [];
    protected array $warnings = [];
    protected array $healthChecks = [];

    public function __construct()
    {
        $this->config = Config::get('api-client', []);
    }

    /**
     * Validate the entire configuration
     */
    public function validate(): array
    {
        $this->errors = [];
        $this->warnings = [];

        $this->validateGlobalConfiguration();
        $this->validateSchemas();
        $this->validateVersioningConfiguration();
        $this->validateCachingConfiguration();
        $this->validateSecurityConfiguration();
        $this->validateLoggingConfiguration();

        return [
            'valid' => empty($this->errors),
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'summary' => $this->generateValidationSummary(),
        ];
    }

    /**
     * Perform health checks on all configured schemas
     */
    public function performHealthChecks(): array
    {
        $this->healthChecks = [];

        if (!$this->isHealthChecksEnabled()) {
            return [
                'enabled' => false,
                'message' => 'Health checks are disabled in configuration',
            ];
        }

        $schemas = $this->config['schemas'] ?? [];
        $healthConfig = $this->config['health_checks'] ?? [];

        foreach ($schemas as $schemaName => $schemaConfig) {
            if (!($schemaConfig['enabled'] ?? false)) {
                continue;
            }

            $this->healthChecks[$schemaName] = $this->performSchemaHealthCheck($schemaName, $schemaConfig, $healthConfig);
        }

        return [
            'enabled' => true,
            'timestamp' => Carbon::now()->toISOString(),
            'overall_status' => $this->calculateOverallHealthStatus(),
            'schemas' => $this->healthChecks,
            'summary' => $this->generateHealthSummary(),
        ];
    }

    /**
     * Validate global configuration settings
     */
    protected function validateGlobalConfiguration(): void
    {
        // Validate default schema
        $defaultSchema = $this->config['default_schema'] ?? null;
        if (!$defaultSchema) {
            $this->errors[] = 'Default schema is not configured';
        } elseif (!isset($this->config['schemas'][$defaultSchema])) {
            $this->errors[] = "Default schema '{$defaultSchema}' does not exist in schemas configuration";
        }

        // Validate required directories
        $this->validateDirectories();

        // Validate environment variables
        $this->validateEnvironmentVariables();
    }

    /**
     * Validate all schema configurations
     */
    protected function validateSchemas(): void
    {
        $schemas = $this->config['schemas'] ?? [];

        if (empty($schemas)) {
            $this->errors[] = 'No schemas configured';
            return;
        }

        foreach ($schemas as $schemaName => $schemaConfig) {
            $this->validateSchema($schemaName, $schemaConfig);
        }
    }

    /**
     * Validate a single schema configuration
     */
    protected function validateSchema(string $schemaName, array $schemaConfig): void
    {
        $context = "Schema '{$schemaName}'";

        // Required fields
        $requiredFields = ['name', 'base_url'];
        foreach ($requiredFields as $field) {
            if (!isset($schemaConfig[$field]) || empty($schemaConfig[$field])) {
                $this->errors[] = "{$context}: Missing required field '{$field}'";
            }
        }

        // Validate URL format
        if (isset($schemaConfig['base_url'])) {
            if (!filter_var($schemaConfig['base_url'], FILTER_VALIDATE_URL)) {
                $this->errors[] = "{$context}: Invalid base_url format";
            }
        }

        // Validate source if provided
        if (isset($schemaConfig['source']) && !empty($schemaConfig['source'])) {
            $this->validateSchemaSource($schemaName, $schemaConfig['source']);
        }

        // Validate authentication configuration
        $this->validateAuthentication($schemaName, $schemaConfig['authentication'] ?? []);

        // Validate model generation configuration
        $this->validateModelGeneration($schemaName, $schemaConfig['model_generation'] ?? []);

        // Validate validation configuration
        $this->validateValidationConfig($schemaName, $schemaConfig['validation'] ?? []);

        // Validate caching configuration
        $this->validateSchemaCaching($schemaName, $schemaConfig['caching'] ?? []);
    }

    /**
     * Validate schema source accessibility
     */
    protected function validateSchemaSource(string $schemaName, string $source): void
    {
        $context = "Schema '{$schemaName}' source";

        if (filter_var($source, FILTER_VALIDATE_URL)) {
            // Remote URL validation
            try {
                $response = Http::timeout(10)->get($source);
                if (!$response->successful()) {
                    $this->errors[] = "{$context}: Remote schema not accessible (HTTP {$response->status()})";
                }
            } catch (\Exception $e) {
                $this->errors[] = "{$context}: Failed to access remote schema - {$e->getMessage()}";
            }
        } else {
            // Local file validation
            if (!File::exists($source)) {
                $this->errors[] = "{$context}: Local schema file does not exist at '{$source}'";
            } elseif (!File::isReadable($source)) {
                $this->errors[] = "{$context}: Local schema file is not readable at '{$source}'";
            } else {
                // Validate schema content
                try {
                    $parser = new OpenApiSchemaParser();
                    $parser->parse($source, false); // Don't use cache for validation
                } catch (\Exception $e) {
                    $this->errors[] = "{$context}: Invalid OpenAPI schema - {$e->getMessage()}";
                }
            }
        }
    }

    /**
     * Validate authentication configuration
     */
    protected function validateAuthentication(string $schemaName, array $authConfig): void
    {
        $context = "Schema '{$schemaName}' authentication";
        $authType = $authConfig['type'] ?? null;

        if (!$authType) {
            $this->warnings[] = "{$context}: No authentication type specified";
            return;
        }

        $validTypes = ['bearer', 'api_key', 'basic', 'oauth2'];
        if (!in_array($authType, $validTypes)) {
            $this->errors[] = "{$context}: Invalid authentication type '{$authType}'. Valid types: " . implode(', ', $validTypes);
        }

        // Validate type-specific requirements
        switch ($authType) {
            case 'bearer':
                if (empty($authConfig['token'])) {
                    $this->warnings[] = "{$context}: Bearer token not configured";
                }
                break;

            case 'api_key':
                if (empty($authConfig['api_key'])) {
                    $this->warnings[] = "{$context}: API key not configured";
                }
                if (empty($authConfig['api_key_header'])) {
                    $this->warnings[] = "{$context}: API key header not specified";
                }
                break;

            case 'basic':
                if (empty($authConfig['username']) || empty($authConfig['password'])) {
                    $this->warnings[] = "{$context}: Basic auth credentials not configured";
                }
                break;

            case 'oauth2':
                // OAuth2 validation would be more complex
                $this->warnings[] = "{$context}: OAuth2 configuration should be validated manually";
                break;
        }
    }

    /**
     * Validate model generation configuration
     */
    protected function validateModelGeneration(string $schemaName, array $modelConfig): void
    {
        $context = "Schema '{$schemaName}' model generation";

        if (!($modelConfig['enabled'] ?? false)) {
            return;
        }

        // Validate namespace
        if (isset($modelConfig['namespace'])) {
            if (!preg_match('/^[A-Za-z_][A-Za-z0-9_\\\\]*$/', $modelConfig['namespace'])) {
                $this->errors[] = "{$context}: Invalid namespace format";
            }
        }

        // Validate output directory
        if (isset($modelConfig['output_directory'])) {
            $outputDir = $modelConfig['output_directory'];
            if (!File::isDirectory(dirname($outputDir))) {
                $this->warnings[] = "{$context}: Output directory parent does not exist: " . dirname($outputDir);
            }
        }

        // Validate naming convention
        $namingConvention = $modelConfig['naming_convention'] ?? 'pascal_case';
        $validConventions = ['pascal_case', 'snake_case', 'camel_case'];
        if (!in_array($namingConvention, $validConventions)) {
            $this->errors[] = "{$context}: Invalid naming convention '{$namingConvention}'. Valid options: " . implode(', ', $validConventions);
        }
    }

    /**
     * Validate validation configuration
     */
    protected function validateValidationConfig(string $schemaName, array $validationConfig): void
    {
        $context = "Schema '{$schemaName}' validation";

        $strictness = $validationConfig['strictness'] ?? 'strict';
        $validStrictness = ['strict', 'moderate', 'lenient'];
        if (!in_array($strictness, $validStrictness)) {
            $this->errors[] = "{$context}: Invalid strictness level '{$strictness}'. Valid options: " . implode(', ', $validStrictness);
        }
    }

    /**
     * Validate schema-specific caching configuration
     */
    protected function validateSchemaCaching(string $schemaName, array $cachingConfig): void
    {
        $context = "Schema '{$schemaName}' caching";

        if (!($cachingConfig['enabled'] ?? true)) {
            return;
        }

        // Validate TTL
        $ttl = $cachingConfig['ttl'] ?? 3600;
        if (!is_numeric($ttl) || $ttl < 0) {
            $this->errors[] = "{$context}: Invalid TTL value '{$ttl}'. Must be a positive number";
        }

        // Validate cache store
        $store = $cachingConfig['store'] ?? 'default';
        try {
            Cache::store($store);
        } catch (\Exception $e) {
            $this->errors[] = "{$context}: Invalid cache store '{$store}' - {$e->getMessage()}";
        }
    }

    /**
     * Validate versioning configuration
     */
    protected function validateVersioningConfiguration(): void
    {
        $versioningConfig = $this->config['versioning'] ?? [];

        if (!($versioningConfig['enabled'] ?? false)) {
            return;
        }

        // Validate storage path
        $storagePath = $versioningConfig['storage_path'] ?? null;
        if ($storagePath && !File::isDirectory(dirname($storagePath))) {
            $this->warnings[] = "Versioning storage path parent directory does not exist: " . dirname($storagePath);
        }

        // Validate migration strategy
        $strategy = $versioningConfig['migration_strategy'] ?? 'backup_and_replace';
        $validStrategies = ['backup_and_replace', 'merge', 'manual'];
        if (!in_array($strategy, $validStrategies)) {
            $this->errors[] = "Invalid migration strategy '{$strategy}'. Valid options: " . implode(', ', $validStrategies);
        }

        // Validate version format
        $versionFormat = $versioningConfig['version_format'] ?? 'Y-m-d_H-i-s';
        try {
            Carbon::now()->format($versionFormat);
        } catch (\Exception $e) {
            $this->errors[] = "Invalid version format '{$versionFormat}' - {$e->getMessage()}";
        }
    }

    /**
     * Validate global caching configuration
     */
    protected function validateCachingConfiguration(): void
    {
        $cachingConfig = $this->config['caching'] ?? [];

        if (!($cachingConfig['enabled'] ?? true)) {
            return;
        }

        // Validate default store
        $store = $cachingConfig['store'] ?? 'default';
        try {
            Cache::store($store);
        } catch (\Exception $e) {
            $this->errors[] = "Invalid default cache store '{$store}' - {$e->getMessage()}";
        }

        // Validate serialization method
        $serialization = $cachingConfig['serialization'] ?? 'json';
        $validMethods = ['json', 'serialize', 'igbinary'];
        if (!in_array($serialization, $validMethods)) {
            $this->errors[] = "Invalid serialization method '{$serialization}'. Valid options: " . implode(', ', $validMethods);
        }

        if ($serialization === 'igbinary' && !extension_loaded('igbinary')) {
            $this->errors[] = "igbinary extension is not loaded but specified as serialization method";
        }
    }

    /**
     * Validate security configuration
     */
    protected function validateSecurityConfiguration(): void
    {
        $securityConfig = $this->config['security'] ?? [];

        // Validate SSL certificate paths
        if (isset($securityConfig['ssl_cert_path']) && !File::exists($securityConfig['ssl_cert_path'])) {
            $this->errors[] = "SSL certificate file does not exist: " . $securityConfig['ssl_cert_path'];
        }

        if (isset($securityConfig['ssl_key_path']) && !File::exists($securityConfig['ssl_key_path'])) {
            $this->errors[] = "SSL key file does not exist: " . $securityConfig['ssl_key_path'];
        }

        // Validate encryption configuration
        $encryptionConfig = $securityConfig['encryption'] ?? [];
        if ($encryptionConfig['enabled'] ?? false) {
            if (empty($encryptionConfig['key'])) {
                $this->errors[] = "Encryption is enabled but no encryption key is configured";
            }

            $algorithm = $encryptionConfig['algorithm'] ?? 'AES-256-CBC';
            if (!in_array($algorithm, openssl_get_cipher_methods())) {
                $this->errors[] = "Invalid encryption algorithm '{$algorithm}'";
            }
        }
    }

    /**
     * Validate logging configuration
     */
    protected function validateLoggingConfiguration(): void
    {
        $loggingConfig = $this->config['logging'] ?? [];

        if (!($loggingConfig['enabled'] ?? true)) {
            return;
        }

        // Validate log level
        $level = $loggingConfig['level'] ?? 'info';
        $validLevels = ['debug', 'info', 'warning', 'error'];
        if (!in_array($level, $validLevels)) {
            $this->errors[] = "Invalid log level '{$level}'. Valid options: " . implode(', ', $validLevels);
        }

        // Validate log channel
        $channel = $loggingConfig['channel'] ?? 'default';
        try {
            Log::channel($channel);
        } catch (\Exception $e) {
            $this->warnings[] = "Log channel '{$channel}' may not be properly configured - {$e->getMessage()}";
        }
    }

    /**
     * Validate required directories exist
     */
    protected function validateDirectories(): void
    {
        $directories = [
            'storage/api-client/schemas' => 'Schema storage directory',
            'storage/api-client/mocks' => 'Mock data directory',
        ];

        foreach ($directories as $path => $description) {
            $fullPath = base_path($path);
            if (!File::isDirectory($fullPath)) {
                $this->warnings[] = "{$description} does not exist: {$fullPath}";
            }
        }
    }

    /**
     * Validate critical environment variables
     */
    protected function validateEnvironmentVariables(): void
    {
        $criticalEnvVars = [
            'API_CLIENT_DEFAULT_SCHEMA',
            'API_CLIENT_PRIMARY_SCHEMA',
            'API_CLIENT_PRIMARY_BASE_URL',
        ];

        foreach ($criticalEnvVars as $envVar) {
            if (!env($envVar)) {
                $this->warnings[] = "Environment variable '{$envVar}' is not set";
            }
        }
    }

    /**
     * Perform health check for a specific schema
     */
    protected function performSchemaHealthCheck(string $schemaName, array $schemaConfig, array $healthConfig): array
    {
        $checks = $healthConfig['checks'] ?? [];
        $timeout = $healthConfig['timeout'] ?? 30;
        $results = [];

        // Schema accessibility check
        if ($checks['schema_accessibility'] ?? true) {
            $results['schema_accessibility'] = $this->checkSchemaAccessibility($schemaConfig, $timeout);
        }

        // Schema validity check
        if ($checks['schema_validity'] ?? true) {
            $results['schema_validity'] = $this->checkSchemaValidity($schemaConfig);
        }

        // Endpoint connectivity check
        if ($checks['endpoint_connectivity'] ?? true) {
            $results['endpoint_connectivity'] = $this->checkEndpointConnectivity($schemaConfig, $timeout);
        }

        // Authentication check
        if ($checks['authentication'] ?? true) {
            $results['authentication'] = $this->checkAuthentication($schemaConfig, $timeout);
        }

        // Response time check
        if ($checks['response_time'] ?? true) {
            $results['response_time'] = $this->checkResponseTime($schemaConfig, $healthConfig['thresholds'] ?? []);
        }

        // Cache health check
        if ($checks['cache_health'] ?? true) {
            $results['cache_health'] = $this->checkCacheHealth($schemaConfig);
        }

        return [
            'status' => $this->calculateSchemaHealthStatus($results),
            'timestamp' => Carbon::now()->toISOString(),
            'checks' => $results,
        ];
    }

    /**
     * Check if schema source is accessible
     */
    protected function checkSchemaAccessibility(array $schemaConfig, int $timeout): array
    {
        $source = $schemaConfig['source'] ?? null;
        
        if (!$source) {
            return [
                'status' => 'warning',
                'message' => 'No schema source configured',
            ];
        }

        try {
            if (filter_var($source, FILTER_VALIDATE_URL)) {
                $response = Http::timeout($timeout)->get($source);
                return [
                    'status' => $response->successful() ? 'healthy' : 'unhealthy',
                    'message' => $response->successful() ? 'Schema accessible' : "HTTP {$response->status()}",
                    'response_time' => $response->transferStats?->getTransferTime() ?? null,
                ];
            } else {
                return [
                    'status' => File::exists($source) && File::isReadable($source) ? 'healthy' : 'unhealthy',
                    'message' => File::exists($source) ? 'Local schema accessible' : 'Local schema not found',
                ];
            }
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check schema validity
     */
    protected function checkSchemaValidity(array $schemaConfig): array
    {
        $source = $schemaConfig['source'] ?? null;
        
        if (!$source) {
            return [
                'status' => 'warning',
                'message' => 'No schema source to validate',
            ];
        }

        try {
            $parser = new OpenApiSchemaParser();
            $result = $parser->parse($source, false);
            
            return [
                'status' => 'healthy',
                'message' => 'Schema is valid',
                'schemas_count' => count($result['schemas'] ?? []),
                'endpoints_count' => count($result['endpoints'] ?? []),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => "Schema validation failed: {$e->getMessage()}",
            ];
        }
    }

    /**
     * Check endpoint connectivity
     */
    protected function checkEndpointConnectivity(array $schemaConfig, int $timeout): array
    {
        $baseUrl = $schemaConfig['base_url'] ?? null;
        
        if (!$baseUrl) {
            return [
                'status' => 'warning',
                'message' => 'No base URL configured',
            ];
        }

        try {
            $response = Http::timeout($timeout)->get($baseUrl);
            return [
                'status' => $response->successful() ? 'healthy' : 'unhealthy',
                'message' => $response->successful() ? 'Endpoint accessible' : "HTTP {$response->status()}",
                'response_time' => $response->transferStats?->getTransferTime() ?? null,
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => "Connectivity failed: {$e->getMessage()}",
            ];
        }
    }

    /**
     * Check authentication
     */
    protected function checkAuthentication(array $schemaConfig, int $timeout): array
    {
        $authConfig = $schemaConfig['authentication'] ?? [];
        $baseUrl = $schemaConfig['base_url'] ?? null;
        
        if (!$baseUrl || empty($authConfig)) {
            return [
                'status' => 'warning',
                'message' => 'No authentication configured',
            ];
        }

        try {
            $request = Http::timeout($timeout);
            
            // Apply authentication
            switch ($authConfig['type'] ?? null) {
                case 'bearer':
                    if ($token = $authConfig['token'] ?? null) {
                        $request = $request->withToken($token);
                    }
                    break;
                    
                case 'api_key':
                    if ($apiKey = $authConfig['api_key'] ?? null) {
                        $header = $authConfig['api_key_header'] ?? 'X-API-Key';
                        $request = $request->withHeaders([$header => $apiKey]);
                    }
                    break;
                    
                case 'basic':
                    if ($username = $authConfig['username'] ?? null) {
                        $password = $authConfig['password'] ?? '';
                        $request = $request->withBasicAuth($username, $password);
                    }
                    break;
            }
            
            $response = $request->get($baseUrl);
            
            return [
                'status' => $response->status() === 401 ? 'unhealthy' : 'healthy',
                'message' => $response->status() === 401 ? 'Authentication failed' : 'Authentication working',
                'status_code' => $response->status(),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => "Authentication check failed: {$e->getMessage()}",
            ];
        }
    }

    /**
     * Check response time
     */
    protected function checkResponseTime(array $schemaConfig, array $thresholds): array
    {
        $baseUrl = $schemaConfig['base_url'] ?? null;
        $threshold = $thresholds['response_time_ms'] ?? 5000;
        
        if (!$baseUrl) {
            return [
                'status' => 'warning',
                'message' => 'No base URL configured',
            ];
        }

        try {
            $start = microtime(true);
            $response = Http::timeout(30)->get($baseUrl);
            $responseTime = (microtime(true) - $start) * 1000; // Convert to milliseconds
            
            return [
                'status' => $responseTime <= $threshold ? 'healthy' : 'warning',
                'message' => $responseTime <= $threshold ? 'Response time acceptable' : 'Response time above threshold',
                'response_time_ms' => round($responseTime, 2),
                'threshold_ms' => $threshold,
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => "Response time check failed: {$e->getMessage()}",
            ];
        }
    }

    /**
     * Check cache health
     */
    protected function checkCacheHealth(array $schemaConfig): array
    {
        $cachingConfig = $schemaConfig['caching'] ?? [];
        
        if (!($cachingConfig['enabled'] ?? true)) {
            return [
                'status' => 'warning',
                'message' => 'Caching disabled',
            ];
        }

        try {
            $store = $cachingConfig['store'] ?? 'default';
            $cache = Cache::store($store);
            
            // Test cache operations
            $testKey = 'api_client_health_check_' . time();
            $testValue = 'test_value';
            
            $cache->put($testKey, $testValue, 60);
            $retrieved = $cache->get($testKey);
            $cache->forget($testKey);
            
            return [
                'status' => $retrieved === $testValue ? 'healthy' : 'unhealthy',
                'message' => $retrieved === $testValue ? 'Cache working' : 'Cache read/write failed',
                'store' => $store,
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => "Cache check failed: {$e->getMessage()}",
            ];
        }
    }

    /**
     * Calculate overall health status
     */
    protected function calculateOverallHealthStatus(): string
    {
        $statuses = [];
        
        foreach ($this->healthChecks as $schemaChecks) {
            $statuses[] = $schemaChecks['status'];
        }
        
        if (in_array('unhealthy', $statuses)) {
            return 'unhealthy';
        }
        
        if (in_array('warning', $statuses)) {
            return 'warning';
        }
        
        return 'healthy';
    }

    /**
     * Calculate health status for a specific schema
     */
    protected function calculateSchemaHealthStatus(array $results): string
    {
        $statuses = array_column($results, 'status');
        
        if (in_array('unhealthy', $statuses)) {
            return 'unhealthy';
        }
        
        if (in_array('warning', $statuses)) {
            return 'warning';
        }
        
        return 'healthy';
    }

    /**
     * Generate validation summary
     */
    protected function generateValidationSummary(): array
    {
        return [
            'total_errors' => count($this->errors),
            'total_warnings' => count($this->warnings),
            'schemas_count' => count($this->config['schemas'] ?? []),
            'enabled_schemas' => count(array_filter($this->config['schemas'] ?? [], fn($s) => $s['enabled'] ?? false)),
        ];
    }

    /**
     * Generate health check summary
     */
    protected function generateHealthSummary(): array
    {
        $healthy = 0;
        $warning = 0;
        $unhealthy = 0;
        
        foreach ($this->healthChecks as $check) {
            switch ($check['status']) {
                case 'healthy':
                    $healthy++;
                    break;
                case 'warning':
                    $warning++;
                    break;
                case 'unhealthy':
                    $unhealthy++;
                    break;
            }
        }
        
        return [
            'total_schemas' => count($this->healthChecks),
            'healthy' => $healthy,
            'warning' => $warning,
            'unhealthy' => $unhealthy,
        ];
    }

    /**
     * Check if health checks are enabled
     */
    protected function isHealthChecksEnabled(): bool
    {
        return $this->config['health_checks']['enabled'] ?? true;
    }

    /**
     * Get validation errors
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Get validation warnings
     */
    public function getWarnings(): array
    {
        return $this->warnings;
    }

    /**
     * Check if configuration is valid
     */
    public function isValid(): bool
    {
        return empty($this->errors);
    }
}
