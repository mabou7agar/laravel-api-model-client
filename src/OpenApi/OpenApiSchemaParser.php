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
    protected ?string $lastSource = null;
    protected ?array $lastRawDoc = null;
    protected bool $enableLogging = false; // disable logging to reduce benchmark noise
    
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
            $this->lastSource = $source;
            $cacheKey = $this->getCacheKey($source);
            
            if ($useCache && $this->config['cache_enabled']) {
                $cached = Cache::get($cacheKey);
                if ($cached !== null) {
                    $this->logInfo("OpenAPI schema loaded from cache", ['source' => $source]);
                    return $cached;
                }
            }

            $this->openApiSpec = $this->loadSchema($source);
            // Keep raw doc for result fields (openapi/paths) and fallbacks
            $this->lastRawDoc = $this->loadRawDocument($source);
            $this->validateOpenApiVersion();
            $this->validateSchemaStructure();
            
            // Extract endpoints safely
            try {
                $this->extractEndpoints();
            } catch (\Exception $e) {
                $this->logWarning("Failed to extract endpoints: " . $e->getMessage());
                $this->endpoints = [];
            }

            // Extract schemas safely
            try {
                $this->extractSchemas();
            } catch (\Exception $e) {
                $this->logWarning("Failed to extract schemas: " . $e->getMessage());
                $this->schemas = [];
            }

            // Fallback extraction from raw document when cebe parsing yields no endpoints
            if (empty($this->endpoints)) {
                try {
                    $raw = $this->loadRawDocument($source);
                    if (!empty($raw)) {
                        $this->fallbackExtractFromRaw($raw);
                    }
                } catch (\Exception $e) {
                    $this->logWarning("Raw fallback extraction failed: " . $e->getMessage());
                }
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

            $raw = is_array($this->lastRawDoc) ? $this->lastRawDoc : [];
            $result = [
                'openapi' => $raw['openapi'] ?? ($this->openApiSpec->openapi ?? null),
                'info' => $info,
                'paths' => $raw['paths'] ?? [],
                'endpoints' => $this->endpoints,
                'schemas' => $this->schemas,
                'model_mappings' => $this->modelMappings,
                'validation_rules' => $this->validationRules,
                'servers' => $servers,
                'security' => $security,
                'components' => [
                    'schemas' => $this->schemas,
                ],
            ];

            if ($useCache && $this->config['cache_enabled']) {
                Cache::put($cacheKey, $result, $this->cacheTtl);
                $this->logInfo("OpenAPI schema cached", ['source' => $source]);
            }

            return $result;

        } catch (\Exception $e) {
            $this->logError("Failed to parse OpenAPI schema", [
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
     * Validate the parsed OpenAPI structure using cebe/php-openapi if available
     */
    protected function validateSchemaStructure(): void
    {
        // Perform lightweight structural validation on the raw document to avoid
        // over-strict library validation that may not fully support 3.1.x features
        $raw = [];
        if ($this->lastSource) {
            $raw = $this->loadRawDocument($this->lastSource);
        }

        if (!is_array($raw) || empty($raw)) {
            // Fallback minimal check against spec object
            if (!isset($this->openApiSpec->paths)) {
                throw new SchemaValidationException('OpenAPI schema missing paths section.');
            }
            return;
        }

        // Basic info section checks
        if (!isset($raw['info']) || !is_array($raw['info']) || empty($raw['info']['version'])) {
            throw new SchemaValidationException('OpenAPI schema missing required info.version.');
        }

        if (!isset($raw['paths']) || !is_array($raw['paths'])) {
            throw new SchemaValidationException('OpenAPI schema missing or invalid paths.');
        }

        // Ensure each operation has a responses section (required by spec)
        foreach ($raw['paths'] as $path => $ops) {
            if (!is_array($ops)) {
                continue;
            }
            foreach ($ops as $method => $op) {
                if (!is_array($op)) {
                    continue;
                }
                if (!isset($op['responses']) || !is_array($op['responses'])) {
                    throw new SchemaValidationException("Operation '{$method} {$path}' missing responses definition.");
                }
            }
        }
    }

    /**
     * Load raw OpenAPI document array from a file path or URL
     */
    protected function loadRawDocument(string $source): array
    {
        if ($this->isUrl($source)) {
            $response = Http::timeout($this->config['remote_timeout'])->get($source);
            if (!$response->successful()) {
                return [];
            }
            $content = $response->body();
        } else {
            if (!file_exists($source)) {
                return [];
            }
            $content = file_get_contents($source);
        }

        $data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            // Try YAML if JSON failed
            return [];
        }
        return is_array($data) ? $data : [];
    }

    /**
     * Fallback extraction of endpoints and schemas from a raw decoded document
     */
    protected function fallbackExtractFromRaw(array $doc): void
    {
        // Endpoints
        $paths = $doc['paths'] ?? [];
        $methods = ['get', 'post', 'put', 'patch', 'delete', 'head', 'options', 'trace'];
        foreach ($paths as $path => $ops) {
            if (!is_array($ops)) {
                continue;
            }
            foreach ($methods as $method) {
                if (!isset($ops[$method]) || !is_array($ops[$method])) {
                    continue;
                }
                $op = $ops[$method];
                $operationId = $op['operationId'] ?? $this->generateOperationId($method, $path);
                $endpoint = [
                    'operation_id' => $operationId,
                    'path' => $path,
                    'method' => strtoupper($method),
                    'summary' => $op['summary'] ?? '',
                    'description' => $op['description'] ?? '',
                    'tags' => $op['tags'] ?? [],
                    'parameters' => [],
                    'request_body' => null,
                    'responses' => [],
                    'security' => $op['security'] ?? [],
                    'deprecated' => $op['deprecated'] ?? false,
                ];

                // Parameters
                foreach (($op['parameters'] ?? []) as $param) {
                    $endpoint['parameters'][] = [
                        'name' => $param['name'] ?? '',
                        'in' => $param['in'] ?? 'query',
                        'description' => $param['description'] ?? '',
                        'required' => $param['required'] ?? false,
                        'deprecated' => $param['deprecated'] ?? false,
                        'schema' => $param['schema'] ?? null,
                        'style' => $param['style'] ?? null,
                        'explode' => $param['explode'] ?? null,
                        'example' => $param['example'] ?? null,
                    ];
                }

                // Request body
                if (isset($op['requestBody']['content'])) {
                    $content = [];
                    foreach ($op['requestBody']['content'] as $mediaType => $media) {
                        $content[$mediaType] = [
                            'schema' => $media['schema'] ?? null,
                            'example' => $media['example'] ?? null,
                            'examples' => $media['examples'] ?? [],
                        ];
                    }
                    $endpoint['request_body'] = [
                        'description' => $op['requestBody']['description'] ?? '',
                        'required' => $op['requestBody']['required'] ?? false,
                        'content' => $content,
                    ];
                }

                // Responses
                foreach (($op['responses'] ?? []) as $status => $response) {
                    $respContent = [];
                    foreach (($response['content'] ?? []) as $mediaType => $media) {
                        $respContent[$mediaType] = [
                            'schema' => $media['schema'] ?? null,
                            'example' => $media['example'] ?? null,
                        ];
                    }
                    $endpoint['responses'][$status] = [
                        'description' => $response['description'] ?? '',
                        'content' => $respContent,
                        'headers' => $response['headers'] ?? [],
                    ];
                }

                $this->endpoints[$operationId] = $endpoint;
            }
        }

        // Schemas
        $this->schemas = [];
        foreach (($doc['components']['schemas'] ?? []) as $name => $schema) {
            // Keep as raw array for rule generation
            $this->schemas[$name] = is_array($schema) ? $schema : [];
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
            $this->logInfo("Loading OpenAPI schema from URL", ['url' => $url]);
            
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
            $this->logInfo("Loading OpenAPI schema from file", ['path' => $filePath]);
            
            if (!file_exists($filePath)) {
                throw new OpenApiParsingException("OpenAPI schema file not found");
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

        $this->logInfo("OpenAPI version validated", ['version' => $version]);
    }

    protected function logInfo(string $message, array $context = []): void
    {
        if ($this->enableLogging) { \Illuminate\Support\Facades\Log::info($message, $context); }
    }
    protected function logWarning(string $message, array $context = []): void
    {
        if ($this->enableLogging) { \Illuminate\Support\Facades\Log::warning($message, $context); }
    }
    protected function logError(string $message, array $context = []): void
    {
        if ($this->enableLogging) { \Illuminate\Support\Facades\Log::error($message, $context); }
    }

    // Getters
    public function getEndpoints(): array { return $this->endpoints; }
    public function getSchemas(): array { return $this->schemas; }
    public function getModelMappings(): array { return $this->modelMappings; }
    public function getValidationRules(): array 
    { 
        // Prefer rules for the primary model if available
        $schemaRules = $this->validationRules['schemas'] ?? [];
        if (!empty($schemaRules)) {
            $primary = $this->getPrimarySchemaName();
            if ($primary && isset($schemaRules[$primary])) {
                return $schemaRules[$primary];
            }
            // Fallback to first available
            $first = array_key_first($schemaRules);
            if ($first !== null) {
                return $schemaRules[$first];
            }
        }

        // As a last resort, try merging endpoint parameter rules
        if (!empty($this->validationRules['endpoints'])) {
            $merged = [];
            foreach ($this->validationRules['endpoints'] as $endpointRules) {
                $merged = array_merge($merged, $endpointRules['parameters'] ?? []);
            }
            if (!empty($merged)) {
                return $merged;
            }
        }

        // Fallback: derive directly from primary schema if available
        $primary = $this->getPrimarySchemaName();
        if ($primary && isset($this->schemas[$primary])) {
            $rules = $this->generateSchemaValidationRules($this->schemas[$primary]);
            if (!empty($rules)) {
                return $rules;
            }
        }

        // Fallback to raw structure if nothing else applies
        return $this->validationRules; 
    }

    protected function getPrimarySchemaName(): ?string
    {
        // Use first model mapping if present
        if (!empty($this->modelMappings)) {
            $firstModel = array_key_first($this->modelMappings);
            return $firstModel;
        }
        // Otherwise choose the first schema key
        if (!empty($this->schemas)) {
            return array_key_first($this->schemas);
        }
        return null;
    }

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
    use ExtractsEndpoints { extractEndpoints as protected extractEndpointsInternal; }
    use ExtractsSchemas, GeneratesValidationRules, GeneratesModelMappings;

    /**
     * Public endpoint extraction utility.
     * - Without argument: runs the internal extractor and returns current endpoints.
     * - With a raw OpenAPI document array: extracts endpoints from that document and returns them (no state change).
     */
    public function extractEndpoints(array $doc = null): array
    {
        if ($doc === null) {
            $this->extractEndpointsInternal();
            return $this->endpoints;
        }

        return $this->extractEndpointsFromRaw($doc);
    }

    /**
     * Extract endpoints from a raw document without mutating internal state.
     */
    protected function extractEndpointsFromRaw(array $doc): array
    {
        $out = [];
        $paths = $doc['paths'] ?? [];
        $methods = ['get', 'post', 'put', 'patch', 'delete', 'head', 'options', 'trace'];
        foreach ($paths as $path => $ops) {
            if (!is_array($ops)) {
                continue;
            }
            foreach ($methods as $method) {
                if (!isset($ops[$method]) || !is_array($ops[$method])) {
                    continue;
                }
                $op = $ops[$method];
                $operationId = $op['operationId'] ?? $this->generateOperationId($method, $path);
                $endpoint = [
                    'operation_id' => $operationId,
                    'path' => $path,
                    'method' => strtoupper($method),
                    'summary' => $op['summary'] ?? '',
                    'description' => $op['description'] ?? '',
                    'tags' => $op['tags'] ?? [],
                    'parameters' => $op['parameters'] ?? [],
                    'request_body' => $op['requestBody'] ?? null,
                    'responses' => $op['responses'] ?? [],
                    'security' => $op['security'] ?? [],
                    'deprecated' => $op['deprecated'] ?? false,
                ];
                $out[$operationId] = $endpoint;
            }
        }
        return $out;
    }
}
