<?php

namespace ApiModelRelations\Services\Auth;

use ApiModelRelations\Contracts\AuthStrategyInterface;

class BasicAuth implements AuthStrategyInterface
{
    /**
     * The username.
     *
     * @var string|null
     */
    protected $username;

    /**
     * The password.
     *
     * @var string|null
     */
    protected $password;

    /**
     * Create a new basic authentication strategy.
     *
     * @param string|null $username
     * @param string|null $password
     * @return void
     */
    public function __construct(?string $username = null, ?string $password = null)
    {
        $this->username = $username;
        $this->password = $password;
    }

    /**
     * Apply authentication to the request.
     *
     * @param array $options Current request options
     * @return array Modified request options with authentication
     */
    public function applyToRequest(array $options): array
    {
        if ($this->username === null || $this->password === null) {
            return $options;
        }

        // Add basic auth to the request
        $options['auth'] = [$this->username, $this->password];

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
        if (isset($credentials['username'])) {
            $this->username = $credentials['username'];
        }

        if (isset($credentials['password'])) {
            $this->password = $credentials['password'];
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
        return 'basic';
    }
}
