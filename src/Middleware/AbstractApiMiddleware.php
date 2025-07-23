<?php

namespace ApiModelRelations\Middleware;

use ApiModelRelations\Contracts\ApiMiddlewareInterface;

abstract class AbstractApiMiddleware implements ApiMiddlewareInterface
{
    /**
     * The priority of this middleware (lower numbers run first).
     *
     * @var int
     */
    protected $priority = 100;

    /**
     * Get the priority of this middleware (lower numbers run first).
     *
     * @return int
     */
    public function getPriority(): int
    {
        return $this->priority;
    }

    /**
     * Set the priority of this middleware.
     *
     * @param int $priority
     * @return $this
     */
    public function setPriority(int $priority)
    {
        $this->priority = $priority;
        
        return $this;
    }
}
