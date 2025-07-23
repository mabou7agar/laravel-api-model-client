<?php

namespace ApiModelRelations\Testing;

use ApiModelRelations\Contracts\ApiClientInterface;
use ApiModelRelations\Contracts\AuthStrategyInterface;
use ApiModelRelations\Exceptions\ApiException;

class MockApiClient implements ApiClientInterface
{
    /**
     * The API mock handler instance.
     *
     * @var \ApiModelRelations\Testing\ApiMockHandler
     */
    protected $mockHandler;

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
     * Create a new mock API client instance.
     *
     * @param \ApiModelRelations\Testing\ApiMockHandler $mockHandler
     * @param string|null $baseUrl
     * @return void
     */
    public function __construct(ApiMockHandler $mockHandler, ?string $baseUrl = null)
    {
        $this->mockHandler = $mockHandler;
        $this->baseUrl = $baseUrl;
    }

    /**
     * Set the base URL for API requests.
     *
     * @param string $url
     * @return $this
     */
    public function setBaseUrl(string $url)
    {
        $this->baseUrl = $url;
        
        return $this;
    }

    /**
     * Set the authentication strategy.
     *
     * @param \ApiModelRelations\Contracts\AuthStrategyInterface $strategy
     * @return $this
     */
    public function setAuthStrategy(AuthStrategyInterface $strategy)
    {
        $this->authStrategy = $strategy;
        
        return $this;
    }

    /**
     * Send a GET request to the API.
     *
     * @param string $endpoint
     * @param array $queryParams
     * @param array $headers
     * @return array
     */
    public function get(string $endpoint, array $queryParams = [], array $headers = []): array
    {
        return $this->request('get', $endpoint, [
            'query' => $queryParams,
            'headers' => $headers,
        ]);
    }

    /**
     * Send a POST request to the API.
     *
     * @param string $endpoint
     * @param array $data
     * @param array $headers
     * @return array
     */
    public function post(string $endpoint, array $data = [], array $headers = []): array
    {
        return $this->request('post', $endpoint, [
            'json' => $data,
            'headers' => $headers,
        ]);
    }

    /**
     * Send a PUT request to the API.
     *
     * @param string $endpoint
     * @param array $data
     * @param array $headers
     * @return array
     */
    public function put(string $endpoint, array $data = [], array $headers = []): array
    {
        return $this->request('put', $endpoint, [
            'json' => $data,
            'headers' => $headers,
        ]);
    }

    /**
     * Send a PATCH request to the API.
     *
     * @param string $endpoint
     * @param array $data
     * @param array $headers
     * @return array
     */
    public function patch(string $endpoint, array $data = [], array $headers = []): array
    {
        return $this->request('patch', $endpoint, [
            'json' => $data,
            'headers' => $headers,
        ]);
    }

    /**
     * Send a DELETE request to the API.
     *
     * @param string $endpoint
     * @param array $queryParams
     * @param array $headers
     * @return array
     */
    public function delete(string $endpoint, array $queryParams = [], array $headers = []): array
    {
        return $this->request('delete', $endpoint, [
            'query' => $queryParams,
            'headers' => $headers,
        ]);
    }

    /**
     * Send a request to the API.
     *
     * @param string $method
     * @param string $endpoint
     * @param array $options
     * @return array
     *
     * @throws \ApiModelRelations\Exceptions\ApiException
     */
    protected function request(string $method, string $endpoint, array $options = []): array
    {
        // Apply authentication if available
        if ($this->authStrategy !== null) {
            $options = $this->authStrategy->applyToRequest($options);
        }

        // Get the mocked response
        $mockResponse = $this->mockHandler->getResponse($method, $endpoint, $options);
        
        if ($mockResponse === null) {
            throw new ApiException(
                "No mock response found for {$method} request to {$endpoint}",
                404
            );
        }
        
        $statusCode = $mockResponse['status_code'] ?? 200;
        
        // If status code indicates an error, throw an exception
        if ($statusCode >= 400) {
            throw new ApiException(
                "API request failed with status code {$statusCode}",
                $statusCode,
                null,
                $mockResponse['response'] ?? []
            );
        }
        
        return $mockResponse['response'] ?? [];
    }
}
