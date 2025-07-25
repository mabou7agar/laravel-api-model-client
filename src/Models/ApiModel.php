<?php

namespace ApiModelRelations\Models;

use ApiModelRelations\Contracts\ApiModelInterface;
use ApiModelRelations\Traits\ApiModelAttributes;
use ApiModelRelations\Traits\ApiModelCaching;
use ApiModelRelations\Traits\ApiModelErrorHandling;
use ApiModelRelations\Traits\ApiModelEvents;
use ApiModelRelations\Traits\ApiModelInterfaceMethods;
use ApiModelRelations\Traits\ApiModelQueries;
use ApiModelRelations\Traits\HasApiRelationships;
use ApiModelRelations\Traits\LazyLoadsApiRelationships;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;

abstract class ApiModel extends Model implements ApiModelInterface
{
    use ApiModelAttributes;
    use ApiModelCaching;
    use ApiModelErrorHandling;
    use ApiModelEvents;
    use ApiModelInterfaceMethods;
    use ApiModelQueries;
    use HasApiRelationships;
    use LazyLoadsApiRelationships;

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
        return strtolower(str_plural($className));
    }

    /**
     * Get the API client instance.
     *
     * @return \ApiModelRelations\Contracts\ApiClientInterface
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

        return collect($response)->map(function ($data) {
            return new static($data);
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
            return new static($response);
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
}
