<?php

namespace ApiModelRelations\Middleware;

use Illuminate\Cache\RateLimiter;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;

class RateLimitMiddleware extends AbstractApiMiddleware
{
    /**
     * The rate limiter instance.
     *
     * @var \Illuminate\Cache\RateLimiter
     */
    protected $limiter;

    /**
     * The maximum number of attempts.
     *
     * @var int
     */
    protected $maxAttempts;

    /**
     * The number of minutes until the rate limit is reset.
     *
     * @var int
     */
    protected $decayMinutes;

    /**
     * Create a new rate limit middleware instance.
     *
     * @param int|null $maxAttempts
     * @param int|null $decayMinutes
     * @return void
     */
    public function __construct(?int $maxAttempts = null, ?int $decayMinutes = null)
    {
        $this->limiter = App::make(RateLimiter::class);
        $this->priority = 20; // Run early in the pipeline
        
        $this->maxAttempts = $maxAttempts ?? Config::get('api-model-relations.rate_limiting.max_attempts', 60);
        $this->decayMinutes = $decayMinutes ?? Config::get('api-model-relations.rate_limiting.decay_minutes', 1);
    }

    /**
     * Process the API request.
     *
     * @param array $request The request data
     * @param callable $next The next middleware in the pipeline
     * @return array The processed response
     *
     * @throws \ApiModelRelations\Exceptions\ApiException
     */
    public function handle(array $request, callable $next): array
    {
        // Skip rate limiting if disabled in config
        if (!Config::get('api-model-relations.rate_limiting.enabled', true)) {
            return $next($request);
        }
        
        $key = $this->resolveRequestSignature($request);
        
        if ($this->limiter->tooManyAttempts($key, $this->maxAttempts)) {
            $retryAfter = $this->limiter->availableIn($key);
            
            throw new \ApiModelRelations\Exceptions\ApiException(
                "Too many API requests. Please try again in {$retryAfter} seconds.",
                429,
                null,
                ['retry_after' => $retryAfter]
            );
        }
        
        $this->limiter->hit($key, $this->decayMinutes * 60);
        
        return $next($request);
    }

    /**
     * Resolve the request signature for rate limiting.
     *
     * @param array $request
     * @return string
     */
    protected function resolveRequestSignature(array $request): string
    {
        $endpoint = $request['endpoint'] ?? '';
        $method = $request['method'] ?? 'GET';
        
        // Use the configured key prefix
        $keyPrefix = Config::get('api-model-relations.rate_limiting.key_prefix', 'api_rate_limit');
        
        // Generate a signature based on the endpoint and method
        return $keyPrefix . ':' . strtolower($method) . ':' . md5($endpoint);
    }
}
