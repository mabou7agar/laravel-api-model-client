<?php

namespace ApiModelRelations\Contracts;

interface ApiMiddlewareInterface
{
    /**
     * Process the API request.
     *
     * @param array $request The request data
     * @param callable $next The next middleware in the pipeline
     * @return array The processed response
     */
    public function handle(array $request, callable $next): array;
    
    /**
     * Get the priority of this middleware (lower numbers run first).
     *
     * @return int
     */
    public function getPriority(): int;
}
