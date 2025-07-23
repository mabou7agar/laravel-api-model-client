<?php

namespace ApiModelRelations\Traits;

use Illuminate\Support\Arr;

/**
 * Trait for handling API model attributes
 */
trait ApiModelAttributes
{
    /**
     * The API endpoint for this model.
     *
     * @var string|null
     */
    protected $apiEndpoint;

    /**
     * The base URL for API requests.
     *
     * @var string|null
     */
    protected $apiBaseUrl;

    /**
     * The API authentication strategy to use.
     *
     * @var string|null
     */
    protected $apiAuthStrategy;

    /**
     * The API request timeout in seconds.
     *
     * @var int|null
     */
    protected $apiTimeout;

    /**
     * Whether to merge API data with local database data.
     *
     * @var bool
     */
    protected $mergeWithLocalData = false;

    /**
     * The local database table to merge with.
     *
     * @var string|null
     */
    protected $localTable;

    /**
     * The foreign key to use for merging with local data.
     *
     * @var string
     */
    protected $localForeignKey = 'api_id';

    /**
     * Get the API endpoint for this model.
     *
     * @return string
     */
    public function getApiEndpoint()
    {
        return $this->apiEndpoint ?? '/' . str_replace('_', '-', Str::snake(Str::pluralStudly(class_basename($this))));
    }

    /**
     * Set the API endpoint for this model.
     *
     * @param  string  $endpoint
     * @return $this
     */
    public function setApiEndpoint($endpoint)
    {
        $this->apiEndpoint = $endpoint;

        return $this;
    }

    /**
     * Get the base URL for API requests.
     *
     * @return string
     */
    public function getApiBaseUrl()
    {
        return $this->apiBaseUrl ?? config('api-model-relations.base_url');
    }

    /**
     * Set the base URL for API requests.
     *
     * @param  string  $url
     * @return $this
     */
    public function setApiBaseUrl($url)
    {
        $this->apiBaseUrl = $url;

        return $this;
    }

    /**
     * Get the API authentication strategy to use.
     *
     * @return string
     */
    public function getApiAuthStrategy()
    {
        return $this->apiAuthStrategy ?? config('api-model-relations.auth_strategy');
    }

    /**
     * Set the API authentication strategy to use.
     *
     * @param  string  $strategy
     * @return $this
     */
    public function setApiAuthStrategy($strategy)
    {
        $this->apiAuthStrategy = $strategy;

        return $this;
    }

    /**
     * Get the API request timeout in seconds.
     *
     * @return int
     */
    public function getApiTimeout()
    {
        return $this->apiTimeout ?? config('api-model-relations.timeout');
    }

    /**
     * Set the API request timeout in seconds.
     *
     * @param  int  $timeout
     * @return $this
     */
    public function setApiTimeout($timeout)
    {
        $this->apiTimeout = $timeout;

        return $this;
    }

    /**
     * Determine if this model should merge API data with local database data.
     *
     * @return bool
     */
    public function shouldMergeWithLocalData()
    {
        return $this->mergeWithLocalData;
    }

    /**
     * Enable or disable merging API data with local database data.
     *
     * @param  bool  $merge
     * @return $this
     */
    public function setMergeWithLocalData($merge = true)
    {
        $this->mergeWithLocalData = $merge;

        return $this;
    }

    /**
     * Get the local database table to merge with.
     *
     * @return string
     */
    public function getLocalTable()
    {
        return $this->localTable ?? $this->getTable();
    }

    /**
     * Set the local database table to merge with.
     *
     * @param  string  $table
     * @return $this
     */
    public function setLocalTable($table)
    {
        $this->localTable = $table;

        return $this;
    }

    /**
     * Get the foreign key to use for merging with local data.
     *
     * @return string
     */
    public function getLocalForeignKey()
    {
        return $this->localForeignKey;
    }

    /**
     * Set the foreign key to use for merging with local data.
     *
     * @param  string  $key
     * @return $this
     */
    public function setLocalForeignKey($key)
    {
        $this->localForeignKey = $key;

        return $this;
    }

    /**
     * Map API response data to model attributes.
     *
     * @param  array  $data
     * @return array
     */
    protected function mapApiAttributes(array $data)
    {
        // Apply any transformations to the API data here
        return $data;
    }

    /**
     * Map model attributes to API request data.
     *
     * @param  array  $attributes
     * @return array
     */
    protected function mapModelAttributesToApi(array $attributes)
    {
        // Filter out any attributes that shouldn't be sent to the API
        return Arr::except($attributes, [
            $this->getKeyName() . '_local',
            'created_at',
            'updated_at',
            'deleted_at',
        ]);
    }
}
