<?php

namespace MTechStack\LaravelApiModelClient\Exceptions;

use Exception;
use Throwable;

class ApiException extends Exception
{
    /**
     * The response data from the API.
     *
     * @var array|null
     */
    protected $response;

    /**
     * Create a new API exception instance.
     *
     * @param string $message
     * @param int $code
     * @param \Throwable|null $previous
     * @param array|null $response
     * @return void
     */
    public function __construct(string $message = "", int $code = 0, Throwable $previous = null, ?array $response = null)
    {
        parent::__construct($message, $code, $previous);
        $this->response = $response;
    }

    /**
     * Get the response data from the API.
     *
     * @return array|null
     */
    public function getResponse(): ?array
    {
        return $this->response;
    }

    /**
     * Check if the exception has response data.
     *
     * @return bool
     */
    public function hasResponse(): bool
    {
        return $this->response !== null;
    }
}
