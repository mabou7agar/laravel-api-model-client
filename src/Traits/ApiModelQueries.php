<?php

namespace MTechStack\LaravelApiModelClient\Traits;

use MTechStack\LaravelApiModelClient\Query\ApiQueryBuilder;
use Illuminate\Support\Collection;

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
     * @return \MTechStack\LaravelApiModelClient\Query\ApiQueryBuilder
     */
    public function newQuery()
    {
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

            // Build the endpoint
            $endpoint = $instance->getApiEndpoint();
            if (!str_ends_with($endpoint, '/')) {
                $endpoint .= '/';
            }
            $endpoint .= $id;

            try {
                // Make the API request
                $response = $apiClient->get($endpoint);

                // Map the API response to model attributes
                $attributes = $instance->mapApiAttributes($response);

                // Create a new model instance with the attributes
                $model = $instance->newInstance($attributes, true);
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
                    // Map the API response to model attributes
                    $attributes = $instance->mapApiAttributes($item);

                    // Create a new model instance with the attributes
                    $model = $instance->newInstance($attributes, true);
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

            // Map the API response to model attributes
            $responseAttributes = $this->mapApiAttributes($response);

            // Update the model with the response attributes
            $this->setRawAttributes($responseAttributes, true);
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
}
