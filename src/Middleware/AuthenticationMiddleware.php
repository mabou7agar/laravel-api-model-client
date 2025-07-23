<?php

namespace ApiModelRelations\Middleware;

use ApiModelRelations\Contracts\AuthStrategyInterface;

class AuthenticationMiddleware extends AbstractApiMiddleware
{
    /**
     * The authentication strategy.
     *
     * @var \ApiModelRelations\Contracts\AuthStrategyInterface
     */
    protected $authStrategy;

    /**
     * Create a new authentication middleware instance.
     *
     * @param \ApiModelRelations\Contracts\AuthStrategyInterface $authStrategy
     * @return void
     */
    public function __construct(AuthStrategyInterface $authStrategy)
    {
        $this->authStrategy = $authStrategy;
        $this->priority = 10; // Run very early in the pipeline
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
        // Apply authentication to the request options
        if (isset($request['options'])) {
            $request['options'] = $this->authStrategy->applyToRequest($request['options']);
        } else {
            $request['options'] = $this->authStrategy->applyToRequest([]);
        }
        
        return $next($request);
    }
}
