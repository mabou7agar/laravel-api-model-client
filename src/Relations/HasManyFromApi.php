<?php

namespace MTechStack\LaravelApiModelClient\Relations;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use MTechStack\LaravelApiModelClient\Query\ApiQueryBuilder;

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
            $model->setRelation($relation, $this->related->newCollection());
        }

        return $models;
    }

    /**
     * Match the eagerly loaded results to their parents.
     *
     * @param array $models
     * @param \Illuminate\Database\Eloquent\Collection|\Illuminate\Support\Collection $results
     * @param string $relation
     * @return array
     */
    public function match(array $models, Collection|BaseCollection $results, $relation)
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
     * @param \Illuminate\Database\Eloquent\Collection|\Illuminate\Support\Collection $results
     * @return array
     */
    protected function buildDictionary(Collection|BaseCollection $results)
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

        // Check if the relationship data is already loaded in the parent model
        $preloadedData = $this->getPreloadedRelationData();
        if ($preloadedData !== null) {
            return $this->processPreloadedData($preloadedData);
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
            // Build the endpoint - handle different endpoint patterns
            $endpoint = $this->endpoint;
            $queryParams = [];
            
            // Check if endpoint contains parameter placeholders that need substitution
            if (strpos($endpoint, '{') !== false) {
                // Replace parameter placeholders like {parent_id} with actual values
                $endpoint = str_replace('{' . $this->foreignKey . '}', $parentKey, $endpoint);
                $endpoint = str_replace('{id}', $parentKey, $endpoint);
            } elseif (strpos($endpoint, $this->foreignKey) !== false && !is_numeric(basename($endpoint))) {
                // If endpoint contains the foreign key name but not as a number, replace it
                $endpoint = str_replace($this->foreignKey, $parentKey, $endpoint);
            } else {
                // Default: use query parameters
                $queryParams = [$this->foreignKey => $parentKey];
            }

            // Make API request with header injection support
            $requestContext = [
                'endpoint' => $endpoint,
                'query_params' => $queryParams,
                'relation_type' => 'HasManyFromApi',
                'parent_key' => $parentKey
            ];
            $response = $this->getApiClient($requestContext)->get($endpoint, $queryParams);

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

        // Debug information
        if (config('api-debug.output.console', false)) {
            echo "🔄 Processing API response with " . count($items) . " items for " . get_class($this->related) . "\n";
        }

        // Create a collection of models
        $models = [];

        foreach ($items as $item) {
            // Skip non-array items
            if (!is_array($item)) {
                continue;
            }

            // Try to create a model from the data using newFromApiResponse
            $model = null;

            try {
                // First attempt: Use newFromApiResponse if available
                if (method_exists($this->related, 'newFromApiResponse')) {
                    $model = $this->related->newFromApiResponse($item);
                }

                // Second attempt: If model is still null, try creating a new instance and filling it
                if ($model === null) {
                    $relatedClass = get_class($this->related);
                    $model = new $relatedClass();
                    $model->fill($item);
                    $model->exists = true;
                }

                // Store API response data in the model for access to nested relations
                if ($model !== null && method_exists($model, 'setApiResponseData')) {
                    $model->setApiResponseData($item);
                }

                // Add to models array if successfully created
                if ($model !== null) {
                    $models[] = $model;

                    // Debug information
                    if (config('api-debug.output.console', false)) {
                        echo "✅ Created model: " . get_class($model) . "\n";
                    }
                }
            } catch (\Exception $e) {
                // Log the error if configured to do so
                if (config('api-model-relations.error_handling.log_errors', true)) {
                    \Illuminate\Support\Facades\Log::error('Error creating model from API response', [
                        'exception' => $e->getMessage(),
                        'item' => $item,
                    ]);
                }

                if (config('api-debug.output.console', false)) {
                    echo "❌ Error creating model: " . $e->getMessage() . "\n";
                }
            }
        }

        if (config('api-debug.output.console', false)) {
            echo "✅ Returning collection with " . count($models) . " models\n";
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

    /**
     * Check if the relationship data is already preloaded in the parent model.
     * This checks for common API inclusion patterns like 'variants', 'variant', etc.
     *
     * @return array|null
     */
    protected function getPreloadedRelationData()
    {
        $relationName = $this->getRelationName();

        // Debug: Log what relation name we detected
        if (config('api-debug.output.console', false)) {
            echo "🔍 Detected relation name: '{$relationName}'\n";
        }

        // Use direct attribute access to avoid triggering relations
        $attributes = $this->parent->getAttributes();

        // Check for common API inclusion patterns
        $possibleKeys = [
            $relationName,
            'variants',  // Most common case
            'variant',   // Singular form
            'included_variants',
            'embedded_variants',
            'included_' . $relationName,
            'embedded_' . $relationName,
        ];

        if (config('api-debug.output.console', false)) {
            echo "🔍 Checking possible keys: " . implode(', ', $possibleKeys) . "\n";
            echo "🔍 Available attributes: " . implode(', ', array_keys($attributes)) . "\n";
        }

        // Check direct attributes first (avoid getAttribute to prevent recursion)
        foreach ($possibleKeys as $key) {
            if (isset($attributes[$key]) && is_array($attributes[$key])) {
                if (config('api-debug.output.console', false)) {
                    echo "✅ Found pre-loaded data in '{$key}' attribute: " . count($attributes[$key]) . " items\n";
                }
                return $attributes[$key];
            }
        }

        // Check nested data structures (common in API responses)
        // Look for data.variants, data.variant, etc.
        if (isset($attributes['data']) && is_array($attributes['data'])) {
            $nestedData = $attributes['data'];
            if (config('api-debug.output.console', false)) {
                echo "🔍 Checking nested data with keys: " . implode(', ', array_keys($nestedData)) . "\n";
            }

            foreach ($possibleKeys as $key) {
                if (isset($nestedData[$key]) && is_array($nestedData[$key])) {
                    if (config('api-debug.output.console', false)) {
                        echo "✅ Found pre-loaded data in nested 'data.{$key}': " . count($nestedData[$key]) . " items\n";
                    }
                    return $nestedData[$key];
                }
            }
        }

        // Check if parent has raw API response data with included relationships
        if ($this->parent->hasApiResponseData()) {
            $apiResponse = $this->parent->getApiResponseData();
            if (config('api-debug.output.console', false)) {
                echo "🔍 Checking API response data with keys: " . implode(', ', array_keys($apiResponse)) . "\n";
            }

            // Check direct keys in API response
            foreach ($possibleKeys as $key) {
                if (isset($apiResponse[$key]) && is_array($apiResponse[$key])) {
                    if (config('api-debug.output.console', false)) {
                        echo "✅ Found pre-loaded data in API response '{$key}': " . count($apiResponse[$key]) . " items\n";
                    }
                    return $apiResponse[$key];
                }
            }

            // Check nested data in API response (data.variants, etc.)
            if (isset($apiResponse['data']) && is_array($apiResponse['data'])) {
                $nestedApiData = $apiResponse['data'];
                foreach ($possibleKeys as $key) {
                    if (isset($nestedApiData[$key]) && is_array($nestedApiData[$key])) {
                        if (config('api-debug.output.console', false)) {
                            echo "✅ Found pre-loaded data in API response 'data.{$key}': " . count($nestedApiData[$key]) . " items\n";
                        }
                        return $nestedApiData[$key];
                    }
                }
            }
        }

        // Check if parent has original attributes with included relationships
        $originalAttributes = $this->parent->getOriginal();
        if (is_array($originalAttributes)) {
            foreach ($possibleKeys as $key) {
                if (isset($originalAttributes[$key]) && is_array($originalAttributes[$key])) {
                    if (config('api-debug.output.console', false)) {
                        echo "✅ Found pre-loaded data in original attributes '{$key}': " . count($originalAttributes[$key]) . " items\n";
                    }
                    return $originalAttributes[$key];
                }
            }

            // Check nested data in original attributes
            if (isset($originalAttributes['data']) && is_array($originalAttributes['data'])) {
                $nestedOriginalData = $originalAttributes['data'];
                foreach ($possibleKeys as $key) {
                    if (isset($nestedOriginalData[$key]) && is_array($nestedOriginalData[$key])) {
                        if (config('api-debug.output.console', false)) {
                            echo "✅ Found pre-loaded data in original 'data.{$key}': " . count($nestedOriginalData[$key]) . " items\n";
                        }
                        return $nestedOriginalData[$key];
                    }
                }
            }
        }

        if (config('api-debug.output.console', false)) {
            echo "❌ No pre-loaded relation data found\n";
        }

        return null;
    }

    /**
     * Process preloaded relationship data into a collection of models.
     *
     * @param array $preloadedData
     * @return \Illuminate\Database\Eloquent\Collection
     */
    protected function processPreloadedData(array $preloadedData)
    {
        // If the data is empty, return empty collection
        if (empty($preloadedData)) {
            return $this->related->newCollection();
        }

        // If it's a single item, wrap it in an array
        if (isset($preloadedData[0]) === false && !empty($preloadedData)) {
            // Check if this looks like a single model (has keys that aren't numeric)
            $keys = array_keys($preloadedData);
            if (!is_numeric($keys[0])) {
                $preloadedData = [$preloadedData];
            }
        }

        // Debug information
        if (config('api-debug.output.console', false)) {
            echo "🔄 Processing " . count($preloadedData) . " preloaded items for " . get_class($this->related) . "\n";
        }

        $models = [];

        foreach ($preloadedData as $item) {
            // Skip non-array items
            if (!is_array($item)) {
                continue;
            }

            // Try to create a model from the data
            $model = null;

            try {
                // First attempt: Use newFromApiResponse if available
                if (method_exists($this->related, 'newFromApiResponse')) {
                    $model = $this->related->newFromApiResponse($item);
                }

                // Second attempt: If model is still null, try creating a new instance and filling it
                if ($model === null) {
                    $relatedClass = get_class($this->related);
                    $model = new $relatedClass();
                    $model->fill($item);
                    $model->exists = true;
                }

                // Store API response data in the model for access to nested relations
                if ($model !== null && method_exists($model, 'setApiResponseData')) {
                    $model->setApiResponseData($item);
                }

                // Add to models array if successfully created
                if ($model !== null) {
                    $models[] = $model;

                    // Debug information
                    if (config('api-debug.output.console', false)) {
                        echo "✅ Created model: " . get_class($model) . "\n";
                    }
                }
            } catch (\Exception $e) {
                // Log the error if configured to do so
                if (config('api-model-relations.error_handling.log_errors', true)) {
                    \Illuminate\Support\Facades\Log::error('Error creating model from preloaded data', [
                        'exception' => $e->getMessage(),
                        'item' => $item,
                    ]);
                }

                if (config('api-debug.output.console', false)) {
                    echo "❌ Error creating model: " . $e->getMessage() . "\n";
                }
            }
        }

        if (config('api-debug.output.console', false)) {
            echo "✅ Returning collection with " . count($models) . " models\n";
        }

        return $this->related->newCollection($models);
    }

    /**
     * Get the relation name based on the calling method or configuration.
     *
     * @return string
     */
    protected function getRelationName()
    {
        // Try to get the relation name from the debug backtrace
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 15);

        foreach ($trace as $frame) {
            if (isset($frame['function']) &&
                isset($frame['class']) &&
                $frame['class'] === get_class($this->parent) &&
                !in_array($frame['function'], ['get', 'getResults', '__call', 'getAttribute', 'hasManyFromApi', 'belongsToFromApi'])) {
                return $frame['function'];
            }
        }

        // Fallback: try to guess from the endpoint or related model class name
        $relatedClass = get_class($this->related);
        $baseName = class_basename($relatedClass);

        // Final fallback - just use 'variants' as most common case
        // Note: We avoid calling getAttribute() here to prevent infinite recursion
        return 'variants';
    }
}
