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
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;
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
    use LazyLoadsApiRelationships {
        // Resolve method collisions
        ApiModelCaching::getCacheTtl insteadof ApiModelInterfaceMethods;
        ApiModelQueries::delete insteadof ApiModelInterfaceMethods;
        ApiModelQueries::save insteadof ApiModelInterfaceMethods;
        
        // Create aliases for the conflicting methods from ApiModelInterfaceMethods
        ApiModelInterfaceMethods::getCacheTtl as getInterfaceCacheTtl;
        ApiModelInterfaceMethods::delete as deleteFromInterface;
        ApiModelInterfaceMethods::save as saveFromInterface;
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
        return App::make('api-client');
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

        // Handle nested data structure (e.g., Bagisto API returns {data: [...], meta: {...}})
        $data = $response;
        if (is_array($response) && isset($response['data']) && is_array($response['data'])) {
            $data = $response['data'];
        }

        return collect($data)->map(function ($item) {
            return new static($item);
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
            
            // Handle nested data structure for single items
            $data = $response;
            if (is_array($response) && isset($response['data']) && is_array($response['data'])) {
                $data = $response['data'];
            }
            
            return new static($data);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Save the model to the API.
     *
     * @return bool
     */
    public function saveToApi()
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
}
