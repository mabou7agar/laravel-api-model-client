<?php

namespace MTechStack\LaravelApiModelClient\Contracts;

interface AuthStrategyInterface
{
    /**
     * Apply authentication to the request.
     *
     * @param array $options Current request options
     * @return array Modified request options with authentication
     */
    public function applyToRequest(array $options): array;
    
    /**
     * Set the credentials for this authentication strategy.
     *
     * @param array $credentials
     * @return $this
     */
    public function setCredentials(array $credentials);
    
    /**
     * Get the name of this authentication strategy.
     *
     * @return string
     */
    public function getName(): string;
}
