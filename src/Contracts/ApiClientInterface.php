<?php

namespace MTechStack\LaravelApiModelClient\Contracts;

interface ApiClientInterface
{
    /**
     * Send a GET request to the API.
     *
     * @param string $endpoint
     * @param array $queryParams
     * @param array $headers
     * @return mixed
     */
    public function get(string $endpoint, array $queryParams = [], array $headers = []);

    /**
     * Send a POST request to the API.
     *
     * @param string $endpoint
     * @param array $data
     * @param array $headers
     * @return mixed
     */
    public function post(string $endpoint, array $data = [], array $headers = []);

    /**
     * Send a PUT request to the API.
     *
     * @param string $endpoint
     * @param array $data
     * @param array $headers
     * @return mixed
     */
    public function put(string $endpoint, array $data = [], array $headers = []);

    /**
     * Send a PATCH request to the API.
     *
     * @param string $endpoint
     * @param array $data
     * @param array $headers
     * @return mixed
     */
    public function patch(string $endpoint, array $data = [], array $headers = []);

    /**
     * Send a DELETE request to the API.
     *
     * @param string $endpoint
     * @param array $queryParams
     * @param array $headers
     * @return mixed
     */
    public function delete(string $endpoint, array $queryParams = [], array $headers = []);

    /**
     * Set the authentication strategy.
     *
     * @param \ApiModelRelations\Contracts\AuthStrategyInterface $authStrategy
     * @return $this
     */
    public function setAuthStrategy(\ApiModelRelations\Contracts\AuthStrategyInterface $authStrategy);

    /**
     * Set the base URL for API requests.
     *
     * @param string $baseUrl
     * @return $this
     */
    public function setBaseUrl(string $baseUrl);
}
