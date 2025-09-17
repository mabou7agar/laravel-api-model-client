<?php

namespace MTechStack\LaravelApiModelClient\Models;

use MTechStack\LaravelApiModelClient\Contracts\ApiModelInterface;
use MTechStack\LaravelApiModelClient\Traits\ApiModelAttributes;
use MTechStack\LaravelApiModelClient\Traits\ApiModelCaching;
use MTechStack\LaravelApiModelClient\Traits\ApiModelErrorHandling;
use MTechStack\LaravelApiModelClient\Traits\ApiModelEvents;
use MTechStack\LaravelApiModelClient\Traits\ApiModelInterfaceMethods;
use MTechStack\LaravelApiModelClient\Traits\ApiModelQueries;
use MTechStack\LaravelApiModelClient\Traits\HasApiRelationships;
use MTechStack\LaravelApiModelClient\Traits\LazyLoadsApiRelationships;
use MTechStack\LaravelApiModelClient\Traits\HybridDataSource;
use MTechStack\LaravelApiModelClient\Traits\SyncWithApi;
use MTechStack\LaravelApiModelClient\Traits\HasOpenApiSchema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class ApiModel extends Model implements ApiModelInterface
{
    use ApiModelAttributes;
    use ApiModelCaching;
    use ApiModelErrorHandling;
    use ApiModelEvents;
    use ApiModelInterfaceMethods;
    use ApiModelQueries;
    use HasApiRelationships;
    use LazyLoadsApiRelationships;
    use SyncWithApi;
    use HasOpenApiSchema;
    use HybridDataSource {
        // HybridDataSource methods take precedence over other traits
        HybridDataSource::find insteadof ApiModelQueries;
        HybridDataSource::all insteadof ApiModelQueries;
        HybridDataSource::save insteadof ApiModelQueries, ApiModelInterfaceMethods;
        HybridDataSource::delete insteadof ApiModelQueries, ApiModelInterfaceMethods;
        HybridDataSource::create insteadof ApiModelQueries;
        HybridDataSource::syncToApi insteadof SyncWithApi;
        
        // Resolve other method collisions
        ApiModelCaching::getCacheTtl insteadof ApiModelInterfaceMethods;
        ApiModelQueries::update insteadof ApiModelInterfaceMethods;
        
        // CRITICAL FIX: Ensure LazyLoadsApiRelationships __call doesn't interfere with newFromApiResponse
        LazyLoadsApiRelationships::__call as lazyLoadsCall;
        
        // Resolve OpenAPI trait method collisions
        HasOpenApiSchema::getApiEndpoint insteadof ApiModelInterfaceMethods;
        HasOpenApiSchema::__call as openApiCall;
        
        // Create aliases for overridden methods
        ApiModelQueries::find as findFromQueries;
        ApiModelQueries::all as allFromQueries;
        ApiModelQueries::save as saveFromQueries;
        ApiModelQueries::delete as deleteFromQueries;
        ApiModelQueries::update as updateFromQueries;
        ApiModelInterfaceMethods::getCacheTtl as getInterfaceCacheTtl;
        ApiModelInterfaceMethods::delete as deleteFromInterface;
        ApiModelInterfaceMethods::save as saveFromInterface;
        ApiModelInterfaceMethods::update as updateFromInterface;
        ApiModelInterfaceMethods::saveToApi as saveToApiFromTrait;
        ApiModelInterfaceMethods::getApiEndpoint as getApiEndpointFromInterface;
        SyncWithApi::syncToApi as syncToApiFromSyncTrait;
    }

    /**
     * Override HasEvents boot to avoid failing on custom constructors.
     */
    public static function bootHasEvents()
    {
        // Mirror trait behavior without invoking new static() unsafely
        static::observe(static::resolveObserveAttributes());
    }

    /**
     * Resolve the observer classes to be used for this model.
     *
     * @return array
     */
    public static function resolveObserveAttributes()
    {
        // Get the model class name
        $modelClass = static::class;
        
        // Check if there's a specific observer class defined
        $observerClass = $modelClass . 'Observer';
        
        // Return array of observer classes if they exist
        $observers = [];
        
        if (class_exists($observerClass)) {
            $observers[] = $observerClass;
        }
        
        // Check for observers defined in the model's $observables property
        $instance = static::makeSafeInstance();
        if (property_exists($instance, 'observables') && is_array($instance->observables)) {
            $observers = array_merge($observers, $instance->observables);
        }
        
        return $observers;
    }

    /**
     * Override HasEvents::observe to instantiate safely when subclass constructors
     * have required parameters (e.g., test anonymous models).
     */
    public static function observe($classes)
    {
        $instance = static::makeSafeInstance();

        foreach (Arr::wrap($classes) as $class) {
            $instance->registerObserver($class);
        }
    }

    /**
     * Create a model instance without requiring constructor arguments.
     */
    protected static function makeSafeInstance(): self
    {
        $ref = new \ReflectionClass(static::class);
        try {
            $ctor = $ref->getConstructor();
            if ($ctor && $ctor->getNumberOfRequiredParameters() > 0) {
                $args = [];
                foreach ($ctor->getParameters() as $param) {
                    if ($param->isDefaultValueAvailable()) {
                        $args[] = $param->getDefaultValue();
                        continue;
                    }
                    $type = $param->getType();
                    if ($type instanceof \ReflectionNamedType && $type->getName() === 'array') {
                        $args[] = [];
                    } else {
                        $args[] = null;
                    }
                }
                return $ref->newInstanceArgs($args);
            }
            return $ref->newInstance();
        } catch (\Throwable $e) {
            return $ref->newInstanceWithoutConstructor();
        }
    }


    /**
     * Create a new ApiModel instance.
     *
     * @param array $attributes
     * @return void
     */
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        // If the table is not explicitly set, use the default naming convention
        if (!isset($this->table)) {
            $this->setTable($this->getDefaultTableName());
        }
    }

    /**
     * Get the default table name for the model.
     *
     * @return string
     */
    protected function getDefaultTableName()
    {
        $className = class_basename($this);
        return strtolower(Str::plural($className));
    }

    /**
     * Get the API client instance.
     *
     * @return \MTechStack\LaravelApiModelClient\Contracts\ApiClientInterface
     */
    protected function getApiClient()
    {
        $client = App::make('api-client');
        // Ensure base URL is applied even if provider was initialized before config
        $base = config('api-model-client.client.base_url') ?? config('api-model-client.base_url');
        if ($base && method_exists($client, 'setBaseUrl')) {
            $client->setBaseUrl($base);
        }
        return $client;
    }

    /**
     * Determine if the model should always check the API even if found in database.
     *
     * @return bool
     */
    protected function shouldAlwaysCheckApi()
    {
        return false;
    }

    /**
     * Merge a database model with an API model.
     *
     * @param \Illuminate\Database\Eloquent\Model $dbModel
     * @param \Illuminate\Database\Eloquent\Model $apiModel
     * @return \Illuminate\Database\Eloquent\Model
     */
    protected function mergeModels($dbModel, $apiModel)
    {
        // By default, API data takes precedence over database data
        foreach ($apiModel->getAttributes() as $key => $value) {
            $dbModel->setAttribute($key, $value);
        }

        return $dbModel;
    }

    /**
     * Merge two collections of models based on primary key.
     *
     * @param \Illuminate\Database\Eloquent\Collection $dbModels
     * @param \Illuminate\Database\Eloquent\Collection $apiModels
     * @return \Illuminate\Database\Eloquent\Collection
     */
    protected function mergeCollections($dbModels, $apiModels)
    {
        $merged = $dbModels->keyBy($this->getKeyName());

        foreach ($apiModels as $apiModel) {
            $key = $apiModel->getKey();

            if ($merged->has($key)) {
                // If model exists in both collections, merge them
                $merged->put($key, $this->mergeModels($merged->get($key), $apiModel));
            } else {
                // If model only exists in API, add it to the collection
                $merged->put($key, $apiModel);
            }
        }

        return $merged->values();
    }

    /**
     * Enhanced __call method that handles OpenAPI dynamic methods and existing functionality
     */
    public function __call($method, $parameters)
    {
        // First, try OpenAPI-based methods
        if (method_exists($this, 'hasOpenApiSchema') && $this->hasOpenApiSchema()) {
            try {
                return $this->openApiCall($method, $parameters);
            } catch (\BadMethodCallException $e) {
                // Continue to other methods if OpenAPI doesn't handle it
            }
        }

        // Then try lazy loading relationships
        try {
            return $this->lazyLoadsCall($method, $parameters);
        } catch (\BadMethodCallException $e) {
            // Continue to parent if lazy loading doesn't handle it
        }

        // Fall back to parent __call
        return parent::__call($method, $parameters);
    }

    /**
     * Get the API endpoint for this model.
     *
     * This method is enhanced with OpenAPI support while maintaining backward compatibility.
     *
     * @return string
     */
    public function getApiEndpoint(): string
    {
        // The HasOpenApiSchema trait's getApiEndpoint method will be used due to insteadof resolution
        // This ensures OpenAPI-based endpoint resolution takes precedence
        
        // Fallback to original implementation if OpenAPI is not available
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
     * Create a new instance of the model.
     *
     * This override is resilient to subclasses that define a custom
     * constructor signature (e.g., anonymous test models that accept
     * a schema array). It attempts to instantiate the model even when
     * required constructor parameters exist, by supplying safe defaults
     * (empty arrays for array-typed params, null otherwise), or as a
     * last resort bypassing the constructor.
     */
    public function newInstance($attributes = [], $exists = false)
    {
        $class = static::class;
        $ref = new \ReflectionClass($class);

        try {
            $ctor = $ref->getConstructor();
            if ($ctor && $ctor->getNumberOfRequiredParameters() > 0) {
                $args = [];
                foreach ($ctor->getParameters() as $param) {
                    if ($param->isDefaultValueAvailable()) {
                        $args[] = $param->getDefaultValue();
                        continue;
                    }

                    $type = $param->getType();
                    if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                        // For class-typed params, pass null
                        $args[] = null;
                    } elseif ($type instanceof \ReflectionNamedType && $type->getName() === 'array') {
                        $args[] = [];
                    } else {
                        $args[] = null;
                    }
                }
                $model = $ref->newInstanceArgs($args);
            } else {
                $model = $ref->newInstance();
            }
        } catch (\Throwable $e) {
            // Fallback: bypass constructor if instantiation failed
            $model = $ref->newInstanceWithoutConstructor();
        }

        // Emulate base Model::newInstance behavior
        $model->exists = $exists;
        $model->setConnection($this->getConnectionName());
        $model->setTable($this->getTable());
        $model->setPerPage($this->getPerPage());

        // Set raw attributes without firing casts/mutators
        $model->forceFill((array) $attributes);

        return $model;
    }

    /**
     * Determine if the model should merge API data with local database data.
     *
     * @return bool
     */
    public function shouldMergeWithDatabase(): bool
    {
        return true;
    }

    /**
     * Get all models from the API.
     *
     * @return \Illuminate\Support\Collection
     */
    public static function allFromApi()
    {
        $instance = new static;
        $apiClient = $instance->getApiClient();
        $response = $apiClient->get($instance->getApiEndpoint());
        if (empty($response)) {
            // Fallback to Laravel HTTP client (compatible with Http::fake in tests)
            $base = config('api-model-client.client.base_url') ?? config('api-model-client.base_url') ?? 'https://demo.bagisto.com/bagisto-api-demo-common';
            $url = rtrim($base, '/') . '/' . ltrim($instance->getApiEndpoint(), '/');
            $response = Http::get($url)->json() ?? [];
        }

        // Normalize items using helper to handle nested/flat responses
        $items = $instance->extractItemsFromResponse($response ?? []);

        return collect($items)->map(function ($item) {
            return new static($item ?? []);
        });
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
        $apiClient = $instance->getApiClient();

        try {
            $response = $apiClient->get($instance->getApiEndpoint() . '/' . $id);
            if (empty($response)) {
                $base = config('api-model-client.client.base_url') ?? config('api-model-client.base_url') ?? 'https://demo.bagisto.com/bagisto-api-demo-common';
                $url = rtrim($base, '/') . '/' . ltrim($instance->getApiEndpoint(), '/') . '/' . $id;
                $response = Http::get($url)->json() ?? [];
            }
            if (empty($response)) {
                return null;
            }

            // Handle nested data structure for single items
            $data = $response;
            if (is_array($response) && isset($response['data'])) {
                $nested = $response['data'];
                if (is_array($nested) && !isset($nested[0])) {
                    $data = $nested;
                }
            }

            if (!is_array($data) || empty($data)) {
                return null;
            }

            return new static($data);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Save the model to the API.
     * Compatible with both public interface and HybridDataSource trait.
     *
     * @param array $options
     * @return bool
     */
    public function saveToApi(array $options = [])
    {
        $apiClient = $this->getApiClient();
        $data = $this->getAttributes();

        try {
            if ($this->exists) {
                $apiClient->put($this->getApiEndpoint() . '/' . $this->getKey(), $data);
            } else {
                $response = $apiClient->post($this->getApiEndpoint(), $data);
                $this->forceFill($response);
            }

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Delete the model from the API.
     *
     * @return bool
     */
    public function deleteFromApi()
    {
        if (!$this->exists) {
            return false;
        }

        $apiClient = $this->getApiClient();

        try {
            $apiClient->delete($this->getApiEndpoint() . '/' . $this->getKey());
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Extract items from an API response, handling different response formats.
     * This method is used by both the model and query builder for consistent data parsing.
     *
     * @param array $response
     * @return array
     */
    public function extractItemsFromResponse($response)
    {
        // Handle empty response
        if (empty($response)) {
            return [];
        }

        // If response has a 'data' key (nested structure like Bagisto API)
        if (isset($response['data'])) {
            $data = $response['data'];
            
            // If data is an array of items, return it
            if (is_array($data) && isset($data[0])) {
                return $data;
            }
            
            // If data is a single item, wrap it in an array
            if (is_array($data) && !isset($data[0])) {
                return [$data];
            }
        }

        // If response is already an array of items (flat structure)
        if (isset($response[0])) {
            return $response;
        }

        // If response is a single item, wrap it in an array
        if (is_array($response) && !empty($response)) {
            return [$response];
        }

        return [];
    }

    /**
     * Create a new model instance from an API response.
     * This method is implemented in the ApiModelInterfaceMethods trait.
     *
     * @param array $response
     * @return static|null
     */
    public function newFromApiResponse($response = [])
    {
        // This method is implemented in the ApiModelInterfaceMethods trait
        // The trait method will handle the actual logic
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
     * Override Laravel's all() method to use API instead of database.
     * This provides a seamless experience for users expecting standard Eloquent behavior.
     *
     * @param array $columns
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function all($columns = ['*'])
    {
        // Redirect to allFromApi() method to use API instead of database
        return static::allFromApi();
    }

    /**
     * Override initializeTraits to handle missing trait initializers gracefully.
     * This prevents the "Undefined array key" and "foreach() argument must be of type array|object" warnings.
     */
    protected function initializeTraits()
    {
        // Check if trait initializers exist for this class before trying to iterate
        if (isset(static::$traitInitializers[static::class]) && 
            is_array(static::$traitInitializers[static::class])) {
            foreach (static::$traitInitializers[static::class] as $method) {
                if (method_exists($this, $method)) {
                    $this->{$method}();
                }
            }
        }
    }

    /**
     * Override boot method to ensure proper trait initialization for API models.
     */
    protected static function boot()
    {
        // Initialize trait initializers array if not set
        if (!isset(static::$traitInitializers[static::class])) {
            static::$traitInitializers[static::class] = [];
        }
        
        // Call parent boot to handle standard Laravel model initialization
        parent::boot();
    }



    /**
     * Create a new record via API.
     *
     * @param array $options
     * @return bool
     */
    protected function createViaApi(array $options = []): bool
    {
        try {
            $client = app('api-client');
            $response = $client->post($this->getApiEndpoint(), $this->getAttributes());
            
            if ($response && isset($response['data'])) {
                $this->fill($response['data']);
                $this->exists = true;
                $this->wasRecentlyCreated = true;
                return true;
            }
            
            return false;
        } catch (\Exception $e) {
            \Log::error("API create failed for " . static::class, [
                'error' => $e->getMessage(),
                'attributes' => $this->getAttributes()
            ]);
            return false;
        }
    }

    /**
     * Update an existing record via API.
     *
     * @param array $options
     * @return bool
     */
    protected function updateViaApi(array $options = []): bool
    {
        try {
            $client = app('api-client');
            $endpoint = $this->getApiEndpoint() . '/' . $this->getKey();
            $response = $client->put($endpoint, $this->getAttributes());
            
            if ($response && isset($response['data'])) {
                $this->fill($response['data']);
                return true;
            }
            
            return false;
        } catch (\Exception $e) {
            \Log::error("API update failed for " . static::class, [
                'error' => $e->getMessage(),
                'attributes' => $this->getAttributes()
            ]);
            return false;
        }
    }

    /**
     * Delete record via API.
     *
     * @return bool
     */
    protected function deleteFromApiOnly(): bool
    {
        try {
            $client = app('api-client');
            $endpoint = $this->getApiEndpoint() . '/' . $this->getKey();
            $response = $client->delete($endpoint);
            
            return $response !== false;
        } catch (\Exception $e) {
            \Log::error("API delete failed for " . static::class, [
                'error' => $e->getMessage(),
                'id' => $this->getKey()
            ]);
            return false;
        }
    }

    /**
     * Delete using hybrid approach.
     *
     * @return bool|null
     */
    protected function deleteHybrid(): bool
    {
        $dbDeleted = false;
        $apiDeleted = false;

        // Try database first
        try {
            $dbDeleted = parent::delete();
        } catch (\Exception $e) {
            \Log::warning("Database delete failed for " . static::class, [
                'error' => $e->getMessage()
            ]);
        }

        // Try API as fallback or additional sync
        try {
            $apiDeleted = $this->deleteFromApiOnly();
        } catch (\Exception $e) {
            \Log::warning("API delete failed for " . static::class, [
                'error' => $e->getMessage()
            ]);
        }

        return $dbDeleted || $apiDeleted;
    }

    /**
     * Delete using API first approach.
     *
     * @return bool
     */
    protected function deleteApiFirst(): bool
    {
        try {
            $apiDeleted = $this->deleteFromApiOnly();
            
            if ($apiDeleted) {
                // Sync to database
                parent::delete();
                return true;
            }
        } catch (\Exception $e) {
            \Log::warning("API first delete failed for " . static::class, [
                'error' => $e->getMessage()
            ]);
        }

        // Fallback to database
        return parent::delete();
    }

    /**
     * Delete using dual sync approach.
     *
     * @return bool
     */
    protected function deleteDualSync(): bool
    {
        $dbDeleted = false;
        $apiDeleted = false;

        // Delete from both sources
        try {
            $dbDeleted = parent::delete();
        } catch (\Exception $e) {
            \Log::error("Database delete failed in dual sync for " . static::class, [
                'error' => $e->getMessage()
            ]);
        }

        try {
            $apiDeleted = $this->deleteFromApiOnly();
        } catch (\Exception $e) {
            \Log::error("API delete failed in dual sync for " . static::class, [
                'error' => $e->getMessage()
            ]);
        }

        return $dbDeleted && $apiDeleted;
    }

    /**
     * Create using API only.
     *
     * @param array $attributes
     * @return static
     */
    protected static function createInApiOnly(array $attributes = [])
    {
        $instance = new static($attributes);
        $instance->saveToApi();
        return $instance;
    }

    /**
     * Create using hybrid approach.
     *
     * @param array $attributes
     * @return static
     */
    protected static function createHybrid(array $attributes = [])
    {
        // Try database first
        try {
            return parent::create($attributes);
        } catch (\Exception $e) {
            \Log::warning("Database create failed for " . static::class, [
                'error' => $e->getMessage()
            ]);
        }

        // Fallback to API
        return static::createInApiOnly($attributes);
    }

    /**
     * Create using API first approach.
     *
     * @param array $attributes
     * @return static
     */
    protected static function createApiFirst(array $attributes = [])
    {
        $instance = static::createInApiOnly($attributes);
        
        if ($instance->exists) {
            // Sync to database
            try {
                $instance->syncToDatabase();
            } catch (\Exception $e) {
                \Log::warning("Database sync failed after API create for " . static::class, [
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        return $instance;
    }

    /**
     * Create using dual sync approach.
     *
     * @param array $attributes
     * @return static
     */
    protected static function createDualSync(array $attributes = [])
    {
        $dbModel = null;
        $apiModel = null;

        // Create in both sources
        try {
            $dbModel = parent::create($attributes);
        } catch (\Exception $e) {
            \Log::error("Database create failed in dual sync for " . static::class, [
                'error' => $e->getMessage()
            ]);
        }

        try {
            $apiModel = static::createInApiOnly($attributes);
        } catch (\Exception $e) {
            \Log::error("API create failed in dual sync for " . static::class, [
                'error' => $e->getMessage()
            ]);
        }

        // Return the API model as it's typically more current
        return $apiModel ?: $dbModel;
    }
}
