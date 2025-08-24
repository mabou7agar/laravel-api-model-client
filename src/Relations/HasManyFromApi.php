<?php

namespace MTechStack\LaravelApiModelClient\Relations;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class HasManyFromApi extends ApiRelation
{
    /**
     * The foreign key of the parent model.
     *
     * @var string
     */
    protected $foreignKey;

    /**
     * The local key of the parent model.
     *
     * @var string
     */
    protected $localKey;

    /**
     * Create a new has many from API relationship instance.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param \Illuminate\Database\Eloquent\Model $parent
     * @param string $endpoint
     * @param string $foreignKey
     * @param string $localKey
     * @return void
     */
    public function __construct(Builder $query, Model $parent, string $endpoint, string $foreignKey, string $localKey)
    {
        $this->foreignKey = $foreignKey;
        $this->localKey = $localKey;
        
        parent::__construct($query, $parent, $endpoint);
    }

    /**
     * Set the base constraints on the relation query.
     *
     * @return void
     */
    public function addConstraints()
    {
        if (static::$constraints) {
            // We're not using query constraints for API relations
            // as filtering will be done at the API level
        }
    }

    /**
     * Set the constraints for an eager load of the relation.
     *
     * @param array $models
     * @return void
     */
    public function addEagerConstraints(array $models)
    {
        // We're not using eager load constraints for API relations
        // as filtering will be done at the API level
    }

    /**
     * Initialize the relation on a set of models.
     *
     * @param array $models
     * @param string $relation
     * @return array
     */
    public function initRelation(array $models, $relation)
    {
        foreach ($models as $model) {
            $model->setRelation($relation, $this->related->newCollection());
        }

        return $models;
    }

    /**
     * Match the eagerly loaded results to their parents.
     *
     * @param array $models
     * @param \Illuminate\Database\Eloquent\Collection $results
     * @param string $relation
     * @return array
     */
    public function match(array $models, Collection $results, $relation)
    {
        $dictionary = $this->buildDictionary($results);

        // Once we have the dictionary we can simply spin through the parent models to
        // link them up with their children using the keyed dictionary to make the
        // matching very convenient and easy work. Then we'll just return them.
        foreach ($models as $model) {
            $key = $model->getAttribute($this->localKey);

            if (isset($dictionary[$key])) {
                $model->setRelation(
                    $relation, $this->related->newCollection($dictionary[$key])
                );
            }
        }

        return $models;
    }

    /**
     * Build model dictionary keyed by the relation's foreign key.
     *
     * @param \Illuminate\Database\Eloquent\Collection $results
     * @return array
     */
    protected function buildDictionary(Collection $results)
    {
        $dictionary = [];

        foreach ($results as $result) {
            $dictionary[$result->{$this->foreignKey}][] = $result;
        }

        return $dictionary;
    }

    /**
     * Get the results of the relationship.
     *
     * @return mixed
     */
    public function getResults()
    {
        return $this->get();
    }

    /**
     * Execute the query as a "select" statement.
     *
     * @param array $columns
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function get($columns = ['*'])
    {
        $parentKey = $this->parent->getAttribute($this->localKey);
        
        if (is_null($parentKey)) {
            return $this->related->newCollection();
        }
        
        return $this->getRelationResults($parentKey);
    }

    /**
     * Get the relation results for a specific parent key.
     *
     * @param mixed $parentKey
     * @return \Illuminate\Database\Eloquent\Collection
     */
    protected function getRelationResults($parentKey)
    {
        $cacheKey = $this->getRelationCacheKey($parentKey);
        $cacheTtl = $this->getCacheTtl();
        
        // Check if we have a cached response
        if (config('api-model-relations.cache.enabled', true) && $cacheTtl > 0) {
            $cachedData = Cache::get($cacheKey);
            if ($cachedData !== null) {
                return $this->processApiResponse($cachedData);
            }
        }
        
        try {
            // Build the endpoint with query parameters
            $endpoint = $this->endpoint;
            $queryParams = [$this->foreignKey => $parentKey];
            
            // Make API request
            $response = $this->getApiClient()->get($endpoint, $queryParams);
            
            // Cache the response if caching is enabled
            if (config('api-model-relations.cache.enabled', true) && $cacheTtl > 0) {
                Cache::put($cacheKey, $response, $cacheTtl);
            }
            
            return $this->processApiResponse($response);
        } catch (\Exception $e) {
            // Log the error if configured to do so
            if (config('api-model-relations.error_handling.log_errors', true)) {
                \Illuminate\Support\Facades\Log::error('Error fetching HasManyFromApi relation', [
                    'endpoint' => $this->endpoint,
                    'parent_key' => $parentKey,
                    'exception' => $e->getMessage(),
                ]);
            }
            
            return $this->related->newCollection();
        }
    }

    /**
     * Process the API response into a collection of models.
     *
     * @param array $response
     * @return \Illuminate\Database\Eloquent\Collection
     */
    protected function processApiResponse($response)
    {
        // Extract items from the response
        $items = $this->extractItemsFromResponse($response);
        
        // Create a collection of models
        $models = [];
        
        foreach ($items as $item) {
            $model = $this->related->newFromApiResponse($item);
            if ($model !== null) {
                $models[] = $model;
            }
        }
        
        return $this->related->newCollection($models);
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
     * Get a cache key for this relation.
     *
     * @param mixed $parentKey
     * @return string
     */
    protected function getRelationCacheKey($parentKey)
    {
        $prefix = config('api-model-relations.cache.prefix', 'api_model_');
        $relatedClass = str_replace('\\', '_', get_class($this->related));
        $parentClass = str_replace('\\', '_', get_class($this->parent));
        
        return $prefix . $parentClass . '_' . $relatedClass . '_' . $this->foreignKey . '_' . $parentKey;
    }

    /**
     * Get the cache TTL for this relation.
     *
     * @return int
     */
    protected function getCacheTtl()
    {
        return method_exists($this->related, 'getCacheTtl')
            ? $this->related->getCacheTtl()
            : config('api-model-relations.cache.ttl', 3600);
    }

    /**
     * Get the key value of the parent's local key.
     *
     * @return mixed
     */
    public function getParentKey()
    {
        return $this->parent->getAttribute($this->localKey);
    }

    /**
     * Get the fully qualified parent key name.
     *
     * @return string
     */
    public function getQualifiedParentKeyName()
    {
        return $this->parent->qualifyColumn($this->localKey);
    }

    /**
     * Get the plain foreign key.
     *
     * @return string
     */
    public function getForeignKeyName()
    {
        return $this->foreignKey;
    }

    /**
     * Get the foreign key for the relationship.
     *
     * @return string
     */
    public function getQualifiedForeignKeyName()
    {
        return $this->foreignKey;
    }
}
