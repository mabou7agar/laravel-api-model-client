<?php

namespace MTechStack\LaravelApiModelClient\Traits;

use MTechStack\LaravelApiModelClient\Exceptions\ApiException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

trait HasApiOperations
{
    /**
     * Implementation of findFromApi.
     *
     * @param mixed $id
     * @return static|null
     */
    protected function findFromApiImpl($id)
    {
        $cacheKey = $this->getApiCacheKey('find', $id);
        $cacheTtl = $this->getCacheTtl();
        
        // Check if we have a cached response
        if (config('api-model-relations.cache.enabled', true) && $cacheTtl > 0) {
            $cachedData = Cache::get($cacheKey);
            if ($cachedData !== null) {
                return $this->newFromApiResponse($cachedData);
            }
        }
        
        try {
            // Fire before find event
            $this->fireApiEvent('finding', $id);
            
            // Make API request
            $endpoint = $this->getApiEndpoint() . '/' . $id;
            $response = $this->getApiClient()->get($endpoint);
            
            // Fire after find event
            $this->fireApiEvent('found', $response);
            
            // Cache the response if caching is enabled
            if (config('api-model-relations.cache.enabled', true) && $cacheTtl > 0) {
                Cache::put($cacheKey, $response, $cacheTtl);
            }
            
            return $this->newFromApiResponse($response);
        } catch (\Exception $e) {
            $this->handleApiException($e, 'Error finding model with ID ' . $id);
            return null;
        }
    }
    
    /**
     * Implementation of allFromApi.
     *
     * @return \Illuminate\Support\Collection
     */
    protected function allFromApiImpl()
    {
        $cacheKey = $this->getApiCacheKey('all');
        $cacheTtl = $this->getCacheTtl();
        
        // Check if we have a cached response
        if (config('api-model-relations.cache.enabled', true) && $cacheTtl > 0) {
            $cachedData = Cache::get($cacheKey);
            if ($cachedData !== null) {
                return $this->newCollectionFromApiResponse($cachedData);
            }
        }
        
        try {
            // Fire before all event
            $this->fireApiEvent('retrievingAll');
            
            // Make API request
            $endpoint = $this->getApiEndpoint();
            $response = $this->getApiClient()->get($endpoint);
            
            // Fire after all event
            $this->fireApiEvent('retrievedAll', $response);
            
            // Cache the response if caching is enabled
            if (config('api-model-relations.cache.enabled', true) && $cacheTtl > 0) {
                Cache::put($cacheKey, $response, $cacheTtl);
            }
            
            return $this->newCollectionFromApiResponse($response);
        } catch (\Exception $e) {
            $this->handleApiException($e, 'Error retrieving all models');
            return new Collection();
        }
    }
    
    /**
     * Implementation of saveToApi.
     *
     * @return bool
     */
    protected function saveToApiImpl()
    {
        try {
            $endpoint = $this->getApiEndpoint();
            $data = $this->mapAttributesToApiRequest($this->getAttributes());
            
            // Fire before save event
            $this->fireApiEvent('saving', $data);
            
            // Determine if this is a create or update operation
            $isUpdate = $this->exists;
            
            if ($isUpdate) {
                // Update existing model
                $id = $this->getKey();
                $endpoint .= '/' . $id;
                $response = $this->getApiClient()->put($endpoint, $data);
                
                // Clear the cache for this model
                $this->clearModelCache($id);
            } else {
                // Create new model
                $response = $this->getApiClient()->post($endpoint, $data);
                
                // Set the ID from the response if available
                if (isset($response[$this->getKeyName()])) {
                    $this->setAttribute($this->getKeyName(), $response[$this->getKeyName()]);
                    $this->exists = true;
                }
            }
            
            // Fire after save event
            $this->fireApiEvent($isUpdate ? 'updated' : 'created', $response);
            
            // Clear the "all" cache
            $this->clearAllCache();
            
            return true;
        } catch (\Exception $e) {
            $this->handleApiException($e, 'Error saving model');
            return false;
        }
    }
    
    /**
     * Implementation of deleteFromApi.
     *
     * @return bool
     */
    protected function deleteFromApiImpl()
    {
        try {
            $id = $this->getKey();
            $endpoint = $this->getApiEndpoint() . '/' . $id;
            
            // Fire before delete event
            $this->fireApiEvent('deleting', $id);
            
            // Make API request
            $response = $this->getApiClient()->delete($endpoint);
            
            // Fire after delete event
            $this->fireApiEvent('deleted', $response);
            
            // Clear the cache for this model
            $this->clearModelCache($id);
            
            // Clear the "all" cache
            $this->clearAllCache();
            
            return true;
        } catch (\Exception $e) {
            $this->handleApiException($e, 'Error deleting model with ID ' . $this->getKey());
            return false;
        }
    }
    
    /**
     * Create a new model instance from an API response.
     *
     * @param array $response
     * @return static|null
     */
    protected function newFromApiResponse($response = [])
    {
        if (empty($response)) {
            return null;
        }
        
        // Map API fields to model attributes
        $attributes = $this->mapApiResponseToAttributes($response);
        
        // Cast attributes to their proper types
        $attributes = $this->castApiResponseData($attributes);
        
        // Create a new model instance with the attributes
        $model = new static($attributes);
        $model->exists = true;
        
        return $model;
    }
    
    /**
     * Create a new collection of models from an API response.
     *
     * @param array $response
     * @return \Illuminate\Support\Collection
     */
    protected function newCollectionFromApiResponse($response)
    {
        // Handle different API response formats
        $items = $this->extractItemsFromResponse($response);
        
        // Create a collection of models
        $models = new Collection();
        
        foreach ($items as $item) {
            $model = $this->newFromApiResponse($item);
            if ($model !== null) {
                $models->push($model);
            }
        }
        
        return $models;
    }
    
    /**
     * Extract items from an API response, handling different response formats.
     *
     * @param array $response
     * @return array
     */
    protected function extractItemsFromResponse($response)
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
        
        // If we can't find a collection, return an empty array
        return [];
    }
    
    /**
     * Handle API exceptions.
     *
     * @param \Exception $exception
     * @param string $message
     * @throws \Exception
     */
    protected function handleApiException(\Exception $exception, $message)
    {
        // Log the error if configured to do so
        if (config('api-model-relations.error_handling.log_errors', true)) {
            Log::error($message, [
                'exception' => $exception->getMessage(),
                'model' => get_class($this),
                'trace' => $exception->getTraceAsString(),
            ]);
        }
        
        // Throw the exception if configured to do so
        if (config('api-model-relations.error_handling.throw_exceptions', true)) {
            throw new ApiException($message . ': ' . $exception->getMessage(), 0, $exception);
        }
    }
    
    /**
     * Get a cache key for API operations.
     *
     * @param string $operation
     * @param mixed $id
     * @return string
     */
    protected function getApiCacheKey($operation, $id = null)
    {
        $prefix = config('api-model-relations.cache.prefix', 'api_model_');
        $class = str_replace('\\', '_', get_class($this));
        
        if ($id !== null) {
            return $prefix . $class . '_' . $operation . '_' . $id;
        }
        
        return $prefix . $class . '_' . $operation;
    }
    
    /**
     * Clear the cache for a specific model.
     *
     * @param mixed $id
     * @return void
     */
    protected function clearModelCache($id)
    {
        if (config('api-model-relations.cache.enabled', true)) {
            $cacheKey = $this->getApiCacheKey('find', $id);
            Cache::forget($cacheKey);
        }
    }
    
    /**
     * Clear the cache for all models of this type.
     *
     * @return void
     */
    protected function clearAllCache()
    {
        if (config('api-model-relations.cache.enabled', true)) {
            $cacheKey = $this->getApiCacheKey('all');
            Cache::forget($cacheKey);
        }
    }
    
    /**
     * Fire an API event.
     *
     * @param string $event
     * @param mixed $payload
     * @return void
     */
    protected function fireApiEvent($event, $payload = null)
    {
        $className = get_class($this);
        $eventName = "api.{$className}.{$event}";
        
        event($eventName, [$this, $payload]);
    }
}
