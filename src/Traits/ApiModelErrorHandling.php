<?php

namespace MTechStack\LaravelApiModelClient\Traits;

use MTechStack\LaravelApiModelClient\Exceptions\ApiModelException;
use Illuminate\Support\Facades\Log;

/**
 * Trait for handling API model errors
 */
trait ApiModelErrorHandling
{
    /**
     * Whether to throw exceptions on API errors.
     *
     * @var bool
     */
    protected $throwExceptions = false;

    /**
     * Whether to log API errors.
     *
     * @var bool
     */
    protected $logErrors = true;

    /**
     * The last error message.
     *
     * @var string|null
     */
    protected $lastError;

    /**
     * The last error response.
     *
     * @var array|null
     */
    protected $lastErrorResponse;

    /**
     * The last error code.
     *
     * @var int|null
     */
    protected $lastErrorCode;

    /**
     * Handle an API error.
     *
     * @param  string  $message
     * @param  array|null  $response
     * @param  int|null  $code
     * @return void
     *
     * @throws \MTechStack\LaravelApiModelClient\Exceptions\ApiModelException
     */
    protected function handleApiError($message, $response = null, $code = null)
    {
        $this->lastError = $message;
        $this->lastErrorResponse = $response;
        $this->lastErrorCode = $code;

        // Fire the API failed event
        $this->fireApiModelEvent('apiFailed', [
            'message' => $message,
            'response' => $response,
            'code' => $code,
        ]);

        // Log the error if enabled
        if ($this->shouldLogErrors()) {
            $this->logApiError($message, $response, $code);
        }

        // Throw an exception if enabled
        if ($this->shouldThrowExceptions()) {
            throw new ApiModelException($message, $code ?? 0);
        }
    }

    /**
     * Log an API error.
     *
     * @param  string  $message
     * @param  array|null  $response
     * @param  int|null  $code
     * @return void
     */
    protected function logApiError($message, $response = null, $code = null)
    {
        $context = [
            'model' => get_class($this),
            'response' => $response,
            'code' => $code,
        ];

        Log::error("API Model Error: {$message}", $context);
    }

    /**
     * Determine if exceptions should be thrown on API errors.
     *
     * @return bool
     */
    public function shouldThrowExceptions()
    {
        return $this->throwExceptions || config('api-model-relations.throw_exceptions', false);
    }

    /**
     * Enable or disable throwing exceptions on API errors.
     *
     * @param  bool  $throw
     * @return $this
     */
    public function setThrowExceptions($throw = true)
    {
        $this->throwExceptions = $throw;

        return $this;
    }

    /**
     * Determine if API errors should be logged.
     *
     * @return bool
     */
    public function shouldLogErrors()
    {
        return $this->logErrors && config('api-model-relations.log_errors', true);
    }

    /**
     * Enable or disable logging API errors.
     *
     * @param  bool  $log
     * @return $this
     */
    public function setLogErrors($log = true)
    {
        $this->logErrors = $log;

        return $this;
    }

    /**
     * Get the last error message.
     *
     * @return string|null
     */
    public function getLastError()
    {
        return $this->lastError;
    }

    /**
     * Get the last error response.
     *
     * @return array|null
     */
    public function getLastErrorResponse()
    {
        return $this->lastErrorResponse;
    }

    /**
     * Get the last error code.
     *
     * @return int|null
     */
    public function getLastErrorCode()
    {
        return $this->lastErrorCode;
    }

    /**
     * Clear the last error.
     *
     * @return $this
     */
    public function clearLastError()
    {
        $this->lastError = null;
        $this->lastErrorResponse = null;
        $this->lastErrorCode = null;

        return $this;
    }

    /**
     * Determine if the model has an error.
     *
     * @return bool
     */
    public function hasError()
    {
        return $this->lastError !== null;
    }
}
