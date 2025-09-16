<?php

namespace MTechStack\LaravelApiModelClient\OpenApi;

use cebe\openapi\Reader;
use cebe\openapi\spec\OpenApi;
use cebe\openapi\spec\Operation;
use cebe\openapi\spec\Parameter;
use cebe\openapi\spec\PathItem;
use cebe\openapi\spec\Schema;
use cebe\openapi\spec\Reference;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use MTechStack\LaravelApiModelClient\OpenApi\Exceptions\OpenApiParsingException;
use MTechStack\LaravelApiModelClient\OpenApi\Exceptions\SchemaValidationException;
use MTechStack\LaravelApiModelClient\OpenApi\Traits\ExtractsEndpoints;
use MTechStack\LaravelApiModelClient\OpenApi\Traits\ExtractsSchemas;
use MTechStack\LaravelApiModelClient\OpenApi\Traits\GeneratesModelMappings;
use MTechStack\LaravelApiModelClient\OpenApi\Traits\GeneratesValidationRules;

/**
 * Comprehensive OpenAPI 3.0 Schema Parser
 * 
 * Parses OpenAPI JSON/YAML files and extracts endpoint definitions,
 * parameters, validation rules, and creates automatic model mappings.
 */
class OpenApiSchemaParser
{
    protected ?OpenApi $openApiSpec = null;
    protected string $cachePrefix = 'openapi_schema_';
    protected int $cacheTtl = 3600;
    protected array $endpoints = [];
    protected array $schemas = [];
    protected array $modelMappings = [];
    protected array $validationRules = [];
    
    protected array $config = [
        'cache_enabled' => true,
        'remote_timeout' => 30,
        'max_file_size' => 10485760, // 10MB
        'supported_versions' => ['3.0.0', '3.0.1', '3.0.2', '3.0.3', '3.1.0'],
    ];

    public function __construct(array $config = [])
    {
        $this->config = array_merge($this->config, $config);
    }

    /**
     * Parse OpenAPI schema from file or URL
     */
    public function parse(string $source, bool $useCache = true): array
    {
        try {
            $cacheKey = $this->getCacheKey($source);
            
            if ($useCache && $this->config['cache_enabled']) {
                $cached = Cache::get($cacheKey);
                if ($cached !== null) {
                    Log::info("OpenAPI schema loaded from cache", ['source' => $source]);
                    return $cached;
                }
            }

            $this->openApiSpec = $this->loadSchema($source);
            $this->validateOpenApiVersion();
            
            // Extract endpoints safely
            try {
                $this->extractEndpoints();
            } catch (\Exception $e) {
                Log::warning("Failed to extract endpoints: " . $e->getMessage());
                $this->endpoints = [];
            }
            
            // Extract schemas safely
            try {
                $this->extractSchemas();
            } catch (\Exception $e) {
                Log::warning("Failed to extract schemas: " . $e->getMessage());
                $this->schemas = [];
            }
            
            // Generate model mappings safely
            try {
                $this->generateModelMappings();
            } catch (\Exception $e) {
                Log::warning("Failed to generate model mappings: " . $e->getMessage());
                $this->modelMappings = [];
            }
            
            // Generate validation rules safely
            try {
                $this->generateValidationRules();
            } catch (\Exception $e) {
                Log::warning("Failed to generate validation rules: " . $e->getMessage());
                $this->validationRules = [];
            }

            // Extract info safely
            $info = [];
            try {
                $info = $this->extractInfo();
            } catch (\Exception $e) {
                Log::warning("Failed to extract info: " . $e->getMessage());
            }

            // Extract servers safely
            $servers = [];
            try {
                $servers = $this->extractServers();
            } catch (\Exception $e) {
                Log::warning("Failed to extract servers: " . $e->getMessage());
            }

            // Extract security safely
            $security = [];
            try {
                $security = $this->extractSecurity();
            } catch (\Exception $e) {
                Log::warning("Failed to extract security: " . $e->getMessage());
            }

            $result = [
                'info' => $info,
                'endpoints' => $this->endpoints,
                'schemas' => $this->schemas,
                'model_mappings' => $this->modelMappings,
                'validation_rules' => $this->validationRules,
                'servers' => $servers,
                'security' => $security,
                'parsed_at' => now()->toISOString(),
                'source' => $source,
            ];

            if ($useCache && $this->config['cache_enabled']) {
                Cache::put($cacheKey, $result, $this->cacheTtl);
                Log::info("OpenAPI schema cached", ['source' => $source]);
            }

            return $result;

        } catch (\Exception $e) {
            Log::error("Failed to parse OpenAPI schema", [
                'source' => $source,
                'error' => $e->getMessage()
            ]);
            
            throw new OpenApiParsingException(
                "Failed to parse OpenAPI schema from '{$source}': " . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Load OpenAPI schema from file or URL
     */
    protected function loadSchema(string $source): OpenApi
    {
        if ($this->isUrl($source)) {
            return $this->loadFromUrl($source);
        }
        
        return $this->loadFromFile($source);
    }

    /**
     * Load schema from URL
     */
    protected function loadFromUrl(string $url): OpenApi
    {
        try {
            Log::info("Loading OpenAPI schema from URL", ['url' => $url]);
            
            $response = Http::timeout($this->config['remote_timeout'])->get($url);

            if (!$response->successful()) {
                throw new OpenApiParsingException(
                    "Failed to fetch OpenAPI schema from URL: HTTP {$response->status()}"
                );
            }

            $content = $response->body();
            $contentLength = strlen($content);
            
            if ($contentLength > $this->config['max_file_size']) {
                throw new OpenApiParsingException(
                    "OpenAPI schema file too large: {$contentLength} bytes"
                );
            }

            $contentType = $response->header('content-type', '');
            
            if (str_contains($contentType, 'json') || $this->isJsonContent($content)) {
                return Reader::readFromJson($content);
            } else {
                return Reader::readFromYaml($content);
            }

        } catch (\Exception $e) {
            throw new OpenApiParsingException(
                "Failed to load OpenAPI schema from URL '{$url}': " . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Load schema from local file
     */
    protected function loadFromFile(string $filePath): OpenApi
    {
        try {
            Log::info("Loading OpenAPI schema from file", ['path' => $filePath]);
            
            if (!file_exists($filePath)) {
                throw new OpenApiParsingException("OpenAPI schema file not found: {$filePath}");
            }

            if (!is_readable($filePath)) {
                throw new OpenApiParsingException("OpenAPI schema file not readable: {$filePath}");
            }

            $fileSize = filesize($filePath);
            if ($fileSize > $this->config['max_file_size']) {
                throw new OpenApiParsingException(
                    "OpenAPI schema file too large: {$fileSize} bytes"
                );
            }

            $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
            
            if (in_array($extension, ['json'])) {
                return Reader::readFromJsonFile($filePath);
            } elseif (in_array($extension, ['yaml', 'yml'])) {
                return Reader::readFromYamlFile($filePath);
            } else {
                $content = file_get_contents($filePath);
                if ($this->isJsonContent($content)) {
                    return Reader::readFromJson($content);
                } else {
                    return Reader::readFromYaml($content);
                }
            }

        } catch (\Exception $e) {
            throw new OpenApiParsingException(
                "Failed to load OpenAPI schema from file '{$filePath}': " . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Validate OpenAPI version
     */
    protected function validateOpenApiVersion(): void
    {
        $version = $this->openApiSpec->openapi ?? '';
        
        if (!in_array($version, $this->config['supported_versions'])) {
            throw new SchemaValidationException(
                "Unsupported OpenAPI version: {$version}. Supported: " . 
                implode(', ', $this->config['supported_versions'])
            );
        }

        Log::info("OpenAPI version validated", ['version' => $version]);
    }

    // Getters
    public function getEndpoints(): array { return $this->endpoints; }
    public function getSchemas(): array { return $this->schemas; }
    public function getModelMappings(): array { return $this->modelMappings; }
    public function getValidationRules(): array { return $this->validationRules; }

    // Helper methods
    protected function getCacheKey(string $source): string
    {
        return $this->cachePrefix . md5($source);
    }

    protected function isUrl(string $source): bool
    {
        return filter_var($source, FILTER_VALIDATE_URL) !== false;
    }

    protected function isJsonContent(string $content): bool
    {
        json_decode($content);
        return json_last_error() === JSON_ERROR_NONE;
    }

    // Additional methods will be in trait files
    use ExtractsEndpoints, ExtractsSchemas, GeneratesValidationRules, GeneratesModelMappings;
}
