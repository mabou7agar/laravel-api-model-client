<?php

namespace MTechStack\LaravelApiModelClient\Services;

use MTechStack\LaravelApiModelClient\Contracts\ApiMiddlewareInterface;
use Illuminate\Support\Collection;

class ApiPipeline
{
    /**
     * The collection of middleware.
     *
     * @var \Illuminate\Support\Collection
     */
    protected $middleware;

    /**
     * Create a new API pipeline instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware = new Collection();
    }

    /**
     * Add middleware to the pipeline.
     *
     * @param \MTechStack\LaravelApiModelClient\Contracts\ApiMiddlewareInterface $middleware
     * @return $this
     */
    public function pipe(ApiMiddlewareInterface $middleware)
    {
        $this->middleware->push($middleware);
        
        return $this;
    }

    /**
     * Process the request through the pipeline.
     *
     * @param array $request
     * @param callable $destination
     * @return array
     */
    public function process(array $request, callable $destination): array
    {
        // Sort middleware by priority (lower numbers run first)
        $middleware = $this->middleware->sortBy(function ($middleware) {
            return $middleware->getPriority();
        });
        
        // Create the pipeline
        $pipeline = array_reduce(
            $middleware->reverse()->all(),
            $this->carry(),
            $destination
        );
        
        // Process the request through the pipeline
        return $pipeline($request);
    }

    /**
     * Get a callable that will carry the request through each middleware.
     *
     * @return callable
     */
    protected function carry(): callable
    {
        return function ($stack, $pipe) {
            return function ($request) use ($stack, $pipe) {
                return $pipe->handle($request, $stack);
            };
        };
    }

    /**
     * Clear all middleware from the pipeline.
     *
     * @return $this
     */
    public function clear()
    {
        $this->middleware = new Collection();
        
        return $this;
    }

    /**
     * Get all middleware in the pipeline.
     *
     * @return \Illuminate\Support\Collection
     */
    public function getMiddleware(): Collection
    {
        return $this->middleware;
    }
}
