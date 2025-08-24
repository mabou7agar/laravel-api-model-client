<?php

namespace MTechStack\LaravelApiModelClient\Traits;

use MTechStack\LaravelApiModelClient\Jobs\SyncModelToApi;
use MTechStack\LaravelApiModelClient\Factories\ApiClientFactory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\App;

trait ApiModelInterfaceMethods
{
    /**
     * Store the last API error message.
     *
     * @var string|null
     */
    protected $lastApiError = null;

    /**
     * Get the API endpoint for this model.
     * 
     * This method should be overridden in child classes.
     *
     * @return string
     */
    public function getApiEndpoint(): string
    {
        if (property_exists($this, 'apiEndpoint')) {
            return $this->apiEndpoint;
        }
        
        throw new \RuntimeException('API endpoint not defined for model ' . get_class($this));
    }

    /**
     * Get the primary key for API requests.
     *
     * @return string
     */
    public function getApiKeyName(): string
    {
        return $this->getKeyName();
    }

    /**
     * Determine if the model should merge API data with local database data.
     *
     * @return bool
     */
    public function shouldMergeWithDatabase(): bool
    {
        // Disable API sync in testing environment by default
        if (App::environment('testing') && !config('api_model.sync_in_testing', false)) {
            return false;
        }
        
        return true;
    }

    /**
     * Determine if API operations should be queued.
     *
     * @return bool
     */
    public function shouldQueueApiOperations(): bool
    {
        return config('api_model.queue_operations', true);
    }

    /**
     * Get the number of retry attempts for API operations.
     *
     * @return int
     */
    public function getApiRetryAttempts(): int
    {
        return config('api_model.retry_attempts', 3);
    }

    /**
     * Get the cache TTL for API responses.
     *
     * @return int
     */
    public function getCacheTtl(): int
    {
        return config('api_model.cache_ttl', 3600); // Default: 1 hour
    }

    /**
     * Get all models from the API.
     *
     * @return \Illuminate\Support\Collection
     */
    public static function allFromApi()
    {
        $instance = new static;
        $cacheKey = $instance->getApiCacheKey('all');
        $cacheTtl = $instance->getCacheTtl();
        
        // Check if we have a cached response
        if (config('api_model.cache_enabled', true) && $cacheTtl > 0) {
            $cachedData = Cache::get($cacheKey);
            if ($cachedData !== null) {
                Log::info("Using cached data for all models", [
                    'model' => get_class($instance)
                ]);
                return $instance->newCollectionFromApiResponse($cachedData);
            }
        }
        
        try {
            // Fire before event
            $instance->fireApiEvent('retrievingAll');
            
            Log::info("Fetching all models from API", [
                'model' => get_class($instance),
                'endpoint' => $instance->getApiEndpoint()
            ]);
            
            $apiClient = $instance->getApiClient();
            $response = $apiClient->get($instance->getApiEndpoint());
            
            // Fire after event
            $instance->fireApiEvent('retrievedAll', $response);
            
            Log::info("Successfully fetched models from API", [
                'model' => get_class($instance),
                'count' => is_array($response) ? count($response) : 0
            ]);
            
            // Cache the response if caching is enabled
            if (config('api_model.cache_enabled', true) && $cacheTtl > 0) {
                Cache::put($cacheKey, $response, $cacheTtl);
            }
            
            return $instance->newCollectionFromApiResponse($response);
        } catch (\Exception $e) {
            Log::error("Failed to fetch models from API: " . $e->getMessage(), [
                'model' => get_class($instance),
                'endpoint' => $instance->getApiEndpoint(),
                'exception' => get_class($e)
            ]);
            
            $instance->lastApiError = $e->getMessage();
            $instance->fireApiEvent('retrieveAllFailed', $e);
            return new Collection();
        }
    }

    /**
     * Get the model from the API by its primary key.
     *
     * @param mixed $id
     * @return static|null
     */
    public static function findFromApi($id)
    {
        $instance = new static;
        $cacheKey = $instance->getApiCacheKey('find', $id);
        $cacheTtl = $instance->getCacheTtl();
        
        // Check if we have a cached response
        if (config('api_model.cache_enabled', true) && $cacheTtl > 0) {
            $cachedData = Cache::get($cacheKey);
            if ($cachedData !== null) {
                Log::info("Using cached data for model", [
                    'model' => get_class($instance),
                    'id' => $id
                ]);
                return $instance->newFromApiResponse($cachedData);
            }
        }
        
        try {
            // Fire before event
            $instance->fireApiEvent('finding', $id);
            
            Log::info("Finding model from API", [
                'model' => get_class($instance),
                'id' => $id,
                'endpoint' => $instance->getApiEndpoint() . '/' . $id
            ]);
            
            $apiClient = $instance->getApiClient();
            $response = $apiClient->get($instance->getApiEndpoint() . '/' . $id);
            
            // Fire after event
            $instance->fireApiEvent('found', $response);
            
            Log::info("Successfully found model from API", [
                'model' => get_class($instance),
                'id' => $id
            ]);
            
            // Cache the response if caching is enabled
            if (config('api_model.cache_enabled', true) && $cacheTtl > 0) {
                Cache::put($cacheKey, $response, $cacheTtl);
            }
            
            return $instance->newFromApiResponse($response);
        } catch (\Exception $e) {
            Log::error("Failed to find model from API: " . $e->getMessage(), [
                'model' => get_class($instance),
                'id' => $id,
                'endpoint' => $instance->getApiEndpoint() . '/' . $id,
                'exception' => get_class($e)
            ]);
            
            $instance->lastApiError = $e->getMessage();
            $instance->fireApiEvent('findFailed', ['id' => $id, 'error' => $e]);
            return null;
        }
    }

    /**
     * Save the model to the API with retry logic.
     *
     * @param int|null $retries Override the default retry count
     * @return bool
     */
    public function saveToApi(?int $retries = null)
    {
        $retries = $retries ?? $this->getApiRetryAttempts();
        $apiClient = $this->getApiClient();
        $data = $this->getAttributes();
        $attempt = 0;
        
        // If this is a create operation, use create-specific attributes
        if (!$this->exists) {
            $data = array_intersect_key($data, array_flip($this->getCreateApiAttributes()));
            // Fire before event
            $this->fireApiEvent('creating', $data);
        } else {
            // If this is an update operation, use update-specific attributes
            $data = array_intersect_key($data, array_flip($this->getUpdateApiAttributes()));
            // Fire before event
            $this->fireApiEvent('updating', $data);
        }
        
        while ($attempt < $retries) {
            try {
                Log::info("Attempting to save model to API (Attempt " . ($attempt + 1) . ")", [
                    'model' => get_class($this),
                    'id' => $this->getKey(),
                    'operation' => $this->exists ? 'update' : 'create',
                    'endpoint' => $this->exists ? 
                        $this->getApiEndpoint() . '/' . $this->getKey() : 
                        $this->getApiEndpoint()
                ]);
                
                if ($this->exists) {
                    $response = $apiClient->put($this->getApiEndpoint() . '/' . $this->getKey(), $data);
                    // Fire after event
                    $this->fireApiEvent('updated', $response);
                } else {
                    $response = $apiClient->post($this->getApiEndpoint(), $data);
                    $this->forceFill($response);
                    // Fire after event
                    $this->fireApiEvent('created', $response);
                }
                
                Log::info("Successfully saved model to API", [
                    'model' => get_class($this),
                    'id' => $this->getKey(),
                    'operation' => $this->exists ? 'update' : 'create'
                ]);
                
                // Clear cache for this model
                $this->clearModelCache($this->getKey());
                
                $this->lastApiError = null;
                return true;
            } catch (\Exception $e) {
                $attempt++;
                
                Log::warning("Failed to save model to API (Attempt {$attempt}/{$retries}): " . $e->getMessage(), [
                    'model' => get_class($this),
                    'id' => $this->getKey(),
                    'operation' => $this->exists ? 'update' : 'create',
                    'exception' => get_class($e)
                ]);
                
                $this->lastApiError = $e->getMessage();
                
                if ($attempt >= $retries) {
                    Log::error("Failed to save model to API after {$retries} attempts", [
                        'model' => get_class($this),
                        'id' => $this->getKey(),
                        'operation' => $this->exists ? 'update' : 'create',
                        'last_error' => $this->lastApiError
                    ]);
                    
                    // Fire failed event
                    $this->fireApiEvent($this->exists ? 'updateFailed' : 'createFailed', [
                        'error' => $e,
                        'data' => $data
                    ]);
                    
                    return false;
                }
                
                // Wait before retrying (exponential backoff)
                sleep(pow(2, $attempt));
            }
        }
        
        return false;
    }

    /**
     * Delete the model from the API with retry logic.
     *
     * @param int|null $retries Override the default retry count
     * @return bool
     */
    public function deleteFromApi(?int $retries = null)
    {
        if (!$this->exists) {
            return false;
        }
        
        $retries = $retries ?? $this->getApiRetryAttempts();
        $apiClient = $this->getApiClient();
        $attempt = 0;
        
        // Fire before event
        $this->fireApiEvent('deleting', $this->getKey());
        
        while ($attempt < $retries) {
            try {
                Log::info("Attempting to delete model from API (Attempt " . ($attempt + 1) . ")", [
                    'model' => get_class($this),
                    'id' => $this->getKey(),
                    'endpoint' => $this->getApiEndpoint() . '/' . $this->getKey()
                ]);
                
                $apiClient->delete($this->getApiEndpoint() . '/' . $this->getKey());
                
                Log::info("Successfully deleted model from API", [
                    'model' => get_class($this),
                    'id' => $this->getKey()
                ]);
                
                // Fire after event
                $this->fireApiEvent('deleted', $this->getKey());
                
                // Clear cache for this model
                $this->clearModelCache($this->getKey());
                
                $this->lastApiError = null;
                return true;
            } catch (\Exception $e) {
                $attempt++;
                
                Log::warning("Failed to delete model from API (Attempt {$attempt}/{$retries}): " . $e->getMessage(), [
                    'model' => get_class($this),
                    'id' => $this->getKey(),
                    'exception' => get_class($e)
                ]);
                
                $this->lastApiError = $e->getMessage();
                
                if ($attempt >= $retries) {
                    Log::error("Failed to delete model from API after {$retries} attempts", [
                        'model' => get_class($this),
                        'id' => $this->getKey(),
                        'last_error' => $this->lastApiError
                    ]);
                    
                    // Fire failed event
                    $this->fireApiEvent('deleteFailed', [
                        'id' => $this->getKey(),
                        'error' => $e
                    ]);
                    
                    return false;
                }
                
                // Wait before retrying (exponential backoff)
                sleep(pow(2, $attempt));
            }
        }
        
        return false;
    }

    /**
     * Get attributes that should be synchronized with the API.
     * 
     * @return array
     */
    public function getApiSyncAttributes()
    {
        // By default, all fillable attributes are synced with API
        // Override this method in child classes to customize
        return $this->fillable;
    }

    /**
     * Get attributes that should be synchronized with the API during create operations.
     * 
     * @return array
     */
    public function getCreateApiAttributes()
    {
        // By default, use the general API sync attributes
        // Override this method in child classes to customize
        return $this->getApiSyncAttributes();
    }

    /**
     * Get attributes that should be synchronized with the API during update operations.
     * 
     * @return array
     */
    public function getUpdateApiAttributes()
    {
        // By default, use the general API sync attributes
        // Override this method in child classes to customize
        return $this->getApiSyncAttributes();
    }

    /**
     * Get attributes that should be stored only in the database.
     * 
     * @return array
     */
    public function getDbOnlyAttributes()
    {
        // By default, no attributes are DB-only
        // Override this method in child classes to customize
        return [];
    }

    /**
     * Get the last API error message.
     *
     * @return string|null
     */
    public function getLastApiError()
    {
        return $this->lastApiError;
    }

    /**
     * Override the default save method to handle both API and database within a transaction.
     *
     * @param array $options
     * @return bool
     */
    public function save(array $options = [])
    {
        $dbOnly = $this->getDbOnlyAttributes();
        $apiSync = $this->getApiSyncAttributes();
        
        // Extract API attributes for syncing
        $apiAttributes = [];
        foreach ($apiSync as $attribute) {
            if (array_key_exists($attribute, $this->attributes) && !in_array($attribute, $dbOnly)) {
                $apiAttributes[$attribute] = $this->attributes[$attribute];
            }
        }
        
        // Begin transaction
        DB::beginTransaction();
        
        try {
            // Save to database first
            $dbSaved = parent::save($options);
            
            if (!$dbSaved) {
                // If DB save failed, roll back and return false
                DB::rollBack();
                
                Log::error("Failed to save model to database", [
                    'model' => get_class($this),
                    'id' => $this->getKey()
                ]);
                
                return false;
            }
            
            // If database save was successful and we should sync with API
            if ($this->shouldMergeWithDatabase() && !empty($apiAttributes)) {
                // Store current attributes
                $currentAttributes = $this->attributes;
                
                // Set only API attributes for the API save
                $this->attributes = array_intersect_key($currentAttributes, array_flip($apiSync));
                
                // Remove DB-only attributes from API sync
                foreach ($dbOnly as $attribute) {
                    if (isset($this->attributes[$attribute])) {
                        unset($this->attributes[$attribute]);
                    }
                }
                
                // Determine if we should queue the API operation
                if ($this->shouldQueueApiOperations()) {
                    // Queue the API sync job
                    dispatch(new SyncModelToApi($this, 'save'));
                    $apiSaved = true; // Assume success since it's queued
                } else {
                    // Save to API directly
                    $apiSaved = $this->saveToApi();
                }
                
                // Restore all attributes
                $this->attributes = $currentAttributes;
                
                if (!$apiSaved) {
                    // If API save failed, roll back DB changes and return false
                    DB::rollBack();
                    
                    Log::error("Failed to save model to API, rolling back database changes", [
                        'model' => get_class($this),
                        'id' => $this->getKey(),
                        'api_error' => $this->getLastApiError()
                    ]);
                    
                    return false;
                }
            }
            
            // If we got here, everything succeeded
            DB::commit();
            
            Log::info("Successfully saved model to database" . 
                ($this->shouldMergeWithDatabase() ? " and API" : ""), [
                'model' => get_class($this),
                'id' => $this->getKey(),
                'queued' => $this->shouldQueueApiOperations() && $this->shouldMergeWithDatabase()
            ]);
            
            return true;
        } catch (\Exception $e) {
            // If any exception occurred, roll back and rethrow
            DB::rollBack();
            
            Log::error("Exception during model save: " . $e->getMessage(), [
                'model' => get_class($this),
                'id' => $this->getKey(),
                'exception' => get_class($e),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e;
        }
    }

    /**
     * Override the default update method to handle both API and database within a transaction.
     *
     * @param array $attributes
     * @param array $options
     * @return bool
     */
    public function update(array $attributes = [], array $options = [])
    {
        $dbOnly = $this->getDbOnlyAttributes();
        $apiSync = $this->getApiSyncAttributes();
        
        // Extract API attributes for syncing
        $apiAttributes = [];
        foreach ($attributes as $key => $value) {
            if (in_array($key, $apiSync) && !in_array($key, $dbOnly)) {
                $apiAttributes[$key] = $value;
            }
        }
        
        // Begin transaction
        DB::beginTransaction();
        
        try {
            // Update database
            $dbUpdated = parent::update($attributes, $options);
            
            if (!$dbUpdated) {
                // If DB update failed, roll back and return false
                DB::rollBack();
                
                Log::error("Failed to update model in database", [
                    'model' => get_class($this),
                    'id' => $this->getKey()
                ]);
                
                return false;
            }
            
            // If database update was successful and we should sync with API
            if ($this->shouldMergeWithDatabase() && !empty($apiAttributes)) {
                // Store current attributes
                $currentAttributes = $this->attributes;
                
                // Set only API attributes for the API update
                foreach ($apiAttributes as $key => $value) {
                    $this->setAttribute($key, $value);
                }
                
                // Determine if we should queue the API operation
                if ($this->shouldQueueApiOperations()) {
                    // Queue the API sync job
                    dispatch(new SyncModelToApi($this, 'update'));
                    $apiUpdated = true; // Assume success since it's queued
                } else {
                    // Save to API directly
                    $apiUpdated = $this->saveToApi();
                }
                
                // Restore all attributes
                $this->attributes = $currentAttributes;
                
                if (!$apiUpdated) {
                    // If API update failed, roll back DB changes and return false
                    DB::rollBack();
                    
                    Log::error("Failed to update model in API, rolling back database changes", [
                        'model' => get_class($this),
                        'id' => $this->getKey(),
                        'api_error' => $this->getLastApiError()
                    ]);
                    
                    return false;
                }
            }
            
            // If we got here, everything succeeded
            DB::commit();
            
            Log::info("Successfully updated model in database" . 
                ($this->shouldMergeWithDatabase() ? " and API" : ""), [
                'model' => get_class($this),
                'id' => $this->getKey(),
                'queued' => $this->shouldQueueApiOperations() && $this->shouldMergeWithDatabase()
            ]);
            
            return true;
        } catch (\Exception $e) {
            // If any exception occurred, roll back and rethrow
            DB::rollBack();
            
            Log::error("Exception during model update: " . $e->getMessage(), [
                'model' => get_class($this),
                'id' => $this->getKey(),
                'exception' => get_class($e),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e;
        }
    }

    /**
     * Override the default delete method to handle both API and database within a transaction.
     *
     * @return bool|null
     */
    public function delete()
    {
        if (!$this->exists) {
            return false;
        }
        
        // Begin transaction
        DB::beginTransaction();
        
        try {
            // Delete from database first
            $dbDeleted = parent::delete();
            
            if (!$dbDeleted) {
                // If DB delete failed, roll back and return false
                DB::rollBack();
                
                Log::error("Failed to delete model from database", [
                    'model' => get_class($this),
                    'id' => $this->getKey()
                ]);
                
                return false;
            }
            
            // If database delete was successful and we should sync with API
            if ($this->shouldMergeWithDatabase()) {
                // Determine if we should queue the API operation
                if ($this->shouldQueueApiOperations()) {
                    // Queue the API sync job
                    dispatch(new SyncModelToApi($this, 'delete'));
                    $apiDeleted = true; // Assume success since it's queued
                } else {
                    // Delete from API directly
                    $apiDeleted = $this->deleteFromApi();
                }
                
                if (!$apiDeleted) {
                    // If API delete failed, roll back DB changes and return false
                    DB::rollBack();
                    
                    Log::error("Failed to delete model from API, rolling back database changes", [
                        'model' => get_class($this),
                        'id' => $this->getKey(),
                        'api_error' => $this->getLastApiError()
                    ]);
                    
                    return false;
                }
            }
            
            // If we got here, everything succeeded
            DB::commit();
            
            Log::info("Successfully deleted model from database" . 
                ($this->shouldMergeWithDatabase() ? " and API" : ""), [
                'model' => get_class($this),
                'id' => $this->getKey(),
                'queued' => $this->shouldQueueApiOperations() && $this->shouldMergeWithDatabase()
            ]);
            
            return true;
        } catch (\Exception $e) {
            // If any exception occurred, roll back and rethrow
            DB::rollBack();
            
            Log::error("Exception during model delete: " . $e->getMessage(), [
                'model' => get_class($this),
                'id' => $this->getKey(),
                'exception' => get_class($e),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e;
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
        
        // Map API fields to model attributes if method exists
        if (method_exists($this, 'mapApiResponseToAttributes')) {
            $attributes = $this->mapApiResponseToAttributes($response);
        } else {
            $attributes = $response;
        }
        
        // Cast attributes to their proper types if method exists
        if (method_exists($this, 'castApiResponseData')) {
            $attributes = $this->castApiResponseData($attributes);
        }
        
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
        $items = $this->extractItemsFromResponse($response);
        
        return collect($items)->map(function ($item) {
            return $this->newFromApiResponse($item);
        });
    }

    /**
     * Extract items from an API response, handling different response formats.
     *
     * @param array $response
     * @return array
     */
    protected function extractItemsFromResponse($response)
    {
        // Handle common API response formats
        if (isset($response['data']) && is_array($response['data'])) {
            return $response['data'];
        }
        
        if (isset($response['items']) && is_array($response['items'])) {
            return $response['items'];
        }
        
        if (isset($response['results']) && is_array($response['results'])) {
            return $response['results'];
        }
        
        // If no recognized format, assume the response is the items array directly
        return is_array($response) ? $response : [];
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
        $class = get_class($this);
        $endpoint = $this->getApiEndpoint();
        
        if ($id !== null) {
            return "api_model:{$class}:{$endpoint}:{$operation}:{$id}";
        }
        
        return "api_model:{$class}:{$endpoint}:{$operation}";
    }

    /**
     * Clear the cache for a specific model.
     *
     * @param mixed $id
     * @return void
     */
    public function clearModelCache($id)
    {
        if (config('api_model.cache_enabled', true)) {
            Cache::forget($this->getApiCacheKey('find', $id));
        }
    }

    /**
     * Clear the cache for all models of this type.
     *
     * @return void
     */
    public function clearAllCache()
    {
        if (config('api_model.cache_enabled', true)) {
            Cache::forget($this->getApiCacheKey('all'));
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
        $eventName = 'api.' . $event . '.' . strtolower(class_basename($this));
        event($eventName, [$this, $payload]);
    }

    /**
     * Get the API client instance.
     *
     * @return \MTechStack\LaravelApiModelClient\Contracts\ApiClientInterface
     */
    protected function getApiClient()
    {
        // Use the ApiClientFactory to get the appropriate client based on configuration
        $baseUrl = property_exists($this, 'apiBaseUrl') ? $this->apiBaseUrl : '';
        $protocol = property_exists($this, 'apiProtocol') ? $this->apiProtocol : null;
        
        return ApiClientFactory::create($baseUrl, null, $protocol);
    }
}
