<?php

namespace MTechStack\LaravelApiModelClient\Events;

use MTechStack\LaravelApiModelClient\Exceptions\ApiException;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ApiExceptionEvent
{
    use Dispatchable, SerializesModels;

    /**
     * The HTTP method.
     *
     * @var string
     */
    public $method;

    /**
     * The endpoint URL.
     *
     * @var string
     */
    public $endpoint;

    /**
     * The request options.
     *
     * @var array
     */
    public $options;

    /**
     * The exception that occurred.
     *
     * @var \ApiModelRelations\Exceptions\ApiException
     */
    public $exception;

    /**
     * The model class that triggered the request, if any.
     *
     * @var string|null
     */
    public $modelClass;

    /**
     * Create a new event instance.
     *
     * @param string $method
     * @param string $endpoint
     * @param array $options
     * @param \ApiModelRelations\Exceptions\ApiException $exception
     * @param string|null $modelClass
     * @return void
     */
    public function __construct(
        string $method, 
        string $endpoint, 
        array $options, 
        ApiException $exception, 
        ?string $modelClass = null
    ) {
        $this->method = $method;
        $this->endpoint = $endpoint;
        $this->options = $options;
        $this->exception = $exception;
        $this->modelClass = $modelClass;
    }
}
