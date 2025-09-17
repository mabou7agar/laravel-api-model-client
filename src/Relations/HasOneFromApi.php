<?php

namespace MTechStack\LaravelApiModelClient\Relations;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use MTechStack\LaravelApiModelClient\Query\ApiQueryBuilder;

class HasOneFromApi extends ApiRelation
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
     * Create a new has one from API relationship instance.
     *
     * @param \MTechStack\LaravelApiModelClient\Query\ApiQueryBuilder|\Illuminate\Database\Eloquent\Builder $query
     * @param \Illuminate\Database\Eloquent\Model $parent
     * @param string $endpoint
     * @param string $foreignKey
     * @param string $localKey
     * @return void
     */
    public function __construct(ApiQueryBuilder|Builder $query, Model $parent, string $endpoint, string $foreignKey, string $localKey)
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
            $model->setRelation($relation, null);
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

        // Once we have the dictionary we can easily match the results back to their
        // parent using the dictionary and the primary key of the related models. We
        // will return the models, being sure to set the matching relations on them.
        foreach ($models as $model) {
            $key = $model->getAttribute($this->localKey);

            if (isset($dictionary[$key])) {
                $model->setRelation($relation, $dictionary[$key]);
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
            $dictionary[$result->{$this->foreignKey}] = $result;
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
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function get($columns = ['*'])
    {
        $parentKey = $this->parent->getAttribute($this->localKey);
        
        if (is_null($parentKey)) {
            return null;
        }
        
        // Check if the relationship data is already loaded in the parent model
        $preloadedData = $this->getPreloadedRelationData();
        if ($preloadedData !== null) {
            return $this->processPreloadedData($preloadedData);
        }
        
        return $this->getRelationResults($parentKey);
    }

    /**
     * Get preloaded relation data from the parent model.
     *
     * @return mixed|null
     */
    protected function getPreloadedRelationData()
    {
        $relationName = $this->getRelationName();
        
        // Check various possible keys for preloaded data
        $possibleKeys = [
            $relationName,
            Str::snake($relationName),
            Str::camel($relationName),
            'included_' . $relationName,
            'embedded_' . $relationName,
            'included_' . Str::snake($relationName),
            'embedded_' . Str::snake($relationName),
        ];
        
        if (config('api-debug.output.console', false)) {
            echo "🔍 Checking possible keys: " . implode(', ', $possibleKeys) . "\n";
            echo "🔍 Available attributes: " . implode(', ', array_keys($this->parent->getAttributes())) . "\n";
        }
        
        foreach ($possibleKeys as $key) {
            if (array_key_exists($key, $this->parent->getAttributes())) {
                $data = $this->parent->getAttributes()[$key];
                if ($data !== null) {
                    if (config('api-debug.output.console', false)) {
                        echo "✅ Found pre-loaded data in '$key' attribute: " . (is_array($data) ? count($data) . ' items' : 'single item') . "\n";
                    }
                    return $data;
                }
            }
        }
        
        return null;
    }

    /**
     * Process preloaded relation data.
     *
     * @param mixed $data
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    protected function processPreloadedData($data)
    {
        if (is_array($data) && !empty($data)) {
            // For HasOne, we expect a single item, so take the first one
            $itemData = is_array($data[0]) ? $data[0] : $data;
            return $this->related->newFromApiResponse($itemData);
        } elseif (is_array($data)) {
            // Single item data
            return $this->related->newFromApiResponse($data);
        }
        
        return null;
    }

    /**
     * Get the relation results for a specific parent key.
     *
     * @param mixed $parentKey
     * @return \Illuminate\Database\Eloquent\Model|null
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
                \Illuminate\Support\Facades\Log::error('Error fetching HasOneFromApi relation', [
                    'endpoint' => $this->endpoint,
                    'parent_key' => $parentKey,
                    'exception' => $e->getMessage(),
                ]);
            }
            
            return null;
        }
    }

    /**
     * Process the API response into a single model.
     *
     * @param array $response
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    protected function processApiResponse($response)
    {
        // Extract item from the response
        $item = $this->extractItemFromResponse($response);
        
        if ($item !== null) {
            return $this->related->newFromApiResponse($item);
        }
        
        return null;
    }

    /**
     * Extract a single item from an API response, handling different response formats.
     *
     * @param array $response
     * @return array|null
     */
    protected function extractItemFromResponse($response)
    {
        // If response is a single item (has keys but not numeric), return it
        if (is_array($response) && !isset($response[0]) && !empty($response)) {
            return $response;
        }
        
        // If response is an array with a single item, return the first item
        if (isset($response[0])) {
            return $response[0];
        }
        
        // Check for common wrapper keys
        $possibleKeys = ['data', 'item', 'result', 'record', 'content'];
        
        foreach ($possibleKeys as $key) {
            if (isset($response[$key])) {
                $data = $response[$key];
                if (is_array($data)) {
                    // If it's an array with items, take the first one
                    if (isset($data[0])) {
                        return $data[0];
                    }
                    // If it's a single item object, return it
                    if (!empty($data)) {
                        return $data;
                    }
                }
            }
        }
        
        // If we can't find an item, return null
        return null;
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
     * Get the plain foreign key.
     *
     * @return string
     */
    public function getForeignKeyName()
    {
        return $this->foreignKey;
    }

    /**
     * Get the name of the "where in" method for eager loading.
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     * @param string $key
     * @return string
     */
    protected function whereInMethod(Model $model, $key)
    {
        return 'whereIn';
    }

    /**
     * Get the relation name.
     *
     * @return string
     */
    protected function getRelationName()
    {
        // Try to determine relation name from debug backtrace
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);
        
        foreach ($trace as $frame) {
            if (isset($frame['function']) && 
                isset($frame['class']) && 
                $frame['function'] !== '__call' && 
                $frame['function'] !== 'getAttribute' &&
                method_exists($frame['class'], $frame['function'])) {
                return $frame['function'];
            }
        }
        
        return 'unknown';
    }
}
