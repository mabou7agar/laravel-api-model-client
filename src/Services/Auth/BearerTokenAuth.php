<?php

namespace ApiModelRelations\Services\Auth;

use ApiModelRelations\Contracts\AuthStrategyInterface;

class BearerTokenAuth implements AuthStrategyInterface
{
    /**
     * The bearer token.
     *
     * @var string|null
     */
    protected $token;

    /**
     * Create a new bearer token authentication strategy.
     *
     * @param string|null $token
     * @return void
     */
    public function __construct(?string $token = null)
    {
        $this->token = $token;
    }

    /**
     * Apply authentication to the request.
     *
     * @param array $options Current request options
     * @return array Modified request options with authentication
     */
    public function applyToRequest(array $options): array
    {
        if ($this->token === null) {
            return $options;
        }

        // Add Authorization header with Bearer token
        $options['headers'] = array_merge(
            $options['headers'] ?? [],
            ['Authorization' => 'Bearer ' . $this->token]
        );

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
        if (isset($credentials['token'])) {
            $this->token = $credentials['token'];
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
        return 'bearer';
    }
}
