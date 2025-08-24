<?php

namespace MTechStack\LaravelApiModelClient\Repositories;

use MTechStack\LaravelApiModelClient\Contracts\ApiClientInterface;
use MTechStack\LaravelApiModelClient\Contracts\ApiRepositoryInterface;
use MTechStack\LaravelApiModelClient\Exceptions\ApiException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

abstract class AbstractApiRepository implements ApiRepositoryInterface
{
    /**
     * The API client instance.
     *
     * @var \ApiModelRelations\Contracts\ApiClientInterface
     */
    protected $apiClient;

    /**
     * The API endpoint.
     *
     * @var string
     */
    protected $endpoint;

    /**
     * The cache TTL in seconds.
     *
     * @var int
     */
    protected $cacheTtl = 3600;

    /**
     * Whether to enable caching.
     *
     * @var bool
     */
    protected $cacheEnabled = true;

    /**
     * Create a new API repository instance.
     *
     * @param string|null $endpoint
     * @param \ApiModelRelations\Contracts\ApiClientInterface|null $apiClient
     * @return void
     */
    public function __construct(?string $endpoint = null, ?ApiClientInterface $apiClient = null)
    {
        $this->endpoint = $endpoint ?? $this->getDefaultEndpoint();
        $this->apiClient = $apiClient ?? App::make('api-client');
        
        $this->cacheEnabled = config('api-model-relations.cache.enabled', true);
        $this->cacheTtl = config('api-model-relations.cache.ttl', 3600);
    }

    /**
     * Get all resources from the API.
     *
     * @param array $params
     * @return \Illuminate\Support\Collection
     */
    public function all(array $params = []): Collection
    {
        $cacheKey = $this->getCacheKey('all', $params);
        
        if ($this->shouldUseCache()) {
            $cachedData = Cache::get($cacheKey);
            if ($cachedData !== null) {
                return new Collection($cachedData);
            }
        }
        
        try {
            $response = $this->apiClient->get($this->endpoint, $params);
            $items = $this->extractItemsFromResponse($response);
            
            if ($this->shouldUseCache()) {
                Cache::put($cacheKey, $items, $this->cacheTtl);
            }
            
            return new Collection($items);
        } catch (\Exception $e) {
            $this->handleException($e, 'Error fetching all resources', [
                'endpoint' => $this->endpoint,
                'params' => $params,
            ]);
            
            return new Collection();
        }
    }

    /**
     * Find a resource by its ID.
     *
     * @param mixed $id
     * @param array $params
     * @return array|null
     */
    public function find($id, array $params = []): ?array
    {
        $cacheKey = $this->getCacheKey('find', ['id' => $id, 'params' => $params]);
        
        if ($this->shouldUseCache()) {
            $cachedData = Cache::get($cacheKey);
            if ($cachedData !== null) {
                return $cachedData;
            }
        }
        
        try {
            $endpoint = $this->buildEndpointWithId($id);
            $response = $this->apiClient->get($endpoint, $params);
            
            if ($this->shouldUseCache()) {
                Cache::put($cacheKey, $response, $this->cacheTtl);
            }
            
            return $response;
        } catch (\Exception $e) {
            $this->handleException($e, 'Error finding resource', [
                'endpoint' => $this->buildEndpointWithId($id),
                'id' => $id,
                'params' => $params,
            ]);
            
            return null;
        }
    }

    /**
     * Create a new resource in the API.
     *
     * @param array $data
     * @return array
     */
    public function create(array $data): array
    {
        try {
            $response = $this->apiClient->post($this->endpoint, $data);
            
            // Clear cache for all() method since we've added a new resource
            $this->clearCacheForMethod('all');
            
            return $response;
        } catch (\Exception $e) {
            $this->handleException($e, 'Error creating resource', [
                'endpoint' => $this->endpoint,
                'data' => $data,
            ]);
            
            throw $e;
        }
    }

    /**
     * Update a resource in the API.
     *
     * @param mixed $id
     * @param array $data
     * @return array
     */
    public function update($id, array $data): array
    {
        try {
            $endpoint = $this->buildEndpointWithId($id);
            $response = $this->apiClient->put($endpoint, $data);
            
            // Clear cache for this specific resource and all() method
            $this->clearCacheForMethod('find', ['id' => $id]);
            $this->clearCacheForMethod('all');
            
            return $response;
        } catch (\Exception $e) {
            $this->handleException($e, 'Error updating resource', [
                'endpoint' => $this->buildEndpointWithId($id),
                'id' => $id,
                'data' => $data,
            ]);
            
            throw $e;
        }
    }

    /**
     * Delete a resource from the API.
     *
     * @param mixed $id
     * @return bool
     */
    public function delete($id): bool
    {
        try {
            $endpoint = $this->buildEndpointWithId($id);
            $this->apiClient->delete($endpoint);
            
            // Clear cache for this specific resource and all() method
            $this->clearCacheForMethod('find', ['id' => $id]);
            $this->clearCacheForMethod('all');
            
            return true;
        } catch (\Exception $e) {
            $this->handleException($e, 'Error deleting resource', [
                'endpoint' => $this->buildEndpointWithId($id),
                'id' => $id,
            ]);
            
            return false;
        }
    }

    /**
     * Get resources related to the specified resource.
     *
     * @param mixed $id
     * @param string $relation
     * @param array $params
     * @return \Illuminate\Support\Collection
     */
    public function getRelated($id, string $relation, array $params = []): Collection
    {
        $cacheKey = $this->getCacheKey('related', [
            'id' => $id,
            'relation' => $relation,
            'params' => $params,
        ]);
        
        if ($this->shouldUseCache()) {
            $cachedData = Cache::get($cacheKey);
            if ($cachedData !== null) {
                return new Collection($cachedData);
            }
        }
        
        try {
            $endpoint = $this->buildEndpointWithId($id) . '/' . $relation;
            $response = $this->apiClient->get($endpoint, $params);
            $items = $this->extractItemsFromResponse($response);
            
            if ($this->shouldUseCache()) {
                Cache::put($cacheKey, $items, $this->cacheTtl);
            }
            
            return new Collection($items);
        } catch (\Exception $e) {
            $this->handleException($e, 'Error fetching related resources', [
                'endpoint' => $this->buildEndpointWithId($id) . '/' . $relation,
                'id' => $id,
                'relation' => $relation,
                'params' => $params,
            ]);
            
            return new Collection();
        }
    }

    /**
     * Get the API endpoint for this repository.
     *
     * @return string
     */
    public function getEndpoint(): string
    {
        return $this->endpoint;
    }

    /**
     * Set the API endpoint for this repository.
     *
     * @param string $endpoint
     * @return $this
     */
    public function setEndpoint(string $endpoint)
    {
        $this->endpoint = $endpoint;
        
        return $this;
    }

    /**
     * Get the API client instance.
     *
     * @return \ApiModelRelations\Contracts\ApiClientInterface
     */
    public function getApiClient(): ApiClientInterface
    {
        return $this->apiClient;
    }

    /**
     * Set the API client instance.
     *
     * @param \ApiModelRelations\Contracts\ApiClientInterface $client
     * @return $this
     */
    public function setApiClient(ApiClientInterface $client)
    {
        $this->apiClient = $client;
        
        return $this;
    }

    /**
     * Set the cache TTL.
     *
     * @param int $ttl
     * @return $this
     */
    public function setCacheTtl(int $ttl)
    {
        $this->cacheTtl = $ttl;
        
        return $this;
    }

    /**
     * Enable or disable caching.
     *
     * @param bool $enabled
     * @return $this
     */
    public function setCacheEnabled(bool $enabled)
    {
        $this->cacheEnabled = $enabled;
        
        return $this;
    }

    /**
     * Get the default endpoint for this repository.
     *
     * @return string
     */
    abstract protected function getDefaultEndpoint(): string;

    /**
     * Build an endpoint with the given ID.
     *
     * @param mixed $id
     * @return string
     */
    protected function buildEndpointWithId($id): string
    {
        return rtrim($this->endpoint, '/') . '/' . $id;
    }

    /**
     * Extract items from an API response.
     *
     * @param array $response
     * @return array
     */
    protected function extractItemsFromResponse(array $response): array
    {
        // If response is already an array of items, return it
        if (isset($response[0])) {
            return $response;
        }
        
        // Check for common wrapper keys
        $possibleKeys = ['data', 'items', 'results', 'records', 'content'];
        
        foreach ($possibleKeys as $key) {
            if (isset($response[$key]) && is_array($response[$key])) {
                return $response[$key];
            }
        }
        
        // If we can't find a collection, return the response as is
        return $response;
    }

    /**
     * Get a cache key for a method and parameters.
     *
     * @param string $method
     * @param array $params
     * @return string
     */
    protected function getCacheKey(string $method, array $params = []): string
    {
        $prefix = config('api-model-relations.cache.prefix', 'api_model_');
        $class = str_replace('\\', '_', get_class($this));
        $paramsString = md5(json_encode($params));
        
        return $prefix . $class . '_' . $method . '_' . $paramsString;
    }

    /**
     * Clear the cache for a specific method and parameters.
     *
     * @param string $method
     * @param array $params
     * @return void
     */
    protected function clearCacheForMethod(string $method, array $params = []): void
    {
        if ($this->shouldUseCache()) {
            $cacheKey = $this->getCacheKey($method, $params);
            Cache::forget($cacheKey);
        }
    }

    /**
     * Determine if caching should be used.
     *
     * @return bool
     */
    protected function shouldUseCache(): bool
    {
        return $this->cacheEnabled && $this->cacheTtl > 0;
    }

    /**
     * Handle an exception.
     *
     * @param \Exception $exception
     * @param string $message
     * @param array $context
     * @return void
     */
    protected function handleException(\Exception $exception, string $message, array $context = []): void
    {
        if (config('api-model-relations.error_handling.log_errors', true)) {
            $logContext = array_merge($context, [
                'exception' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);
            
            Log::error($message, $logContext);
        }
    }
}
