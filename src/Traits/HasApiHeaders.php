<?php

namespace MTechStack\LaravelApiModelClient\Traits;

use Closure;

/**
 * Trait HasApiHeaders
 *
 * Provides comprehensive header injection capabilities for API requests.
 * Supports global, model-level, and dynamic header injection.
 */
trait HasApiHeaders
{
    /**
     * Model-specific headers that will be included in all API requests for this model.
     *
     * @var array
     */
    protected $apiHeaders = [];

    /**
     * Dynamic header callbacks that will be executed at request time.
     *
     * @var array
     */
    protected $dynamicApiHeaders = [];

    /**
     * Initialize API headers properties.
     *
     * @return void
     */
    protected function initializeApiHeaders(): void
    {
        if (!is_array($this->apiHeaders)) {
            $this->apiHeaders = [];
        }
        if (!is_array($this->dynamicApiHeaders)) {
            $this->dynamicApiHeaders = [];
        }
    }

    /**
     * Static global headers that apply to all API requests.
     *
     * @var array
     */
    protected static $globalApiHeaders = [];

    /**
     * Global dynamic header callbacks.
     *
     * @var array
     */
    protected static $globalDynamicApiHeaders = [];

    /**
     * Set a header for this model instance.
     *
     * @param string $name
     * @param string $value
     * @return $this
     */
    public function setApiHeader(string $name, string $value): self
    {
        $this->initializeApiHeaders();
        $this->apiHeaders[$name] = $value;
        return $this;
    }

    /**
     * Set multiple headers for this model instance.
     *
     * @param array $headers
     * @return $this
     */
    public function setApiHeaders(array $headers): self
    {
        $this->initializeApiHeaders();
        $this->apiHeaders = array_merge($this->apiHeaders, $headers);
        return $this;
    }

    /**
     * Set a dynamic header callback for this model instance.
     *
     * @param string $name
     * @param Closure $callback
     * @return $this
     */
    public function setDynamicApiHeader(string $name, Closure $callback): self
    {
        $this->initializeApiHeaders();
        $this->dynamicApiHeaders[$name] = $callback;
        return $this;
    }

    /**
     * Set multiple dynamic header callbacks for this model instance.
     *
     * @param array $callbacks
     * @return $this
     */
    public function setDynamicApiHeaders(array $callbacks): self
    {
        $this->initializeApiHeaders();
        $this->dynamicApiHeaders = array_merge($this->dynamicApiHeaders, $callbacks);
        return $this;
    }

    /**
     * Set a global header that applies to all API requests.
     *
     * @param string $name
     * @param string $value
     * @return void
     */
    public static function setGlobalApiHeader(string $name, string $value): void
    {
        static::$globalApiHeaders[$name] = $value;
    }

    /**
     * Set multiple global headers.
     *
     * @param array $headers
     * @return void
     */
    public static function setGlobalApiHeaders(array $headers): void
    {
        static::$globalApiHeaders = array_merge(static::$globalApiHeaders, $headers);
    }

    /**
     * Set a global dynamic header callback.
     *
     * @param string $name
     * @param Closure $callback
     * @return void
     */
    public static function setGlobalDynamicApiHeader(string $name, Closure $callback): void
    {
        static::$globalDynamicApiHeaders[$name] = $callback;
    }

    /**
     * Set multiple global dynamic header callbacks.
     *
     * @param array $callbacks
     * @return void
     */
    public static function setGlobalDynamicApiHeaders(array $callbacks): void
    {
        static::$globalDynamicApiHeaders = array_merge(static::$globalDynamicApiHeaders, $callbacks);
    }

    /**
     * Get a specific header value.
     *
     * @param string $name
     * @return string|null
     */
    public function getApiHeader(string $name): ?string
    {
        return $this->apiHeaders[$name] ?? null;
    }

    /**
     * Get all headers for this model instance.
     *
     * @return array
     */
    public function getApiHeaders(): array
    {
        return $this->apiHeaders;
    }

    /**
     * Remove a header from this model instance.
     *
     * @param string $name
     * @return $this
     */
    public function removeApiHeader(string $name): self
    {
        unset($this->apiHeaders[$name]);
        unset($this->dynamicApiHeaders[$name]);
        return $this;
    }

    /**
     * Clear all headers for this model instance.
     *
     * @return $this
     */
    public function clearApiHeaders(): self
    {
        $this->apiHeaders = [];
        $this->dynamicApiHeaders = [];
        return $this;
    }

    /**
     * Remove a global header.
     *
     * @param string $name
     * @return void
     */
    public static function removeGlobalApiHeader(string $name): void
    {
        unset(static::$globalApiHeaders[$name]);
        unset(static::$globalDynamicApiHeaders[$name]);
    }

    /**
     * Clear all global headers.
     *
     * @return void
     */
    public static function clearGlobalApiHeaders(): void
    {
        static::$globalApiHeaders = [];
        static::$globalDynamicApiHeaders = [];
    }

    /**
     * Get all resolved headers for API requests.
     * This combines global headers, model headers, config headers, and dynamic headers.
     *
     * @param array $requestContext Additional context for dynamic headers
     * @return array
     */
    public function getResolvedApiHeaders(array $requestContext = []): array
    {
        // Initialize properties to avoid undefined variable errors
        $this->initializeApiHeaders();

        $resolvedHeaders = [];

        try {
            // 1. Start with config-based static headers
            $configHeaders = $this->getConfigApiHeaders();
            if (is_array($configHeaders)) {
                $resolvedHeaders = array_merge($resolvedHeaders, $configHeaders);
            }

            // 2. Add global static headers
            if (is_array(static::$globalApiHeaders)) {
                $resolvedHeaders = array_merge($resolvedHeaders, static::$globalApiHeaders);
            }

            // 3. Add model-specific static headers
            if (is_array($this->apiHeaders)) {
                $resolvedHeaders = array_merge($resolvedHeaders, $this->apiHeaders);
            }

            // 4. Add config-based dynamic headers
            $configDynamicHeaders = $this->getConfigDynamicHeaders();
            if (is_array($configDynamicHeaders)) {
                foreach ($configDynamicHeaders as $name => $callback) {
                    try {
                        if (is_callable($callback)) {
                            $value = $callback($this, $requestContext);
                            if ($value !== null && $value !== '') {
                                $resolvedHeaders[$name] = (string) $value;
                            }
                        }
                    } catch (\Exception $e) {
                        // Log error but don't break the request
                        if (function_exists('logger')) {
                            logger()->warning('Failed to resolve config dynamic API header', [
                                'header' => $name,
                                'error' => $e->getMessage(),
                                'model' => get_class($this)
                            ]);
                        }
                    }
                }
            }

            // 5. Add global dynamic headers
            if (is_array(static::$globalDynamicApiHeaders)) {
                foreach (static::$globalDynamicApiHeaders as $name => $callback) {
                    try {
                        if (is_callable($callback)) {
                            $value = $callback($this, $requestContext);
                            if ($value !== null && $value !== '') {
                                $resolvedHeaders[$name] = (string) $value;
                            }
                        }
                    } catch (\Exception $e) {
                        // Log error but don't break the request
                        if (function_exists('logger')) {
                            logger()->warning('Failed to resolve global dynamic API header', [
                                'header' => $name,
                                'error' => $e->getMessage(),
                                'model' => get_class($this)
                            ]);
                        }
                    }
                }
            }

            // 6. Add model-specific dynamic headers
            if (is_array($this->dynamicApiHeaders)) {
                foreach ($this->dynamicApiHeaders as $name => $callback) {
                    try {
                        if (is_callable($callback)) {
                            $value = $callback($this, $requestContext);
                            if ($value !== null && $value !== '') {
                                $resolvedHeaders[$name] = (string) $value;
                            }
                        }
                    } catch (\Exception $e) {
                        // Log error but don't break the request
                        if (function_exists('logger')) {
                            logger()->warning('Failed to resolve dynamic API header', [
                                'header' => $name,
                                'error' => $e->getMessage(),
                                'model' => get_class($this)
                            ]);
                        }
                    }
                }
            }

        } catch (\Exception $e) {
            // If anything goes wrong, log it and return empty headers
            if (function_exists('logger')) {
                logger()->error('Failed to resolve API headers', [
                    'error' => $e->getMessage(),
                    'model' => get_class($this),
                    'trace' => $e->getTraceAsString()
                ]);
            }
            return [];
        }

        // 7. Filter out empty headers and return
        return array_filter($resolvedHeaders, function ($value) {
            return $value !== null && $value !== '';
        });
    }

    /**
     * Get headers from configuration.
     *
     * @return array
     */
    protected function getConfigApiHeaders(): array
    {
        $headers = [];

        if (!function_exists('config')) {
            return $headers;
        }

        // 1. Get global headers from api-headers config
        $globalConfig = config('api-headers.global.headers', []);
        if (is_array($globalConfig)) {
            $headers = array_merge($headers, $globalConfig);
        }

        // 2. Get environment-specific headers
        $environment = app()->environment();
        $envHeaders = config("api-headers.environment.{$environment}", []);
        if (is_array($envHeaders)) {
            $headers = array_merge($headers, $envHeaders);
        }

        // 3. Get authentication headers from config
        $authConfig = config('api-headers.auth', []);

        // Bearer token
        if (!empty($authConfig['bearer']['enabled']) && !empty($authConfig['bearer']['token'])) {
            $prefix = $authConfig['bearer']['prefix'] ?? 'Bearer';
            $headerName = $authConfig['bearer']['header'] ?? 'Authorization';
            $headers[$headerName] = "{$prefix} {$authConfig['bearer']['token']}";
        }

        // API Key
        if (!empty($authConfig['api_key']['enabled']) && !empty($authConfig['api_key']['key'])) {
            $headerName = $authConfig['api_key']['header'] ?? 'X-API-Key';
            $headers[$headerName] = $authConfig['api_key']['key'];
        }

        // Basic Auth
        if (!empty($authConfig['basic']['enabled']) &&
            !empty($authConfig['basic']['username']) &&
            !empty($authConfig['basic']['password'])) {
            $credentials = base64_encode($authConfig['basic']['username'] . ':' . $authConfig['basic']['password']);
            $headers['Authorization'] = "Basic {$credentials}";
        }

        // 4. Get rate limiting headers
        $rateLimitConfig = config('api-headers.rate_limiting', []);
        if (!empty($rateLimitConfig['client_id'])) {
            $headers['X-Client-ID'] = $rateLimitConfig['client_id'];
        }
        if (!empty($rateLimitConfig['client_name'])) {
            $headers['X-Client-Name'] = $rateLimitConfig['client_name'];
        }

        // 5. Get custom headers
        $customHeaders = config('api-headers.custom', []);
        if (is_array($customHeaders)) {
            $headers = array_merge($headers, $customHeaders);
        }

        // 6. Get model-specific headers
        $modelClass = get_class($this);
        $modelKey = str_replace('\\', '.', strtolower($modelClass));
        $modelHeaders = config("api-headers.models.{$modelKey}", []);
        if (is_array($modelHeaders)) {
            $headers = array_merge($headers, $modelHeaders);
        }

        // 7. Fallback to legacy api-client config for backward compatibility
        $legacyHeaders = config('api-client.headers', []);
        if (is_array($legacyHeaders)) {
            $headers = array_merge($headers, $legacyHeaders);
        }

        $legacyModelHeaders = config("api-client.model_headers.{$modelKey}", []);
        if (is_array($legacyModelHeaders)) {
            $headers = array_merge($headers, $legacyModelHeaders);
        }

        return $headers;
    }

    /**
     * Get dynamic headers from configuration.
     *
     * @return array
     */
    protected function getConfigDynamicHeaders(): array
    {
        $dynamicHeaders = [];

        if (!function_exists('config')) {
            return $dynamicHeaders;
        }

        // Get global dynamic headers from config
        $globalDynamic = config('api-headers.global.dynamic', []);
        if (is_array($globalDynamic)) {
            foreach ($globalDynamic as $name => $callback) {
                if (is_callable($callback)) {
                    $dynamicHeaders[$name] = $callback;
                }
            }
        }

        // Get model-specific dynamic headers from config
        $modelClass = get_class($this);
        $modelKey = str_replace('\\', '.', strtolower($modelClass));
        $modelDynamic = config("api-headers.models.{$modelKey}.dynamic", []);
        if (is_array($modelDynamic)) {
            foreach ($modelDynamic as $name => $callback) {
                if (is_callable($callback)) {
                    $dynamicHeaders[$name] = $callback;
                }
            }
        }

        return $dynamicHeaders;
    }

    /**
     * Helper method to set common authentication headers.
     *
     * @param string $token
     * @param string $type
     * @return $this
     */
    public function setAuthHeader(string $token, string $type = 'Bearer'): self
    {
        return $this->setApiHeader('Authorization', "{$type} {$token}");
    }

    /**
     * Helper method to set API key header.
     *
     * @param string $apiKey
     * @param string $headerName
     * @return $this
     */
    public function setApiKeyHeader(string $apiKey, string $headerName = 'X-API-Key'): self
    {
        return $this->setApiHeader($headerName, $apiKey);
    }

    /**
     * Helper method to set content type header.
     *
     * @param string $contentType
     * @return $this
     */
    public function setContentTypeHeader(string $contentType = 'application/json'): self
    {
        return $this->setApiHeader('Content-Type', $contentType);
    }

    /**
     * Helper method to set accept header.
     *
     * @param string $accept
     * @return $this
     */
    public function setAcceptHeader(string $accept = 'application/json'): self
    {
        return $this->setApiHeader('Accept', $accept);
    }

    /**
     * Helper method to set user agent header.
     *
     * @param string $userAgent
     * @return $this
     */
    public function setUserAgentHeader(string $userAgent): self
    {
        return $this->setApiHeader('User-Agent', $userAgent);
    }

    /**
     * Set global authentication header.
     *
     * @param string $token
     * @param string $type
     * @return void
     */
    public static function setGlobalAuthHeader(string $token, string $type = 'Bearer'): void
    {
        static::setGlobalApiHeader('Authorization', "{$type} {$token}");
    }

    /**
     * Set global API key header.
     *
     * @param string $apiKey
     * @param string $headerName
     * @return void
     */
    public static function setGlobalApiKeyHeader(string $apiKey, string $headerName = 'X-API-Key'): void
    {
        static::setGlobalApiHeader($headerName, $apiKey);
    }

    /**
     * Set dynamic authentication header that resolves at request time.
     *
     * @param Closure $tokenCallback
     * @param string $type
     * @return $this
     */
    public function setDynamicAuthHeader(Closure $tokenCallback, string $type = 'Bearer'): self
    {
        return $this->setDynamicApiHeader('Authorization', function ($model, $context) use ($tokenCallback, $type) {
            $token = $tokenCallback($model, $context);
            return $token ? "{$type} {$token}" : null;
        });
    }

    /**
     * Set global dynamic authentication header.
     *
     * @param Closure $tokenCallback
     * @param string $type
     * @return void
     */
    public static function setGlobalDynamicAuthHeader(Closure $tokenCallback, string $type = 'Bearer'): void
    {
        static::setGlobalDynamicApiHeader('Authorization', function ($model, $context) use ($tokenCallback, $type) {
            $token = $tokenCallback($model, $context);
            return $token ? "{$type} {$token}" : null;
        });
    }

    /**
     * Debug method to check if dynamic headers are being called.
     * This will resolve headers with debug output.
     *
     * @param array $requestContext
     * @param bool $verbose
     * @return array
     */
    public function debugResolvedApiHeaders(array $requestContext = [], bool $verbose = false): array
    {
        if ($verbose) {
            echo "🔍 Starting header resolution debug for " . get_class($this) . "\n";
            echo "📋 Request context: " . json_encode($requestContext, JSON_PRETTY_PRINT) . "\n";
        }

        $headers = [];

        // 1. Config headers
        $configHeaders = $this->getConfigApiHeaders();
        if ($verbose && !empty($configHeaders)) {
            echo "⚙️  Config headers: " . json_encode($configHeaders, JSON_PRETTY_PRINT) . "\n";
        }
        $headers = array_merge($headers, $configHeaders);

        // 2. Global static headers
        if ($verbose && !empty(static::$globalApiHeaders)) {
            echo "🌍 Global static headers: " . json_encode(static::$globalApiHeaders, JSON_PRETTY_PRINT) . "\n";
        }
        $headers = array_merge($headers, static::$globalApiHeaders);

        // 3. Model static headers
        if ($verbose && !empty($this->apiHeaders)) {
            echo "📦 Model static headers: " . json_encode($this->apiHeaders, JSON_PRETTY_PRINT) . "\n";
        }
        $headers = array_merge($headers, $this->apiHeaders);

        // 4. Global dynamic headers
        if ($verbose) {
            echo "🔄 Processing " . count(static::$globalDynamicApiHeaders) . " global dynamic headers...\n";
        }

        foreach (static::$globalDynamicApiHeaders as $name => $callback) {
            if ($verbose) {
                echo "   🚀 Calling global dynamic header: {$name}\n";
            }

            try {
                $value = $callback($this, $requestContext);
                if ($value !== null) {
                    $headers[$name] = (string) $value;
                    if ($verbose) {
                        echo "   ✅ {$name}: {$value}\n";
                    }
                } else {
                    if ($verbose) {
                        echo "   ⚠️  {$name}: returned null\n";
                    }
                }
            } catch (\Exception $e) {
                if ($verbose) {
                    echo "   ❌ {$name}: ERROR - {$e->getMessage()}\n";
                }
                if (function_exists('logger')) {
                    logger()->warning('Failed to resolve global dynamic API header', [
                        'header' => $name,
                        'error' => $e->getMessage(),
                        'model' => get_class($this)
                    ]);
                }
            }
        }

        // 5. Model dynamic headers
        if ($verbose) {
            echo "🔄 Processing " . count($this->dynamicApiHeaders) . " model dynamic headers...\n";
        }

        foreach ($this->dynamicApiHeaders as $name => $callback) {
            if ($verbose) {
                echo "   🚀 Calling model dynamic header: {$name}\n";
            }

            try {
                $value = $callback($this, $requestContext);
                if ($value !== null) {
                    $headers[$name] = (string) $value;
                    if ($verbose) {
                        echo "   ✅ {$name}: {$value}\n";
                    }
                } else {
                    if ($verbose) {
                        echo "   ⚠️  {$name}: returned null\n";
                    }
                }
            } catch (\Exception $e) {
                if ($verbose) {
                    echo "   ❌ {$name}: ERROR - {$e->getMessage()}\n";
                }
                if (function_exists('logger')) {
                    logger()->warning('Failed to resolve dynamic API header', [
                        'header' => $name,
                        'error' => $e->getMessage(),
                        'model' => get_class($this)
                    ]);
                }
            }
        }

        // Filter out empty headers
        $finalHeaders = array_filter($headers, function ($value) {
            return $value !== null && $value !== '';
        });

        if ($verbose) {
            echo "🎯 Final resolved headers: " . json_encode($finalHeaders, JSON_PRETTY_PRINT) . "\n";
        }

        return $finalHeaders;
    }

    /**
     * Get count of registered dynamic headers.
     *
     * @return array
     */
    public function getDynamicHeadersInfo(): array
    {
        return [
            'global_dynamic_headers' => count(static::$globalDynamicApiHeaders),
            'model_dynamic_headers' => count($this->dynamicApiHeaders),
            'global_static_headers' => count(static::$globalApiHeaders),
            'model_static_headers' => count($this->apiHeaders),
            'global_dynamic_names' => array_keys(static::$globalDynamicApiHeaders),
            'model_dynamic_names' => array_keys($this->dynamicApiHeaders),
        ];
    }
}
