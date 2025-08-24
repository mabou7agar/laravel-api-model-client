<?php

namespace MTechStack\LaravelApiModelClient\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ApiResponseEvent
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
     * The response data.
     *
     * @var array
     */
    public $response;

    /**
     * The HTTP status code.
     *
     * @var int
     */
    public $statusCode;

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
     * @param array $response
     * @param int $statusCode
     * @param string|null $modelClass
     * @return void
     */
    public function __construct(
        string $method, 
        string $endpoint, 
        array $options, 
        array $response, 
        int $statusCode, 
        ?string $modelClass = null
    ) {
        $this->method = $method;
        $this->endpoint = $endpoint;
        $this->options = $options;
        $this->response = $response;
        $this->statusCode = $statusCode;
        $this->modelClass = $modelClass;
    }
}
