<?php

namespace MTechStack\LaravelApiModelClient\Relations;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use MTechStack\LaravelApiModelClient\Query\ApiQueryBuilder;

class BelongsToFromApi extends ApiRelation
{
    /**
     * The foreign key of the parent model.
     *
     * @var string
     */
    protected $foreignKey;

    /**
     * The associated key on the parent model.
     *
     * @var string
     */
    protected $ownerKey;

    /**
     * Create a new belongs to from API relationship instance.
     *
     * @param \MTechStack\LaravelApiModelClient\Query\ApiQueryBuilder|\Illuminate\Database\Eloquent\Builder $query
     * @param \Illuminate\Database\Eloquent\Model $parent
     * @param string $endpoint
     * @param string $foreignKey
     * @param string $ownerKey
     * @return void
     */
    public function __construct(ApiQueryBuilder|Builder $query, Model $parent, string $endpoint, string $foreignKey, string $ownerKey)
    {
        $this->foreignKey = $foreignKey;
        $this->ownerKey = $ownerKey;
        
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
        $dictionary = [];

        // First we'll build a dictionary of child models keyed by the foreign key
        // so we can easily and quickly match them to their respective parents
        // without having a possibly slow inner loop for every model.
        foreach ($results as $result) {
            $dictionary[$result->getAttribute($this->ownerKey)] = $result;
        }

        // Once we have the dictionary we can simply spin through the parent models to
        // link them up with their children using the keyed dictionary to make the
        // matching very convenient and easy work.
        foreach ($models as $model) {
            $key = $model->getAttribute($this->foreignKey);
            
            if (isset($dictionary[$key])) {
                $model->setRelation($relation, $dictionary[$key]);
            }
        }

        return $models;
    }

    /**
     * Get the results of the relationship.
     *
     * @return mixed
     */
    public function getResults()
    {
        $foreignKey = $this->parent->getAttribute($this->foreignKey);
        
        if (is_null($foreignKey)) {
            return null;
        }
        
        return $this->getRelationResult($foreignKey);
    }

    /**
     * Get the relation result for a specific foreign key.
     *
     * @param mixed $foreignKey
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    protected function getRelationResult($foreignKey)
    {
        $cacheKey = $this->getRelationCacheKey($foreignKey);
        $cacheTtl = $this->getCacheTtl();
        
        // Check if we have a cached response
        if (config('api-model-relations.cache.enabled', true) && $cacheTtl > 0) {
            $cachedData = Cache::get($cacheKey);
            if ($cachedData !== null) {
                return $this->related->newFromApiResponse($cachedData);
            }
        }
        
        try {
            // Build the endpoint with parameter substitution
            $endpoint = $this->buildEndpointWithParameters($foreignKey);
            
            // Make API request with header injection support
            $requestContext = [
                'endpoint' => $endpoint,
                'relation_type' => 'BelongsToFromApi',
                'foreign_key' => $foreignKey
            ];
            $response = $this->getApiClient($requestContext)->get($endpoint);
            
            // Cache the response if caching is enabled
            if (config('api-model-relations.cache.enabled', true) && $cacheTtl > 0) {
                Cache::put($cacheKey, $response, $cacheTtl);
            }
            
            return $this->related->newFromApiResponse($response);
        } catch (\Exception $e) {
            // Log the error if configured to do so
            if (config('api-model-relations.error_handling.log_errors', true)) {
                \Illuminate\Support\Facades\Log::error('Error fetching BelongsToFromApi relation', [
                    'endpoint' => $this->endpoint,
                    'resolved_endpoint' => $endpoint ?? 'failed_to_resolve',
                    'foreign_key' => $foreignKey,
                    'exception' => $e->getMessage(),
                ]);
            }
            
            return null;
        }
    }

    /**
     * Build endpoint with parameter substitution.
     *
     * @param mixed $foreignKey
     * @return string
     */
    protected function buildEndpointWithParameters($foreignKey)
    {
        $endpoint = $this->endpoint;
        
        // Replace common parameter patterns
        $patterns = [
            '{' . $this->ownerKey . '}' => $foreignKey,
            '{' . $this->foreignKey . '}' => $foreignKey,
            '{id}' => $foreignKey,
            '{product_id}' => $foreignKey,
            '{parent_id}' => $foreignKey,
            '{category_id}' => $foreignKey,
            '{user_id}' => $foreignKey,
        ];
        
        foreach ($patterns as $pattern => $replacement) {
            if (strpos($endpoint, $pattern) !== false) {
                $endpoint = str_replace($pattern, $replacement, $endpoint);
                return $endpoint;
            }
        }
        
        // If no parameter patterns found, append the foreign key
        return rtrim($endpoint, '/') . '/' . $foreignKey;
    }

    /**
     * Get a cache key for this relation.
     *
     * @param mixed $foreignKey
     * @return string
     */
    protected function getRelationCacheKey($foreignKey)
    {
        $prefix = config('api-model-relations.cache.prefix', 'api_model_');
        $relatedClass = str_replace('\\', '_', get_class($this->related));
        
        return $prefix . $relatedClass . '_find_' . $foreignKey;
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
     * Get the foreign key of the relationship.
     *
     * @return string
     */
    public function getForeignKeyName()
    {
        return $this->foreignKey;
    }

    /**
     * Get the fully qualified foreign key of the relationship.
     *
     * @return string
     */
    public function getQualifiedForeignKeyName()
    {
        return $this->parent->qualifyColumn($this->foreignKey);
    }

    /**
     * Get the associated key of the relationship.
     *
     * @return string
     */
    public function getOwnerKeyName()
    {
        return $this->ownerKey;
    }

    /**
     * Get the fully qualified associated key of the relationship.
     *
     * @return string
     */
    public function getQualifiedOwnerKeyName()
    {
        return $this->related->qualifyColumn($this->ownerKey);
    }

    /**
     * Get the value of the model's foreign key.
     *
     * @return mixed
     */
    public function getForeignKey()
    {
        return $this->parent->getAttribute($this->foreignKey);
    }
}
