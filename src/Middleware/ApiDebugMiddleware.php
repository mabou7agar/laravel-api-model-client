<?php

namespace MTechStack\LaravelApiModelClient\Middleware;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;

class ApiDebugMiddleware extends AbstractApiMiddleware
{
    /**
     * Create a new API debug middleware instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->priority = 30; // Run after authentication but before most other middleware
    }

    /**
     * Process the API request.
     *
     * @param array $request The request data
     * @param callable $next The next middleware in the pipeline
     * @return array The processed response
     */
    public function handle(array $request, callable $next): array
    {
        // Skip if debugging is disabled
        if (!Config::get('api-model-relations.debug', false)) {
            return $next($request);
        }
        
        // Generate a unique ID for this request
        $requestId = (string) Str::uuid();
        
        // Record start time
        $startTime = microtime(true);
        
        // Store request data
        $debugData = [
            'id' => $requestId,
            'timestamp' => now()->toIso8601String(),
            'method' => $request['method'] ?? 'GET',
            'endpoint' => $request['endpoint'] ?? '',
            'options' => $this->sanitizeOptions($request['options'] ?? []),
            'start_time' => $startTime,
        ];
        
        try {
            // Process the request
            $response = $next($request);
            
            // Calculate duration
            $duration = microtime(true) - $startTime;
            
            // Update debug data with response
            $debugData = array_merge($debugData, [
                'status_code' => $response['status_code'] ?? 200,
                'response' => $this->truncateResponse($response['data'] ?? []),
                'duration' => $duration,
                'success' => true,
            ]);
            
            return $response;
        } catch (\Exception $e) {
            // Calculate duration
            $duration = microtime(true) - $startTime;
            
            // Update debug data with exception
            $debugData = array_merge($debugData, [
                'status_code' => $e->getCode() ?: 500,
                'error' => $e->getMessage(),
                'duration' => $duration,
                'success' => false,
            ]);
            
            throw $e;
        } finally {
            // Store debug data in cache
            $this->storeDebugData($debugData);
        }
    }

    /**
     * Sanitize request options to remove sensitive data.
     *
     * @param array $options
     * @return array
     */
    protected function sanitizeOptions(array $options): array
    {
        $sanitized = $options;
        
        // Sanitize headers
        if (isset($sanitized['headers'])) {
            foreach ($sanitized['headers'] as $key => $value) {
                if ($this->isSensitiveHeader($key)) {
                    $sanitized['headers'][$key] = '********';
                }
            }
        }
        
        // Sanitize auth
        if (isset($sanitized['auth'])) {
            $sanitized['auth'] = ['********', '********'];
        }
        
        return $sanitized;
    }

    /**
     * Determine if a header is sensitive.
     *
     * @param string $header
     * @return bool
     */
    protected function isSensitiveHeader(string $header): bool
    {
        $sensitiveHeaders = [
            'authorization',
            'x-api-key',
            'api-key',
            'token',
            'password',
            'secret',
        ];
        
        return in_array(strtolower($header), $sensitiveHeaders);
    }

    /**
     * Truncate response data to avoid storing too much in cache.
     *
     * @param array $response
     * @return array
     */
    protected function truncateResponse(array $response): array
    {
        $maxSize = 10000; // Characters
        $json = json_encode($response);
        
        if (strlen($json) <= $maxSize) {
            return $response;
        }
        
        return [
            '_truncated' => true,
            '_original_size' => strlen($json),
            'summary' => 'Response was truncated due to size. Original size: ' . strlen($json) . ' characters.',
        ];
    }

    /**
     * Store debug data in cache.
     *
     * @param array $debugData
     * @return void
     */
    protected function storeDebugData(array $debugData): void
    {
        $cacheKey = 'api_model_relations_debug_requests';
        $maxRequests = 100; // Maximum number of requests to store
        
        // Get existing debug data
        $requests = Cache::get($cacheKey, []);
        
        // Add new request to the beginning
        array_unshift($requests, $debugData);
        
        // Limit the number of stored requests
        if (count($requests) > $maxRequests) {
            $requests = array_slice($requests, 0, $maxRequests);
        }
        
        // Store in cache for 24 hours
        Cache::put($cacheKey, $requests, now()->addHours(24));
    }
}
