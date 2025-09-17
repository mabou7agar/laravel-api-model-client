<?php

namespace MTechStack\LaravelApiModelClient\Relations;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\App;
use MTechStack\LaravelApiModelClient\Query\ApiQueryBuilder;

abstract class ApiRelation extends Relation
{
    /**
     * The API endpoint for this relation.
     *
     * @var string
     */
    protected $endpoint;

    /**
     * Create a new API relation instance.
     *
     * @param \MTechStack\LaravelApiModelClient\Query\ApiQueryBuilder|\Illuminate\Database\Eloquent\Builder $query
     * @param \Illuminate\Database\Eloquent\Model $parent
     * @param string $endpoint
     * @return void
     */
    public function __construct(ApiQueryBuilder|Builder $query, Model $parent, string $endpoint)
    {
        $this->endpoint = $endpoint;
        
        // Handle ApiQueryBuilder by creating a compatible Builder instance
        if ($query instanceof ApiQueryBuilder) {
            // Store the original query for API operations
            $this->query = $query;
            $this->parent = $parent;
            $this->related = $query->getModel();
            
            // Initialize relation properties without calling parent constructor
            static::$constraints = true;
        } else {
            // For regular Eloquent Builder, use parent constructor
            parent::__construct($query, $parent);
        }
    }

    /**
     * Get the API client instance.
     *
     * @return \MTechStack\LaravelApiModelClient\Contracts\ApiClientInterface
     */
    protected function getApiClient()
    {
        return App::make('api-client');
    }

    /**
     * Get the API endpoint for this relation.
     *
     * @return string
     */
    public function getEndpoint()
    {
        return $this->endpoint;
    }

    /**
     * Handle dynamic method calls to the relationship.
     *
     * @param string $method
     * @param array $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        if (method_exists($this->query->getModel(), 'scope'.ucfirst($method))) {
            return $this->query->$method(...$parameters);
        }

        return parent::__call($method, $parameters);
    }

    /**
     * Get a plain instance of the related model.
     *
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function getRelated()
    {
        return $this->related;
    }
}
