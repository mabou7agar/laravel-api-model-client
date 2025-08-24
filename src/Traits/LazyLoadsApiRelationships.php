<?php

namespace MTechStack\LaravelApiModelClient\Traits;

use MTechStack\LaravelApiModelClient\Relations\ApiRelation;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

trait LazyLoadsApiRelationships
{
    /**
     * Indicates if all API relations are being loaded lazily.
     *
     * @var bool
     */
    protected $lazyLoadingApiRelations = true;

    /**
     * The API relationships that should be loaded lazily.
     *
     * @var array
     */
    protected $lazyApiWith = [];

    /**
     * Eager load API relations on the model.
     *
     * @param  array|string  $relations
     * @return $this
     */
    public function loadApi($relations)
    {
        $relations = is_string($relations) ? func_get_args() : $relations;

        $this->eagerLoadApiRelations($relations);

        return $this;
    }

    /**
     * Eager load API relations on the model if they are not already eager loaded.
     *
     * @param  array  $relations
     * @return $this
     */
    public function loadApiMissing(array $relations)
    {
        $relations = array_filter($relations, function ($relation) {
            return ! $this->relationLoaded($relation);
        });

        return $this->loadApi($relations);
    }

    /**
     * Eager load API relations on the model.
     *
     * @param  array  $relations
     * @return void
     */
    protected function eagerLoadApiRelations(array $relations)
    {
        foreach ($relations as $name => $constraints) {
            if (is_numeric($name)) {
                $name = $constraints;
                $constraints = function () {
                    //
                };
            }

            $segments = explode('.', $name);
            $name = array_shift($segments);

            if (! method_exists($this, $name) || ! $this->{$name}() instanceof ApiRelation) {
                continue;
            }

            $relation = $this->{$name}();
            $results = $relation->getResults();

            $this->setRelation($name, $results);

            // If there are nested relationships, we'll load those too
            if (! empty($segments)) {
                $nestedRelations = [implode('.', $segments) => $constraints];
                
                if ($results instanceof Collection) {
                    foreach ($results as $result) {
                        $result->eagerLoadApiRelations($nestedRelations);
                    }
                } elseif ($results) {
                    $results->eagerLoadApiRelations($nestedRelations);
                }
            }
        }
    }

    /**
     * Get the relationships that should be loaded lazily.
     *
     * @return array
     */
    public function getLazyApiWith()
    {
        return $this->lazyApiWith;
    }

    /**
     * Set the relationships that should be loaded lazily.
     *
     * @param  array  $relations
     * @return $this
     */
    public function setLazyApiWith(array $relations)
    {
        $this->lazyApiWith = $relations;

        return $this;
    }

    /**
     * Enable or disable lazy loading API relations.
     *
     * @param  bool  $value
     * @return $this
     */
    public function withoutLazyLoadingApiRelations($value = true)
    {
        $this->lazyLoadingApiRelations = ! $value;

        return $this;
    }

    /**
     * Determine if lazy loading API relations is enabled.
     *
     * @return bool
     */
    public function getLazyLoadingApiRelations()
    {
        return $this->lazyLoadingApiRelations;
    }

    /**
     * Handle dynamic method calls to the model.
     *
     * @param  string  $method
     * @param  array  $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        // Check if the method is a relationship method
        if (method_exists($this, $method) && $this->{$method}() instanceof ApiRelation) {
            // If lazy loading is enabled and the relation is in the lazy load list
            if ($this->lazyLoadingApiRelations && 
                (empty($this->lazyApiWith) || in_array($method, $this->lazyApiWith))) {
                // Load the relation if it's not already loaded
                if (! $this->relationLoaded($method)) {
                    $this->loadApi($method);
                }
                
                return $this->getRelation($method);
            }
        }
        
        // Fall back to parent implementation
        return parent::__call($method, $parameters);
    }
}
