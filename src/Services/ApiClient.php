<?php

namespace MTechStack\LaravelApiModelClient\Services;

use MTechStack\LaravelApiModelClient\Contracts\ApiClientInterface;
use MTechStack\LaravelApiModelClient\Contracts\AuthStrategyInterface;
use MTechStack\LaravelApiModelClient\Exceptions\ApiException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;

class ApiClient implements ApiClientInterface
{
    /**
     * The HTTP client instance.
     *
     * @var \GuzzleHttp\Client
     */
    protected $httpClient;

    /**
     * The base URL for API requests.
     *
     * @var string|null
     */
    protected $baseUrl;

    /**
     * The authentication strategy.
     *
     * @var \ApiModelRelations\Contracts\AuthStrategyInterface|null
     */
    protected $authStrategy;

    /**
     * The configuration array.
     *
     * @var array
     */
    protected $config;

    /**
     * Create a new API client instance.
     *
     * @param array $config
     * @return void
     */
    public function __construct(array $config = [])
    {
        $this->config = $config;
        $this->httpClient = new Client([
            'timeout' => $config['client']['timeout'] ?? 30,
            'connect_timeout' => $config['client']['connect_timeout'] ?? 10,
        ]);
    }

    /**
     * Send a GET request to the API.
     *
     * @param string $endpoint
     * @param array $queryParams
     * @param array $headers
     * @return mixed
     */
    public function get(string $endpoint, array $queryParams = [], array $headers = [])
    {
        return $this->request('GET', $endpoint, [
            'query' => $queryParams,
            'headers' => $this->prepareHeaders($headers),
        ]);
    }

    /**
     * Send a POST request to the API.
     *
     * @param string $endpoint
     * @param array $data
     * @param array $headers
     * @return mixed
     */
    public function post(string $endpoint, array $data = [], array $headers = [])
    {
        return $this->request('POST', $endpoint, [
            'json' => $data,
            'headers' => $this->prepareHeaders($headers),
        ]);
    }

    /**
     * Send a PUT request to the API.
     *
     * @param string $endpoint
     * @param array $data
     * @param array $headers
     * @return mixed
     */
    public function put(string $endpoint, array $data = [], array $headers = [])
    {
        return $this->request('PUT', $endpoint, [
            'json' => $data,
            'headers' => $this->prepareHeaders($headers),
        ]);
    }

    /**
     * Send a PATCH request to the API.
     *
     * @param string $endpoint
     * @param array $data
     * @param array $headers
     * @return mixed
     */
    public function patch(string $endpoint, array $data = [], array $headers = [])
    {
        return $this->request('PATCH', $endpoint, [
            'json' => $data,
            'headers' => $this->prepareHeaders($headers),
        ]);
    }

    /**
     * Send a DELETE request to the API.
     *
     * @param string $endpoint
     * @param array $queryParams
     * @param array $headers
     * @return mixed
     */
    public function delete(string $endpoint, array $queryParams = [], array $headers = [])
    {
        return $this->request('DELETE', $endpoint, [
            'query' => $queryParams,
            'headers' => $this->prepareHeaders($headers),
        ]);
    }

    /**
     * Set the authentication strategy.
     *
     * @param \ApiModelRelations\Contracts\AuthStrategyInterface $authStrategy
     * @return $this
     */
    public function setAuthStrategy(AuthStrategyInterface $authStrategy)
    {
        $this->authStrategy = $authStrategy;
        return $this;
    }

    /**
     * Set the base URL for API requests.
     *
     * @param string $baseUrl
     * @return $this
     */
    public function setBaseUrl(string $baseUrl)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        return $this;
    }

    /**
     * Send a request to the API.
     *
     * @param string $method
     * @param string $endpoint
     * @param array $options
     * @return mixed
     * @throws \ApiModelRelations\Exceptions\ApiException
     */
    protected function request(string $method, string $endpoint, array $options = [])
    {
        // Apply authentication if available
        if ($this->authStrategy !== null) {
            $options = $this->authStrategy->applyToRequest($options);
        }

        // Build the full URL
        $url = $this->buildUrl($endpoint);

        // Log the request if debugging is enabled
        $this->logRequest($method, $url, $options);

        try {
            // Send the request
            $response = $this->httpClient->request($method, $url, $options);

            // Parse the response
            $contents = $response->getBody()->getContents();
            $data = json_decode($contents, true);

            // Log the response if debugging is enabled
            $this->logResponse($data);

            return $data;
        } catch (RequestException $e) {
            return $this->handleRequestException($e, $method, $url);
        }
    }

    /**
     * Build the full URL for a request.
     *
     * @param string $endpoint
     * @return string
     */
    protected function buildUrl(string $endpoint)
    {
        if ($this->baseUrl === null) {
            return $endpoint;
        }

        return $this->baseUrl . '/' . ltrim($endpoint, '/');
    }

    /**
     * Prepare headers for a request.
     *
     * @param array $headers
     * @return array
     */
    protected function prepareHeaders(array $headers)
    {
        $defaultHeaders = $this->config['client']['headers'] ?? [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];

        return array_merge($defaultHeaders, $headers);
    }

    /**
     * Handle a request exception.
     *
     * @param \GuzzleHttp\Exception\RequestException $exception
     * @param string $method
     * @param string $url
     * @return mixed
     * @throws \ApiModelRelations\Exceptions\ApiException
     */
    protected function handleRequestException(RequestException $exception, string $method, string $url)
    {
        // Log the error
        if ($this->config['error_handling']['log_errors'] ?? true) {
            Log::error('API request failed', [
                'method' => $method,
                'url' => $url,
                'exception' => $exception->getMessage(),
                'status_code' => $exception->hasResponse() ? $exception->getResponse()->getStatusCode() : null,
            ]);
        }

        // Check if we should retry server errors
        $statusCode = $exception->hasResponse() ? $exception->getResponse()->getStatusCode() : null;
        $shouldRetry = ($this->config['error_handling']['retry_server_errors'] ?? true) && 
                       $statusCode >= 500 && 
                       $statusCode < 600;

        if ($shouldRetry) {
            // Implement retry logic here
            // For now, we'll just throw the exception
        }

        // Parse response if available
        $response = null;
        if ($exception->hasResponse()) {
            $contents = $exception->getResponse()->getBody()->getContents();
            $response = json_decode($contents, true);
        }

        // Throw exception if configured to do so
        if ($this->config['error_handling']['throw_exceptions'] ?? true) {
            throw new ApiException(
                'API request failed: ' . $exception->getMessage(),
                $statusCode ?? 0,
                $exception,
                $response
            );
        }

        // Return empty array if not throwing exceptions
        return [];
    }

    /**
     * Log an API request if debugging is enabled.
     *
     * @param string $method
     * @param string $url
     * @param array $options
     * @return void
     */
    protected function logRequest(string $method, string $url, array $options)
    {
        if (($this->config['debug']['enabled'] ?? false) && ($this->config['debug']['log_requests'] ?? true)) {
            Log::debug('API Request', [
                'method' => $method,
                'url' => $url,
                'options' => $this->sanitizeLogData($options),
            ]);
        }
    }

    /**
     * Log an API response if debugging is enabled.
     *
     * @param mixed $response
     * @return void
     */
    protected function logResponse($response)
    {
        if (($this->config['debug']['enabled'] ?? false) && ($this->config['debug']['log_responses'] ?? true)) {
            Log::debug('API Response', [
                'response' => $this->sanitizeLogData($response),
            ]);
        }
    }

    /**
     * Sanitize data for logging to remove sensitive information.
     *
     * @param mixed $data
     * @return mixed
     */
    protected function sanitizeLogData($data)
    {
        // If data is not an array or object, return as is
        if (!is_array($data) && !is_object($data)) {
            return $data;
        }

        // Convert to array if object
        $array = is_object($data) ? (array) $data : $data;
        $sanitized = [];

        // List of keys that might contain sensitive data
        $sensitiveKeys = ['password', 'secret', 'token', 'key', 'auth', 'credential', 'api_key'];

        foreach ($array as $key => $value) {
            // Check if this key might contain sensitive data
            $isSensitive = false;
            foreach ($sensitiveKeys as $sensitiveKey) {
                if (stripos($key, $sensitiveKey) !== false) {
                    $isSensitive = true;
                    break;
                }
            }

            // Mask sensitive data or recursively sanitize nested arrays/objects
            if ($isSensitive) {
                $sanitized[$key] = '********';
            } elseif (is_array($value) || is_object($value)) {
                $sanitized[$key] = $this->sanitizeLogData($value);
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }
}
