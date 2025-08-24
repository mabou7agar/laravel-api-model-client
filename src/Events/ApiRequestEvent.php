<?php

namespace MTechStack\LaravelApiModelClient\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ApiRequestEvent
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
     * @param string|null $modelClass
     * @return void
     */
    public function __construct(string $method, string $endpoint, array $options, ?string $modelClass = null)
    {
        $this->method = $method;
        $this->endpoint = $endpoint;
        $this->options = $options;
        $this->modelClass = $modelClass;
    }
}
