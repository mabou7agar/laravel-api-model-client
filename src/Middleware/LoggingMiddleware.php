<?php

namespace MTechStack\LaravelApiModelClient\Middleware;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

class LoggingMiddleware extends AbstractApiMiddleware
{
    /**
     * Create a new logging middleware instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->priority = 50; // Run in the middle of the pipeline
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
        // Skip logging if disabled in config
        if (!Config::get('api-model-relations.error_handling.log_requests', true)) {
            return $next($request);
        }
        
        // Log the request
        $this->logRequest($request);
        
        // Process the request
        $response = $next($request);
        
        // Log the response
        $this->logResponse($request, $response);
        
        return $response;
    }

    /**
     * Log the API request.
     *
     * @param array $request
     * @return void
     */
    protected function logRequest(array $request): void
    {
        $method = $request['method'] ?? 'GET';
        $endpoint = $request['endpoint'] ?? '';
        $options = $request['options'] ?? [];
        
        // Sanitize sensitive data from the request
        $sanitizedOptions = $this->sanitizeSensitiveData($options);
        
        Log::debug('API Request', [
            'method' => $method,
            'endpoint' => $endpoint,
            'options' => $sanitizedOptions,
        ]);
    }

    /**
     * Log the API response.
     *
     * @param array $request
     * @param array $response
     * @return void
     */
    protected function logResponse(array $request, array $response): void
    {
        $method = $request['method'] ?? 'GET';
        $endpoint = $request['endpoint'] ?? '';
        
        // Only log response if debug mode is enabled
        if (Config::get('api-model-relations.debug', false)) {
            Log::debug('API Response', [
                'method' => $method,
                'endpoint' => $endpoint,
                'response' => $response,
            ]);
        } else {
            // Just log that a response was received
            Log::debug('API Response Received', [
                'method' => $method,
                'endpoint' => $endpoint,
            ]);
        }
    }

    /**
     * Sanitize sensitive data from the request options.
     *
     * @param array $options
     * @return array
     */
    protected function sanitizeSensitiveData(array $options): array
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
}
