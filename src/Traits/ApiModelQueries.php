<?php

namespace MTechStack\LaravelApiModelClient\Traits;

use MTechStack\LaravelApiModelClient\Query\ApiQueryBuilder;
use MTechStack\LaravelApiModelClient\Helpers\EndpointParameterResolver;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;

/**
 * Trait for handling API model queries
 */
trait ApiModelQueries
{
    /**
     * Create a new API query builder for the model.
     *
     * @return \MTechStack\LaravelApiModelClient\Query\ApiQueryBuilder
     */
    public function newApiQuery()
    {
        return new ApiQueryBuilder($this);
    }

    /**
     * Get a new query builder instance for the model.
     *
     * @return \MTechStack\LaravelApiModelClient\Query\ApiQueryBuilder|\Illuminate\Database\Eloquent\Builder
     */
    public function newQuery()
    {
        // Check if we're being called from a morphTo relationship context
        // by examining the call stack
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);
        foreach ($trace as $frame) {
            if (isset($frame['function']) &&
                (str_contains($frame['function'], 'newMorphTo') ||
                 str_contains($frame['function'], 'morphTo') ||
                 (isset($frame['class']) && str_contains($frame['class'], 'MorphTo')))) {
                // Return standard Eloquent builder for morphTo relationships
                return parent::newQuery();
            }
        }

        if ($this->isApiModel()) {
            return $this->newApiQuery();
        }

        return parent::newQuery();
    }

    /**
     * Create a new Eloquent Collection instance.
     *
     * @param  array  $models
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function newCollection(array $models = [])
    {
        return new Collection($models);
    }

    /**
     * Resolve endpoint parameters intelligently
     *
     * @param string $endpoint
     * @param array $queryParams
     * @param array $additionalParams
     * @return string
     */
    public function resolveEndpointParameters(string $endpoint = null, array $queryParams = [], array $additionalParams = []): string
    {
        $endpoint = $endpoint ?? $this->getApiEndpoint();

        return EndpointParameterResolver::resolve(
            $endpoint,
            $this,
            $queryParams,
            $additionalParams
        );
    }

    /**
     * Build endpoint with parameter validation
     *
     * @param string $endpoint
     * @param array $queryParams
     * @param array $additionalParams
     * @param bool $throwOnUnresolved
     * @return string
     */
    public function buildEndpointWithParams(
        string $endpoint = null,
        array $queryParams = [],
        array $additionalParams = [],
        bool $throwOnUnresolved = false
    ): string {
        $endpoint = $endpoint ?? $this->getApiEndpoint();

        return EndpointParameterResolver::buildEndpoint(
            $endpoint,
            $this,
            $queryParams,
            $additionalParams,
            $throwOnUnresolved
        );
    }

    /**
     * Determine if this is an API model.
     *
     * @return bool
     */
    public function isApiModel()
    {
        return property_exists($this, 'apiEndpoint') && $this->apiEndpoint !== null;
    }

    /**
     * Find a model by its primary key.
     *
     * @param  mixed  $id
     * @param  array  $columns
     * @return \Illuminate\Database\Eloquent\Model|static|null
     */
    public static function find($id, $columns = ['*'])
    {
        $instance = new static;
        if (!$instance->isApiModel()) {
            return parent::find($id, $columns);
        }

        // Generate cache key
        $cacheKey = $instance->generateCacheKey('find', $id);

        // Use cache if enabled
        return $instance->cacheRemember($cacheKey, function () use ($id, $columns, $instance) {
            // Fire the retrieving event
            if ($instance->fireApiModelEvent('retrieving') === false) {
                return null;
            }

            // Get the API client
            $apiClient = $instance->getApiClient();

            // Build the endpoint with intelligent parameter resolution
            $baseEndpoint = $instance->getApiEndpoint();

            // Use EndpointParameterResolver to handle parameter substitution
            $resolvedEndpoint = EndpointParameterResolver::resolve(
                $baseEndpoint,
                $instance,
                ['id' => $id], // Query parameters
                ['id' => $id]  // Additional parameters
            );

            // If endpoint still has unresolved parameters, append ID in traditional way
            if (EndpointParameterResolver::hasUnresolvedParameters($resolvedEndpoint)) {
                if (!str_ends_with($resolvedEndpoint, '/')) {
                    $resolvedEndpoint .= '/';
                }
                $resolvedEndpoint .= $id;
            }

            $endpoint = $resolvedEndpoint;

            try {
                // Make the API request
                $response = $apiClient->get($endpoint);

                // ✅ FIX: Use newFromApiResponse instead of mapApiAttributes for proper attribute flattening
                $model = $instance->newFromApiResponse($response);

                if ($model === null) {
                    return null;
                }

                $model->exists = true;

                // Merge with local data if enabled
                if ($instance->shouldMergeWithLocalData()) {
                    $model = $instance->mergeWithLocalData($model);
                }

                // Fire the retrieved event
                $model->fireApiModelEvent('retrieved');

                return $model;
            } catch (\Exception $e) {
                $instance->handleApiError("Failed to find model with ID {$id}: " . $e->getMessage(), null, $e->getCode());
                return null;
            }
        });
    }

    /**
     * Get all of the models from the API.
     *
     * @param  array  $columns
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function all($columns = ['*'])
    {
        $instance = new static;
        if (!$instance->isApiModel()) {
            return parent::all($columns);
        }

        // Generate cache key
        $cacheKey = $instance->generateCacheKey('all');

        // Use cache if enabled
        return $instance->cacheRemember($cacheKey, function () use ($columns, $instance) {
            // Fire the retrieving event
            if ($instance->fireApiModelEvent('retrieving') === false) {
                return $instance->newCollection();
            }

            // Get the API client
            $apiClient = $instance->getApiClient();

            // Build the endpoint
            $endpoint = $instance->getApiEndpoint();

            try {
                // Make the API request
                $response = $apiClient->get($endpoint);

                // Ensure the response is an array
                if (!is_array($response)) {
                    $response = [$response];
                }

                // Create a collection of models
                $models = $instance->newCollection();

                foreach ($response as $item) {
                    // ✅ FIX: Use newFromApiResponse instead of mapApiAttributes for proper attribute flattening
                    $model = $instance->newFromApiResponse(['data' => $item]);

                    if ($model === null) {
                        continue;
                    }

                    $model->exists = true;

                    // Merge with local data if enabled
                    if ($instance->shouldMergeWithLocalData()) {
                        $model = $instance->mergeWithLocalData($model);
                    }

                    // Fire the retrieved event
                    $model->fireApiModelEvent('retrieved');

                    $models->push($model);
                }

                return $models;
            } catch (\Exception $e) {
                $instance->handleApiError("Failed to get all models: " . $e->getMessage(), null, $e->getCode());
                return $instance->newCollection();
            }
        });
    }

    /**
     * Save the model to the API.
     *
     * @param  array  $options
     * @return bool
     */
    public function save(array $options = [])
    {
        if (!$this->isApiModel()) {
            return parent::save($options);
        }

        // Fire the saving event
        if ($this->fireApiModelEvent('saving') === false) {
            return false;
        }

        // Determine if we're creating or updating
        $method = $this->exists ? 'update' : 'create';

        // Fire the appropriate event
        if ($this->fireApiModelEvent($method . 'ing') === false) {
            return false;
        }

        // Get the API client
        $apiClient = $this->getApiClient();

        // Build the endpoint
        $endpoint = $this->getApiEndpoint();
        if ($this->exists && $this->getKey()) {
            if (!str_ends_with($endpoint, '/')) {
                $endpoint .= '/';
            }
            $endpoint .= $this->getKey();
        }

        // Map model attributes to API request data
        $attributes = $this->mapModelAttributesToApi($this->getAttributes());

        try {
            // Make the API request
            if ($this->exists) {
                $response = $apiClient->put($endpoint, $attributes);
            } else {
                $response = $apiClient->post($endpoint, $attributes);
            }

            // ✅ FIX: Use newFromApiResponse for proper attribute flattening, then merge attributes
            $tempModel = $this->newFromApiResponse($response);

            if ($tempModel !== null) {
                // Update the current model with the flattened attributes
                $this->setRawAttributes($tempModel->getAttributes(), true);
            }
            $this->exists = true;

            // Merge with local data if enabled
            if ($this->shouldMergeWithLocalData()) {
                $this->syncWithLocalData();
            }

            // Clear the cache for this model
            $this->flushCache();

            // Fire the appropriate event
            $this->fireApiModelEvent($method . 'ed');
            $this->fireApiModelEvent('saved');

            return true;
        } catch (\Exception $e) {
            $this->handleApiError("Failed to save model: " . $e->getMessage(), null, $e->getCode());
            return false;
        }
    }

    /**
     * Delete the model from the API.
     *
     * @return bool|null
     */
    public function delete()
    {
        if (!$this->isApiModel()) {
            return parent::delete();
        }

        // Fire the deleting event
        if ($this->fireApiModelEvent('deleting') === false) {
            return false;
        }

        // Get the API client
        $apiClient = $this->getApiClient();

        // Build the endpoint
        $endpoint = $this->getApiEndpoint();
        if (!str_ends_with($endpoint, '/')) {
            $endpoint .= '/';
        }
        $endpoint .= $this->getKey();

        try {
            // Make the API request
            $apiClient->delete($endpoint);

            // Delete from local database if merging is enabled
            if ($this->shouldMergeWithLocalData()) {
                $this->deleteFromLocalData();
            }

            // Clear the cache for this model
            $this->flushCache();

            // Fire the deleted event
            $this->fireApiModelEvent('deleted');

            return true;
        } catch (\Exception $e) {
            $this->handleApiError("Failed to delete model: " . $e->getMessage(), null, $e->getCode());
            return false;
        }
    }

    /**
     * Merge the model with local database data.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return \Illuminate\Database\Eloquent\Model
     */
    protected function mergeWithLocalData($model)
    {
        // Get the local model
        $localModel = $this->getLocalModel($model->getKey());

        if ($localModel) {
            // Merge the attributes
            $apiAttributes = $model->getAttributes();
            $localAttributes = $localModel->getAttributes();

            // Rename the primary key to avoid conflicts
            $localAttributes[$this->getKeyName() . '_local'] = $localAttributes[$this->getKeyName()];
            unset($localAttributes[$this->getKeyName()]);

            // Merge the attributes, giving priority to API attributes
            $mergedAttributes = array_merge($localAttributes, $apiAttributes);

            // Create a new model instance with the merged attributes
            $model = $this->newInstance($mergedAttributes, true);
            $model->exists = true;
        }

        return $model;
    }

    /**
     * Get the local model for the given API model key.
     *
     * @param  mixed  $key
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    protected function getLocalModel($key)
    {
        // Use the parent query builder to query the local database
        return parent::newQuery()
            ->where($this->getLocalForeignKey(), $key)
            ->first();
    }

    /**
     * Sync the model with local database data.
     *
     * @return bool
     */
    protected function syncWithLocalData()
    {
        // Get the local model
        $localModel = $this->getLocalModel($this->getKey());

        // Create or update the local model
        if ($localModel) {
            // Update the local model
            $localModel->{$this->getLocalForeignKey()} = $this->getKey();
            $localModel->fill($this->getAttributes());
            return $localModel->save();
        } else {
            // Create a new local model
            $attributes = $this->getAttributes();
            $attributes[$this->getLocalForeignKey()] = $this->getKey();

            // Use the parent query builder to create a new local model
            return parent::newQuery()->create($attributes) !== null;
        }
    }

    /**
     * Delete the model from the local database.
     *
     * @return bool
     */
    protected function deleteFromLocalData()
    {
        // Get the local model
        $localModel = $this->getLocalModel($this->getKey());

        if ($localModel) {
            // Delete the local model
            return $localModel->delete();
        }

        return true;
    }

    /**
     * Create a new query builder instance and call the take method.
     *
     * @param int $value
     * @return \MTechStack\LaravelApiModelClient\Query\ApiQueryBuilder
     */
    public static function take($value)
    {
        return (new static)->newApiQuery()->take($value);
    }

    /**
     * Create a new query builder instance and call the limit method.
     *
     * @param int $value
     * @return \MTechStack\LaravelApiModelClient\Query\ApiQueryBuilder
     */
    public static function limit($value)
    {
        return (new static)->newApiQuery()->limit($value);
    }

    /**
     * Create a new query builder instance and call the where method.
     *
     * @param string $column
     * @param mixed $operator
     * @param mixed $value
     * @return \MTechStack\LaravelApiModelClient\Query\ApiQueryBuilder
     */
    public static function where($column, $operator = null, $value = null)
    {
        return (new static)->newApiQuery()->where($column, $operator, $value);
    }

    /**
     * Create a new query builder instance and call the with method.
     *
     * @param array|string $relations
     * @return \MTechStack\LaravelApiModelClient\Query\ApiQueryBuilder
     */
    public static function with($relations)
    {
        return (new static)->newApiQuery()->with($relations);
    }

    /**
     * Execute the query and get the first result.
     *
     * @param  array  $columns
     * @return \Illuminate\Database\Eloquent\Model|static|null
     */
    public static function first($columns = ['*'])
    {
        return (new static)->newApiQuery()->first($columns);
    }

    /**
     * Execute the query as a "select" statement.
     *
     * @param  array  $columns
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function get($columns = ['*'])
    {
        return (new static)->newApiQuery()->get($columns);
    }

    /**
     * Paginate the given query.
     *
     * @param  int|null  $perPage
     * @param  array  $columns
     * @param  string  $pageName
     * @param  int|null  $page
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public static function paginate($perPage = null, $columns = ['*'], $pageName = 'page', $page = null)
    {
        return (new static)->newApiQuery()->paginate($perPage, $columns, $pageName, $page);
    }

    /**
     * Get a paginator only supporting simple next and previous links.
     *
     * @param  int|null  $perPage
     * @param  array  $columns
     * @param  string  $pageName
     * @param  int|null  $page
     * @return \Illuminate\Contracts\Pagination\Paginator
     */
    public static function simplePaginate($perPage = null, $columns = ['*'], $pageName = 'page', $page = null)
    {
        return (new static)->newApiQuery()->simplePaginate($perPage, $columns, $pageName, $page);
    }

    /**
     * Find a model by its primary key or throw an exception.
     *
     * @param  mixed  $id
     * @param  array  $columns
     * @return \Illuminate\Database\Eloquent\Model|static
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public static function findOrFail($id, $columns = ['*'])
    {
        return (new static)->newApiQuery()->findOrFail($id, $columns);
    }

    /**
     * Find multiple models by their primary keys.
     *
     * @param  \Illuminate\Contracts\Support\Arrayable|array  $ids
     * @param  array  $columns
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function findMany($ids, $columns = ['*'])
    {
        return (new static)->newApiQuery()->findMany($ids, $columns);
    }

    /**
     * Execute the query and get the first result or throw an exception.
     *
     * @param  array  $columns
     * @return \Illuminate\Database\Eloquent\Model|static
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public static function firstOrFail($columns = ['*'])
    {
        return (new static)->newApiQuery()->firstOrFail($columns);
    }

    /**
     * Execute the query and get the first result or call a callback.
     *
     * @param  \Closure|array  $columns
     * @param  \Closure|null  $callback
     * @return \Illuminate\Database\Eloquent\Model|static|mixed
     */
    public static function firstOr($columns = ['*'], $callback = null)
    {
        return (new static)->newApiQuery()->firstOr($columns, $callback);
    }

    /**
     * Get a single column's value from the first result of a query.
     *
     * @param  string  $column
     * @return mixed
     */
    public static function value($column)
    {
        return (new static)->newApiQuery()->value($column);
    }

    /**
     * Get an array with the values of a given column.
     *
     * @param  string  $column
     * @param  string|null  $key
     * @return \Illuminate\Support\Collection
     */
    public static function pluck($column, $key = null)
    {
        return (new static)->newApiQuery()->pluck($column, $key);
    }

    /**
     * Retrieve the "count" result of the query.
     *
     * @param  string  $columns
     * @return int
     */
    public static function count($columns = '*')
    {
        return (new static)->newApiQuery()->count($columns);
    }

    /**
     * Retrieve the minimum value of a given column.
     *
     * @param  string  $column
     * @return mixed
     */
    public static function min($column)
    {
        return (new static)->newApiQuery()->min($column);
    }

    /**
     * Retrieve the maximum value of a given column.
     *
     * @param  string  $column
     * @return mixed
     */
    public static function max($column)
    {
        return (new static)->newApiQuery()->max($column);
    }

    /**
     * Retrieve the sum of the values of a given column.
     *
     * @param  string  $column
     * @return mixed
     */
    public static function sum($column)
    {
        return (new static)->newApiQuery()->sum($column);
    }

    /**
     * Retrieve the average of the values of a given column.
     *
     * @param  string  $column
     * @return mixed
     */
    public static function avg($column)
    {
        return (new static)->newApiQuery()->avg($column);
    }

    /**
     * Alias for the "avg" method.
     *
     * @param  string  $column
     * @return mixed
     */
    public static function average($column)
    {
        return static::avg($column);
    }

    /**
     * Determine if any rows exist for the current query.
     *
     * @return bool
     */
    public static function exists()
    {
        return (new static)->newApiQuery()->exists();
    }

    /**
     * Determine if no rows exist for the current query.
     *
     * @return bool
     */
    public static function doesntExist()
    {
        return (new static)->newApiQuery()->doesntExist();
    }

    /**
     * Create a new instance of the model.
     *
     * @param  array  $attributes
     * @param  array  $options
     * @return static
     */
    public static function create(array $attributes = [], array $options = [])
    {
        $instance = new static($attributes);
        $instance->save($options);
        return $instance;
    }

    /**
     * Create or update a record matching the attributes, and fill it with values.
     *
     * @param  array  $attributes
     * @param  array  $values
     * @return static
     */
    public static function updateOrCreate(array $attributes, array $values = [])
    {
        return (new static)->newApiQuery()->updateOrCreate($attributes, $values);
    }

    /**
     * Get the first record matching the attributes or instantiate it.
     *
     * @param  array  $attributes
     * @param  array  $values
     * @return static
     */
    public static function firstOrNew(array $attributes = [], array $values = [])
    {
        return (new static)->newApiQuery()->firstOrNew($attributes, $values);
    }

    /**
     * Get the first record matching the attributes or create it.
     *
     * @param  array  $attributes
     * @param  array  $values
     * @return static
     */
    public static function firstOrCreate(array $attributes, array $values = [])
    {
        return (new static)->newApiQuery()->firstOrCreate($attributes, $values);
    }

    /**
     * Update the model in the database.
     *
     * @param  array  $attributes
     * @param  array  $options
     * @return bool
     */
    public function update(array $attributes = [], array $options = [])
    {
        if (!$this->exists) {
            return false;
        }

        return $this->fill($attributes)->save($options);
    }

    /**
     * Reload a fresh model instance from the database.
     *
     * @param  array  $with
     * @return static|null
     */
    public function fresh($with = [])
    {
        if (!$this->exists) {
            return null;
        }

        $model = static::find($this->getKey());

        if ($model && !empty($with)) {
            $model->load($with);
        }

        return $model;
    }

    /**
     * Reload the current model instance with fresh attributes from the database.
     *
     * @return $this
     */
    public function refresh()
    {
        if (!$this->exists) {
            return $this;
        }

        $fresh = $this->fresh();

        if ($fresh) {
            $this->setRawAttributes($fresh->getAttributes(), true);
            $this->syncOriginal();
        }

        return $this;
    }

    /**
     * Create a collection of models from plain arrays.
     *
     * @param  array  $items
     * @param  string|null  $connection
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function hydrate(array $items, $connection = null)
    {
        $instance = new static;

        $items = array_map(function ($item) use ($instance) {
            if ($instance->isApiModel()) {
                // For API models, use newFromApiResponse to properly handle attribute flattening
                return $instance->newFromApiResponse($item);
            } else {
                // For regular models, use standard hydration
                return $instance->newFromBuilder($item);
            }
        }, $items);

        return $instance->newCollection($items);
    }

    /**
     * Define a polymorphic relationship that can handle both API and local models.
     *
     * @param  string|null  $name
     * @param  string|null  $type
     * @param  string|null  $id
     * @param  string|null  $ownerKey
     * @return mixed
     */
    public function morphToApi($name = null, $type = null, $id = null, $ownerKey = null)
    {
        $name = $name ?: $this->guessBelongsToRelation();

        list($type, $id) = $this->getMorphs(
            $name, $type, $id
        );

        // Get the morph type and ID from the model attributes
        $morphType = $this->getAttribute($type);
        $morphId = $this->getAttribute($id);

        if (empty($morphType) || empty($morphId)) {
            return null;
        }

        // Check if the morph type is an API model
        if (class_exists($morphType)) {
            $morphInstance = new $morphType;
            
            if (method_exists($morphInstance, 'isApiModel') && $morphInstance->isApiModel()) {
                // Handle API model - directly call find method
                try {
                    return $morphType::find($morphId);
                } catch (\Exception $e) {
                    // Log error and return null if API call fails
                    \Log::warning("Failed to fetch API model {$morphType} with ID {$morphId}: " . $e->getMessage());
                    return null;
                }
            }
        }

        // Fall back to standard morphTo for regular Eloquent models
        return parent::morphTo($name, $type, $id, $ownerKey);
    }

    /**
     * Override the standard morphTo to handle API models automatically.
     * Only applies when the current model is NOT an API model (i.e., it's a local database model).
     *
     * @param  string|null  $name
     * @param  string|null  $type
     * @param  string|null  $id
     * @param  string|null  $ownerKey
     * @return mixed
     */
    public function morphTo($name = null, $type = null, $id = null, $ownerKey = null)
    {
        // If this model itself is an API model, use standard morphTo behavior
        if ($this->isApiModel()) {
            return parent::morphTo($name, $type, $id, $ownerKey);
        }

        // This is a local database model with morphTo to potentially API models
        $name = $name ?: $this->guessBelongsToRelation();

        list($type, $id) = $this->getMorphs(
            $name, $type, $id
        );

        // Get the morph type and ID from the model attributes
        $morphType = $this->getAttribute($type);
        $morphId = $this->getAttribute($id);

        if (empty($morphType) || empty($morphId)) {
            return null;
        }

        // Check if the morph type is an API model
        if (class_exists($morphType)) {
            $morphInstance = new $morphType;
            
            if (method_exists($morphInstance, 'isApiModel') && $morphInstance->isApiModel()) {
                // Handle API model - directly call find method
                try {
                    return $morphType::find($morphId);
                } catch (\Exception $e) {
                    // Log error and return null if API call fails
                    \Log::warning("Failed to fetch API model {$morphType} with ID {$morphId}: " . $e->getMessage());
                    return null;
                }
            }
        }

        // Fall back to standard morphTo for regular Eloquent models
        return parent::morphTo($name, $type, $id, $ownerKey);
    }

    /**
     * Get the morphed model for API models (direct access, not a relationship).
     * Use this when you need the actual model instance instead of a relationship.
     *
     * @param  string|null  $name
     * @param  string|null  $type
     * @param  string|null  $id
     * @return mixed|null
     */
    public function getMorphedModel($name = null, $type = null, $id = null)
    {
        $name = $name ?: 'entity'; // Default name

        list($type, $id) = $this->getMorphs(
            $name, $type, $id
        );

        // Get the morph type and ID from the model attributes
        $morphType = $this->getAttribute($type);
        $morphId = $this->getAttribute($id);

        if (empty($morphType) || empty($morphId)) {
            return null;
        }

        // Check if the morph type is an API model
        if (class_exists($morphType)) {
            $morphInstance = new $morphType;
            
            if (method_exists($morphInstance, 'isApiModel') && $morphInstance->isApiModel()) {
                // Handle API model - directly call find method
                try {
                    return $morphType::find($morphId);
                } catch (\Exception $e) {
                    // Log error and return null if API call fails
                    \Log::warning("Failed to fetch API model {$morphType} with ID {$morphId}: " . $e->getMessage());
                    return null;
                }
            } else {
                // Handle regular Eloquent model
                return $morphType::find($morphId);
            }
        }

        return null;
    }

    /**
     * Clone the model into a new, non-existing instance.
     *
     * @param  array|null  $except
     * @return static
     */
    public function replicate(array $except = null)
    {
        $defaults = [
            $this->getKeyName(),
            $this->getCreatedAtColumn(),
            $this->getUpdatedAtColumn(),
        ];

        $attributes = Arr::except(
            $this->getAttributes(),
            $except ? array_unique(array_merge($except, $defaults)) : $defaults
        );

        return tap(new static, function ($instance) use ($attributes) {
            $instance->setRawAttributes($attributes);
        });
    }

    /**
     * Handle dynamic static method calls into the method.
     *
     * @param  string  $method
     * @param  array  $parameters
     * @return mixed
     */
    public static function __callStatic($method, $parameters)
    {
        return (new static)->$method(...$parameters);
    }
}
