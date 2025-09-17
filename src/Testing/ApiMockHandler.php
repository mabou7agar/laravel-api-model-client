<?php

namespace MTechStack\LaravelApiModelClient\Testing;

use MTechStack\LaravelApiModelClient\Contracts\ApiClientInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;

class ApiMockHandler
{
    /**
     * The mocked responses.
     *
     * @var array
     */
    protected $responses = [];

    /**
     * The request history.
     *
     * @var array
     */
    protected $history = [];

    /**
     * The original API client instance.
     *
     * @var \MTechStack\LaravelApiModelClient\Contracts\ApiClientInterface|null
     */
    protected $originalClient;

    /**
     * Whether the handler is currently active.
     *
     * @var bool
     */
    protected $active = false;

    /**
     * Create a new API mock handler instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Activate the mock handler.
     *
     * @return $this
     */
    public function activate()
    {
        if (!$this->active) {
            // Store the original client
            $this->originalClient = App::make('api-client');
            
            // Replace with our mock client
            App::singleton('api-client', function () {
                $client = new MockApiClient($this);
                $base = config('api-model-client.client.base_url') ?? config('api-model-client.base_url');
                if ($base) {
                    $client->setBaseUrl($base);
                }
                return $client;
            });
            
            $this->active = true;
        }
        
        return $this;
    }

    /**
     * Deactivate the mock handler.
     *
     * @return $this
     */
    public function deactivate()
    {
        if ($this->active && $this->originalClient !== null) {
            // Restore the original client
            App::singleton('api-client', function () {
                return $this->originalClient;
            });
            
            $this->active = false;
        }
        
        return $this;
    }

    /**
     * Add a mocked response for a specific endpoint and method.
     *
     * @param string $method
     * @param string $endpoint
     * @param mixed $response
     * @param int $statusCode
     * @param array $headers
     * @return $this
     */
    public function mock($method, $endpoint, $response, $statusCode = 200, array $headers = [])
    {
        $method = strtolower($method);
        
        $this->responses[$method][$endpoint] = [
            'response' => $response,
            'status_code' => $statusCode,
            'headers' => $headers,
        ];
        
        return $this;
    }

    /**
     * Add a mocked GET response.
     *
     * @param string $endpoint
     * @param mixed $response
     * @param int $statusCode
     * @param array $headers
     * @return $this
     */
    public function mockGet($endpoint, $response, $statusCode = 200, array $headers = [])
    {
        return $this->mock('get', $endpoint, $response, $statusCode, $headers);
    }

    /**
     * Add a mocked POST response.
     *
     * @param string $endpoint
     * @param mixed $response
     * @param int $statusCode
     * @param array $headers
     * @return $this
     */
    public function mockPost($endpoint, $response, $statusCode = 201, array $headers = [])
    {
        return $this->mock('post', $endpoint, $response, $statusCode, $headers);
    }

    /**
     * Add a mocked PUT response.
     *
     * @param string $endpoint
     * @param mixed $response
     * @param int $statusCode
     * @param array $headers
     * @return $this
     */
    public function mockPut($endpoint, $response, $statusCode = 200, array $headers = [])
    {
        return $this->mock('put', $endpoint, $response, $statusCode, $headers);
    }

    /**
     * Add a mocked PATCH response.
     *
     * @param string $endpoint
     * @param mixed $response
     * @param int $statusCode
     * @param array $headers
     * @return $this
     */
    public function mockPatch($endpoint, $response, $statusCode = 200, array $headers = [])
    {
        return $this->mock('patch', $endpoint, $response, $statusCode, $headers);
    }

    /**
     * Add a mocked DELETE response.
     *
     * @param string $endpoint
     * @param mixed $response
     * @param int $statusCode
     * @param array $headers
     * @return $this
     */
    public function mockDelete($endpoint, $response, $statusCode = 204, array $headers = [])
    {
        return $this->mock('delete', $endpoint, $response, $statusCode, $headers);
    }

    /**
     * Get a mocked response for a request.
     *
     * @param string $method
     * @param string $endpoint
     * @param array $options
     * @return array|null
     */
    public function getResponse($method, $endpoint, array $options = [])
    {
        $method = strtolower($method);
        
        // Record the request in history
        $this->history[] = [
            'method' => $method,
            'endpoint' => $endpoint,
            'options' => $options,
            'time' => microtime(true),
        ];
        
        // Check for exact endpoint match
        if (isset($this->responses[$method][$endpoint])) {
            return $this->responses[$method][$endpoint];
        }
        
        // Check for pattern matches
        foreach ($this->responses[$method] ?? [] as $pattern => $response) {
            if ($this->endpointMatchesPattern($endpoint, $pattern)) {
                return $response;
            }
        }
        
        // No match found
        return null;
    }

    /**
     * Check if an endpoint matches a pattern.
     *
     * @param string $endpoint
     * @param string $pattern
     * @return bool
     */
    protected function endpointMatchesPattern($endpoint, $pattern)
    {
        // Check for wildcard patterns
        if (strpos($pattern, '*') !== false) {
            $pattern = preg_quote($pattern, '/');
            $pattern = str_replace('\*', '.*', $pattern);
            return preg_match('/^' . $pattern . '$/', $endpoint) === 1;
        }
        
        // Check for regex patterns
        if (strpos($pattern, '/') === 0) {
            return preg_match($pattern, $endpoint) === 1;
        }
        
        return false;
    }

    /**
     * Get the request history.
     *
     * @return array
     */
    public function getHistory()
    {
        return $this->history;
    }

    /**
     * Assert that a specific request was made.
     *
     * @param string $method
     * @param string $endpoint
     * @param array|null $options
     * @return bool
     */
    public function assertRequestMade($method, $endpoint, array $options = null)
    {
        $method = strtolower($method);
        
        foreach ($this->history as $request) {
            if ($request['method'] !== $method) {
                continue;
            }
            
            if ($request['endpoint'] !== $endpoint) {
                continue;
            }
            
            if ($options !== null) {
                $match = true;
                
                foreach ($options as $key => $value) {
                    if (Arr::get($request['options'], $key) !== $value) {
                        $match = false;
                        break;
                    }
                }
                
                if (!$match) {
                    continue;
                }
            }
            
            return true;
        }
        
        return false;
    }

    /**
     * Clear all mocked responses and history.
     *
     * @return $this
     */
    public function clear()
    {
        $this->responses = [];
        $this->history = [];
        
        return $this;
    }
}
