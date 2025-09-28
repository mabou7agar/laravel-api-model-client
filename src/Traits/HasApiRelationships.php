<?php

namespace MTechStack\LaravelApiModelClient\Traits;

use MTechStack\LaravelApiModelClient\Relations\HasManyFromApi;
use MTechStack\LaravelApiModelClient\Relations\HasOneFromApi;
use MTechStack\LaravelApiModelClient\Relations\BelongsToFromApi;
use MTechStack\LaravelApiModelClient\Relations\HasManyThroughFromApi;
use Illuminate\Support\Str;

trait HasApiRelationships
{
    /**
     * Array of relationship attributes that should be converted to model instances.
     * Override this in your model to specify which attributes contain relationship data.
     *
     * @var array
     */
    protected $apiRelationshipAttributes = [];

    /**
     * Array of model classes for specific relationship attributes.
     * Format: ['attribute_name' => 'ModelClass']
     *
     * @var array
     */
    protected $apiRelationshipModels = [];

    /**
     * Override getAttribute to automatically convert array relationships to model instances.
     *
     * @param  string  $key
     * @return mixed
     */
    public function getAttribute($key)
    {
        $value = parent::getAttribute($key);

        // Check if this attribute should be converted to model instances
        if ($this->shouldConvertToModels($key, $value)) {
            return $this->convertArrayToModelCollection($key, $value);
        }

        return $value;
    }

    /**
     * Determine if an attribute should be converted to model instances.
     *
     * @param  string  $key
     * @param  mixed   $value
     * @return bool
     */
    protected function shouldConvertToModels($key, $value)
    {
        // Skip if value is null or already an object
        if ($value === null || is_object($value)) {
            return false;
        }

        // Skip if not an array
        if (!is_array($value)) {
            return false;
        }

        // Skip if empty array
        if (empty($value)) {
            return false;
        }

        // Check if explicitly configured as a relationship attribute
        if (in_array($key, $this->apiRelationshipAttributes)) {
            return true;
        }

        // Check if has a configured model class
        if (isset($this->apiRelationshipModels[$key])) {
            return true;
        }

        // Auto-detect common relationship patterns
        $relationshipPatterns = [
            'variants', 'children', 'items', 'products', 'categories',
            'tags', 'attributes', 'options', 'reviews', 'comments',
            'related_products', 'cross_sells', 'up_sells'
        ];

        if (in_array($key, $relationshipPatterns)) {
            return true;
        }

        // Check if the first item in the array looks like model data
        $firstItem = reset($value);
        if (is_array($firstItem) && isset($firstItem['id'])) {
            return true;
        }

        return false;
    }

    /**
     * Convert an array of data to a collection of model instances.
     *
     * @param  string  $key
     * @param  array   $value
     * @return \Illuminate\Database\Eloquent\Collection
     */
    protected function convertArrayToModelCollection($key, $value)
    {
        $models = [];

        // Determine the model class to use
        $modelClass = $this->getModelClassForAttribute($key);

        foreach ($value as $itemData) {
            if (is_array($itemData)) {
                $model = $this->createModelFromData($modelClass, $itemData);

                if ($model !== null) {
                    $models[] = $model;
                }
            } elseif (is_object($itemData)) {
                // If it's already a model instance, just add it
                $models[] = $itemData;
            }
        }

        // Return a collection of model instances
        return $this->newCollection($models);
    }

    /**
     * Get the model class to use for a specific attribute.
     *
     * @param  string  $key
     * @return string
     */
    protected function getModelClassForAttribute($key)
    {
        // Check if a specific model class is configured
        if (isset($this->apiRelationshipModels[$key])) {
            return $this->apiRelationshipModels[$key];
        }

        // Default to the current model class (for self-referencing relationships like variants)
        return get_class($this);
    }

    /**
     * Create a model instance from array data.
     *
     * @param  string  $modelClass
     * @param  array   $data
     * @return mixed
     */
    protected function createModelFromData($modelClass, $data)
    {
        try {
            // Create an instance of the model class
            $modelInstance = new $modelClass();

            // Use newFromApiResponse if available (for API models)
            if (method_exists($modelInstance, 'newFromApiResponse')) {
                $model = $modelInstance->newFromApiResponse($data);
            } else {
                // Fallback to creating a new instance and filling it
                $model = new $modelClass();
                $model->fill($data);
                $model->exists = true;
            }

            // Store API response data in the model for access to nested relations
            if ($model !== null && method_exists($model, 'setApiResponseData')) {
                $model->setApiResponseData($data);
            }

            return $model;
        } catch (\Exception $e) {
            // Log the error if configured to do so
            if (config('api-model-relations.error_handling.log_errors', true)) {
                \Illuminate\Support\Facades\Log::error('Error creating model from relationship data', [
                    'model_class' => $modelClass,
                    'exception' => $e->getMessage(),
                    'data' => $data,
                ]);
            }

            return null;
        }
    }

    /**
     * Configure which attributes should be converted to model instances.
     *
     * @param  array  $attributes
     * @return $this
     */
    public function setApiRelationshipAttributes(array $attributes)
    {
        $this->apiRelationshipAttributes = $attributes;
        return $this;
    }

    /**
     * Configure model classes for specific relationship attributes.
     *
     * @param  array  $models
     * @return $this
     */
    public function setApiRelationshipModels(array $models)
    {
        $this->apiRelationshipModels = $models;
        return $this;
    }
    /**
     * Define a has-many relationship with an API endpoint.
     *
     * @param string $related Related model class
     * @param string|null $endpoint API endpoint for the relationship (null to use default)
     * @param string|null $foreignKey Foreign key on the related model
     * @param string|null $localKey Local key on this model
     * @return \MTechStack\LaravelApiModelClient\Relations\HasManyFromApi
     */
    public function hasManyFromApi($related, $endpoint = null, $foreignKey = null, $localKey = null)
    {
        $instance = $this->newRelatedInstance($related);

        // If no foreign key was provided, use the default naming convention
        $foreignKey = $foreignKey ?: $this->getForeignKey();

        // If no local key was provided, use the primary key of this model
        $localKey = $localKey ?: $this->getKeyName();

        // If no endpoint was provided, use the default endpoint of the related model
        if ($endpoint === null) {
            $endpoint = $instance->getApiEndpoint();
        }

        return new HasManyFromApi($instance->newQuery(), $this, $endpoint, $foreignKey, $localKey);
    }

    /**
     * Define a has-one relationship with an API endpoint.
     *
     * @param string $related Related model class
     * @param string|null $endpoint API endpoint for the relationship (null to use default)
     * @param string|null $foreignKey Foreign key on the related model
     * @param string|null $localKey Local key on this model
     * @return \MTechStack\LaravelApiModelClient\Relations\HasOneFromApi
     */
    public function hasOneFromApi($related, $endpoint = null, $foreignKey = null, $localKey = null)
    {
        $instance = $this->newRelatedInstance($related);

        // If no foreign key was provided, use the default naming convention
        $foreignKey = $foreignKey ?: $this->getForeignKey();

        // If no local key was provided, use the primary key of this model
        $localKey = $localKey ?: $this->getKeyName();

        // If no endpoint was provided, use the default endpoint of the related model
        if ($endpoint === null) {
            $endpoint = $instance->getApiEndpoint();
        }

        return new HasOneFromApi($instance->newQuery(), $this, $endpoint, $foreignKey, $localKey);
    }

    /**
     * Define a belongs-to relationship with an API endpoint.
     *
     * @param string $related Related model class
     * @param string|null $endpoint API endpoint for the relationship (null to use default)
     * @param string|null $foreignKey Foreign key on this model
     * @param string|null $ownerKey Key on the related model
     * @return \MTechStack\LaravelApiModelClient\Relations\BelongsToFromApi
     */
    public function belongsToFromApi($related, $endpoint = null, $foreignKey = null, $ownerKey = null)
    {
        // If no foreign key was provided, use the default naming convention
        if ($foreignKey === null) {
            $foreignKey = Str::snake(class_basename($related)) . '_id';
        }

        $instance = $this->newRelatedInstance($related);

        // If no owner key was provided, use the primary key of the related model
        $ownerKey = $ownerKey ?: $instance->getKeyName();

        // If no endpoint was provided, use the default endpoint of the related model
        if ($endpoint === null) {
            $endpoint = $instance->getApiEndpoint();
        }

        return new BelongsToFromApi($instance->newQuery(), $this, $endpoint, $foreignKey, $ownerKey);
    }

    /**
     * Define a has-many-through relationship with an API endpoint.
     *
     * @param string $related Related model class
     * @param string $through Intermediate model class
     * @param string|null $firstKey Foreign key on the intermediate model
     * @param string|null $secondKey Foreign key on the related model
     * @param string|null $localKey Local key on this model
     * @return \MTechStack\LaravelApiModelClient\Relations\HasManyThroughFromApi
     */
    public function hasManyThroughFromApi($related, $through, $firstKey = null, $secondKey = null, $localKey = null)
    {
        $through = $this->newRelatedInstance($through);
        $related = $this->newRelatedInstance($related);

        // If no first key was provided, use the default naming convention
        $firstKey = $firstKey ?: $this->getForeignKey();

        // If no second key was provided, use the default naming convention
        $secondKey = $secondKey ?: $through->getForeignKey();

        // If no local key was provided, use the primary key of this model
        $localKey = $localKey ?: $this->getKeyName();

        return new HasManyThroughFromApi($related, $through, $firstKey, $secondKey, $localKey);
    }

    /**
     * Instantiate a new related instance for a relationship.
     *
     * @param string $class
     * @return mixed
     */
    protected function newRelatedInstance($class)
    {
        return new $class;
    }
}
