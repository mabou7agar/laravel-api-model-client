<?php

namespace ApiModelRelations\Relations;

use ApiModelRelations\Models\ApiModel;
use ApiModelRelations\QueryBuilder\ApiQueryBuilder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class MorphManyFromApi extends ApiRelation
{
    /**
     * The type of the polymorphic relation.
     *
     * @var string
     */
    protected $morphType;

    /**
     * The value for the polymorphic type.
     *
     * @var string
     */
    protected $morphClass;

    /**
     * The foreign key of the parent model.
     *
     * @var string
     */
    protected $foreignKey;

    /**
     * Create a new morph many relationship instance.
     *
     * @param  \ApiModelRelations\Models\ApiModel  $parent
     * @param  string  $type
     * @param  string  $id
     * @param  string  $localKey
     * @return void
     */
    public function __construct(ApiModel $parent, $type, $id, $localKey)
    {
        $this->morphType = $type;
        $this->foreignKey = $id;
        $this->morphClass = $parent->getMorphClass();
        $this->localKey = $localKey;

        parent::__construct($parent);
    }

    /**
     * Get the results of the relationship.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getResults()
    {
        $localValue = $this->parent->{$this->localKey};
        
        if (is_null($localValue)) {
            return $this->related->newCollection();
        }
        
        return $this->related
            ->where($this->morphType, $this->morphClass)
            ->where($this->foreignKey, $localValue)
            ->get();
    }

    /**
     * Add constraints to the query.
     *
     * @param  \ApiModelRelations\QueryBuilder\ApiQueryBuilder  $query
     * @return \ApiModelRelations\QueryBuilder\ApiQueryBuilder
     */
    public function addConstraints(ApiQueryBuilder $query)
    {
        $query->where($this->morphType, $this->morphClass);
        
        $localValue = $this->parent->{$this->localKey};
        
        if (!is_null($localValue)) {
            $query->where($this->foreignKey, $localValue);
        }
        
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
        $dictionary = [];

        // Group the results by foreign key
        foreach ($results as $result) {
            // Only include results that match the morph type
            if ($result->{$this->morphType} === $this->morphClass) {
                $foreignKey = $result->{$this->foreignKey};
                
                if (!isset($dictionary[$foreignKey])) {
                    $dictionary[$foreignKey] = [];
                }
                
                $dictionary[$foreignKey][] = $result;
            }
        }

        // Match the results to their parents
        foreach ($models as $model) {
            $localKey = $model->{$this->localKey};
            
            if (isset($dictionary[$localKey])) {
                $model->setRelation(
                    $relation, $this->related->newCollection($dictionary[$localKey])
                );
            }
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
        $keys = $this->getKeys($this->parent, $this->localKey);
        
        if (empty($keys)) {
            return $this->related->newCollection();
        }
        
        return $this->related
            ->where($this->morphType, $this->morphClass)
            ->whereIn($this->foreignKey, $keys)
            ->get();
    }

    /**
     * Create a new instance of the related model.
     *
     * @param  array  $attributes
     * @return \ApiModelRelations\Models\ApiModel
     */
    public function create(array $attributes = [])
    {
        $attributes[$this->morphType] = $this->morphClass;
        $attributes[$this->foreignKey] = $this->parent->{$this->localKey};

        $instance = $this->related->newInstance($attributes);
        $instance->save();

        return $instance;
    }

    /**
     * Get the foreign key value for the relation.
     *
     * @param  mixed  $id
     * @return mixed
     */
    protected function parseId($id)
    {
        if ($id instanceof ApiModel) {
            return $id->{$this->localKey};
        }

        return $id;
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
