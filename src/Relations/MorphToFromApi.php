<?php

namespace MTechStack\LaravelApiModelClient\Relations;

use MTechStack\LaravelApiModelClient\Models\ApiModel;
use MTechStack\LaravelApiModelClient\Query\ApiQueryBuilder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class MorphToFromApi extends ApiRelation
{
    /**
     * The type of the polymorphic relation.
     *
     * @var string
     */
    protected $morphType;

    /**
     * The id of the polymorphic relation.
     *
     * @var string
     */
    protected $morphId;

    /**
     * The class name of the target model.
     *
     * @var string|null
     */
    protected $morphClass;

    /**
     * The name of the relationship.
     *
     * @var string
     */
    protected $relationName;

    /**
     * Create a new morph to relationship instance.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $parent
     * @param  string  $name
     * @param  string  $morphType
     * @param  string  $morphId
     * @param  string|null  $localKey
     * @return void
     */
    public function __construct(Model $parent, $name, $morphType, $morphId, $localKey = null)
    {
        $this->relationName = $name;
        $this->morphId = $morphId;
        $this->morphType = $morphType;
        $this->localKey = $localKey;
        $this->morphClass = $parent->{$morphType};

        // If we have a morphClass, we need to set the related model
        if ($this->morphClass) {
            // Convert the stored morph type to a class name if needed
            $this->morphClass = $this->getMorphClassFromType($this->morphClass);
            
            // Create an instance of the related model
            $this->related = new $this->morphClass;
        } else {
            // Default to a generic ApiModel if no morph class is set
            $this->related = new class extends ApiModel {
                // This is a placeholder model
            };
        }

        parent::__construct($this->related);
        
        $this->parent = $parent;
    }

    /**
     * Get the results of the relationship.
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function getResults()
    {
        if (! $this->morphClass) {
            return null;
        }

        $id = $this->parent->{$this->morphId};

        if (is_null($id)) {
            return null;
        }

        // Get the related model from the API
        return $this->related->find($id);
    }

    /**
     * Add constraints to the query.
     *
     * @param  \MTechStack\LaravelApiModelClient\Query\ApiQueryBuilder  $query
     * @return \MTechStack\LaravelApiModelClient\Query\ApiQueryBuilder
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
            $model->setRelation($relation, null);
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

        // Group the results by their morph type and ID
        foreach ($results as $result) {
            $morphClass = get_class($result);
            $dictionary[$morphClass][$result->getKey()] = $result;
        }

        // Match each model to its corresponding related model
        foreach ($models as $model) {
            $morphClass = $this->getMorphClassFromType($model->{$this->morphType});
            $foreignKey = $model->{$this->morphId};

            if (isset($dictionary[$morphClass][$foreignKey])) {
                $model->setRelation($relation, $dictionary[$morphClass][$foreignKey]);
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
        $models = $this->parent->newCollection();

        // Group models by morph type
        $groups = $this->buildDictionary($this->getParents());

        // For each morph type, fetch the related models
        foreach ($groups as $morphClass => $ids) {
            // Skip if no IDs or invalid morph class
            if (empty($ids) || !class_exists($morphClass)) {
                continue;
            }

            // Create an instance of the related model
            $instance = new $morphClass;
            
            // Get the related models from the API
            $related = $instance->whereIn($instance->getKeyName(), array_values($ids))->get();
            
            // Add the related models to the collection
            $models = $models->merge($related);
        }

        return $models;
    }

    /**
     * Build a dictionary of the related models.
     *
     * @param  \Illuminate\Database\Eloquent\Collection  $models
     * @return array
     */
    protected function buildDictionary(Collection $models)
    {
        $dictionary = [];

        foreach ($models as $model) {
            $morphClass = $this->getMorphClassFromType($model->{$this->morphType});
            $foreignKey = $model->{$this->morphId};

            if ($morphClass && $foreignKey) {
                $dictionary[$morphClass][$model->getKey()] = $foreignKey;
            }
        }

        return $dictionary;
    }

    /**
     * Get the parents of the relation.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    protected function getParents()
    {
        return $this->parent->newCollection([$this->parent]);
    }

    /**
     * Get the class name from the morph type.
     *
     * @param  string  $type
     * @return string
     */
    protected function getMorphClassFromType($type)
    {
        // Check if the type is already a fully qualified class name
        if (class_exists($type)) {
            return $type;
        }

        // Check if we have a morph map defined in the config
        $morphMap = config('api-model-relations.morph_map', []);
        
        if (isset($morphMap[$type])) {
            return $morphMap[$type];
        }

        // Default to the type as the class name
        return $type;
    }

    /**
     * Associate the model instance to the given parent.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function associate($model)
    {
        $this->parent->setAttribute($this->morphId, $model ? $model->getKey() : null);
        
        $this->parent->setAttribute(
            $this->morphType, $model ? $this->getMorphTypeFromClass(get_class($model)) : null
        );

        return $this->parent;
    }

    /**
     * Get the morph type from the class name.
     *
     * @param  string  $class
     * @return string
     */
    protected function getMorphTypeFromClass($class)
    {
        // Check if we have a morph map defined in the config
        $morphMap = array_flip(config('api-model-relations.morph_map', []));
        
        if (isset($morphMap[$class])) {
            return $morphMap[$class];
        }

        // Default to the class name
        return $class;
    }

    /**
     * Dissociate previously associated model from the given parent.
     *
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function dissociate()
    {
        $this->parent->setAttribute($this->morphId, null);
        $this->parent->setAttribute($this->morphType, null);

        return $this->parent;
    }

    /**
     * Get the relationship for eager loading.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getRelationExistenceQuery(ApiQueryBuilder $query, ApiQueryBuilder $parentQuery, $columns = ['*'])
    {
        // This is not applicable for API models
        return $query;
    }

    /**
     * Get the cache key for the relationship.
     *
     * @return string
     */
    protected function getRelationCacheKey()
    {
        $parentClass = str_replace('\\', '_', get_class($this->parent));
        $morphClass = str_replace('\\', '_', $this->morphClass ?? 'null');
        $id = $this->parent->{$this->morphId};
        
        return "morph_to_from_api_{$parentClass}_{$morphClass}_{$id}";
    }
}
