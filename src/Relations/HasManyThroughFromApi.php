<?php

namespace MTechStack\LaravelApiModelClient\Relations;

use MTechStack\LaravelApiModelClient\Models\ApiModel;
use MTechStack\LaravelApiModelClient\QueryBuilder\ApiQueryBuilder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class HasManyThroughFromApi extends ApiRelation
{
    /**
     * The "through" parent model instance.
     *
     * @var \ApiModelRelations\Models\ApiModel
     */
    protected $throughParent;

    /**
     * The far parent model instance.
     *
     * @var \ApiModelRelations\Models\ApiModel
     */
    protected $farParent;

    /**
     * The near key on the through parent model.
     *
     * @var string
     */
    protected $throughKey;

    /**
     * The far key on the related model.
     *
     * @var string
     */
    protected $farKey;

    /**
     * Create a new has many through relationship instance.
     *
     * @param  \ApiModelRelations\Models\ApiModel  $farParent
     * @param  \ApiModelRelations\Models\ApiModel  $throughParent
     * @param  string  $throughKey
     * @param  string  $farKey
     * @param  string  $localKey
     * @return void
     */
    public function __construct(ApiModel $farParent, ApiModel $throughParent, $throughKey, $farKey, $localKey)
    {
        $this->localKey = $localKey;
        $this->farKey = $farKey;
        $this->throughKey = $throughKey;
        $this->throughParent = $throughParent;
        $this->farParent = $farParent;

        parent::__construct($farParent);
    }

    /**
     * Get the results of the relationship.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getResults()
    {
        // First, get the intermediate models
        $intermediateValue = $this->farParent->{$this->localKey};
        
        if (is_null($intermediateValue)) {
            return $this->related->newCollection();
        }
        
        $intermediateModels = $this->throughParent->where($this->throughKey, $intermediateValue)->get();
        
        if ($intermediateModels->isEmpty()) {
            return $this->related->newCollection();
        }
        
        // Extract the far keys from the intermediate models
        $farKeys = $intermediateModels->pluck($this->farKey)->filter()->unique()->values()->all();
        
        if (empty($farKeys)) {
            return $this->related->newCollection();
        }
        
        // Get the related models
        return $this->related->whereIn($this->farKey, $farKeys)->get();
    }

    /**
     * Add constraints to the query.
     *
     * @param  \ApiModelRelations\QueryBuilder\ApiQueryBuilder  $query
     * @return \ApiModelRelations\QueryBuilder\ApiQueryBuilder
     */
    public function addConstraints(ApiQueryBuilder $query)
    {
        // This is handled in getResults() for API models
        return $query;
    }

    /**
     * Set the constraints for an eager load of the relation.
     *
     * @param  array  $models
     * @return void
     */
    public function addEagerConstraints(array $models)
    {
        // This is handled in getEager() for API models
    }

    /**
     * Initialize the relation on a set of models.
     *
     * @param  array  $models
     * @param  string  $relation
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
     * @param  array  $models
     * @param  \Illuminate\Database\Eloquent\Collection  $results
     * @param  string  $relation
     * @return array
     */
    public function match(array $models, Collection $results, $relation)
    {
        // Get all the through parents for all models
        $localKeys = collect($models)->pluck($this->localKey)->filter()->unique()->values()->all();
        
        if (empty($localKeys)) {
            return $models;
        }
        
        // Get all intermediate models
        $intermediateModels = $this->throughParent->whereIn($this->throughKey, $localKeys)->get();
        
        if ($intermediateModels->isEmpty()) {
            return $models;
        }
        
        // Create a map of local keys to far keys
        $keyMap = [];
        foreach ($intermediateModels as $intermediate) {
            $localKey = $intermediate->{$this->throughKey};
            $farKey = $intermediate->{$this->farKey};
            
            if (!isset($keyMap[$localKey])) {
                $keyMap[$localKey] = [];
            }
            
            $keyMap[$localKey][] = $farKey;
        }
        
        // Create a map of far keys to related models
        $relatedMap = [];
        foreach ($results as $related) {
            $relatedMap[$related->{$this->farKey}][] = $related;
        }
        
        // Match the related models to their parents
        foreach ($models as $model) {
            $localKey = $model->{$this->localKey};
            
            if (!isset($keyMap[$localKey])) {
                continue;
            }
            
            $farKeys = $keyMap[$localKey];
            $matchedModels = [];
            
            foreach ($farKeys as $farKey) {
                if (isset($relatedMap[$farKey])) {
                    foreach ($relatedMap[$farKey] as $related) {
                        $matchedModels[] = $related;
                    }
                }
            }
            
            $model->setRelation(
                $relation, $this->related->newCollection($matchedModels)
            );
        }
        
        return $models;
    }

    /**
     * Get the results of the relationship for eager loading.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getEager()
    {
        $farKeys = $this->getKeys($this->farParent, $this->localKey);
        
        if (empty($farKeys)) {
            return $this->related->newCollection();
        }
        
        // Get intermediate models
        $intermediateModels = $this->throughParent->whereIn($this->throughKey, $farKeys)->get();
        
        if ($intermediateModels->isEmpty()) {
            return $this->related->newCollection();
        }
        
        // Extract the far keys from the intermediate models
        $relatedKeys = $intermediateModels->pluck($this->farKey)->filter()->unique()->values()->all();
        
        if (empty($relatedKeys)) {
            return $this->related->newCollection();
        }
        
        // Get the related models
        return $this->related->whereIn($this->farKey, $relatedKeys)->get();
    }

    /**
     * Get the key value of a given model.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @param  string  $key
     * @return mixed
     */
    protected function getKeys($model, $key)
    {
        if ($model instanceof Collection) {
            return $model->pluck($key)->filter()->unique()->values()->all();
        }

        return [$model->{$key}];
    }
}
