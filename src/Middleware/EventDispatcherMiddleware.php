<?php

namespace MTechStack\LaravelApiModelClient\Middleware;

use MTechStack\LaravelApiModelClient\Events\ApiExceptionEvent;
use MTechStack\LaravelApiModelClient\Events\ApiRequestEvent;
use MTechStack\LaravelApiModelClient\Events\ApiResponseEvent;
use MTechStack\LaravelApiModelClient\Exceptions\ApiException;

class EventDispatcherMiddleware extends AbstractApiMiddleware
{
    /**
     * The model class that triggered the request, if any.
     *
     * @var string|null
     */
    protected $modelClass;

    /**
     * Create a new event dispatcher middleware instance.
     *
     * @param string|null $modelClass
     * @return void
     */
    public function __construct(?string $modelClass = null)
    {
        $this->modelClass = $modelClass;
        $this->priority = 5; // Run first in the pipeline
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
        $method = $request['method'] ?? 'GET';
        $endpoint = $request['endpoint'] ?? '';
        $options = $request['options'] ?? [];
        
        // Dispatch the request event
        event(new ApiRequestEvent($method, $endpoint, $options, $this->modelClass));
        
        try {
            // Process the request through the pipeline
            $response = $next($request);
            
            // Dispatch the response event
            event(new ApiResponseEvent(
                $method,
                $endpoint,
                $options,
                $response['data'] ?? [],
                $response['status_code'] ?? 200,
                $this->modelClass
            ));
            
            return $response;
        } catch (ApiException $exception) {
            // Dispatch the exception event
            event(new ApiExceptionEvent(
                $method,
                $endpoint,
                $options,
                $exception,
                $this->modelClass
            ));
            
            // Re-throw the exception
            throw $exception;
        }
    }
}
