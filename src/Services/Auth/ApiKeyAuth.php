<?php

namespace ApiModelRelations\Services\Auth;

use ApiModelRelations\Contracts\AuthStrategyInterface;

class ApiKeyAuth implements AuthStrategyInterface
{
    /**
     * The API key.
     *
     * @var string|null
     */
    protected $apiKey;

    /**
     * The header name for the API key.
     *
     * @var string
     */
    protected $headerName;

    /**
     * Whether to use query parameter instead of header.
     *
     * @var bool
     */
    protected $useQueryParam;

    /**
     * The query parameter name for the API key.
     *
     * @var string
     */
    protected $queryParamName;

    /**
     * Create a new API key authentication strategy.
     *
     * @param string|null $apiKey
     * @param string $headerName
     * @param bool $useQueryParam
     * @param string $queryParamName
     * @return void
     */
    public function __construct(
        ?string $apiKey = null,
        string $headerName = 'X-API-KEY',
        bool $useQueryParam = false,
        string $queryParamName = 'api_key'
    ) {
        $this->apiKey = $apiKey;
        $this->headerName = $headerName;
        $this->useQueryParam = $useQueryParam;
        $this->queryParamName = $queryParamName;
    }

    /**
     * Apply authentication to the request.
     *
     * @param array $options Current request options
     * @return array Modified request options with authentication
     */
    public function applyToRequest(array $options): array
    {
        if ($this->apiKey === null) {
            return $options;
        }

        if ($this->useQueryParam) {
            // Add API key as query parameter
            $options['query'] = array_merge(
                $options['query'] ?? [],
                [$this->queryParamName => $this->apiKey]
            );
        } else {
            // Add API key as header
            $options['headers'] = array_merge(
                $options['headers'] ?? [],
                [$this->headerName => $this->apiKey]
            );
        }

        return $options;
    }

    /**
     * Set the credentials for this authentication strategy.
     *
     * @param array $credentials
     * @return $this
     */
    public function setCredentials(array $credentials)
    {
        if (isset($credentials['api_key'])) {
            $this->apiKey = $credentials['api_key'];
        }

        if (isset($credentials['header_name'])) {
            $this->headerName = $credentials['header_name'];
        }

        if (isset($credentials['use_query_param'])) {
            $this->useQueryParam = (bool) $credentials['use_query_param'];
        }

        if (isset($credentials['query_param_name'])) {
            $this->queryParamName = $credentials['query_param_name'];
        }

        return $this;
    }

    /**
     * Get the name of this authentication strategy.
     *
     * @return string
     */
    public function getName(): string
    {
        return 'api_key';
    }
}
