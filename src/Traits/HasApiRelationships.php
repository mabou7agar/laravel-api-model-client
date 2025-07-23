<?php

namespace ApiModelRelations\Traits;

use ApiModelRelations\Relations\HasManyFromApi;
use ApiModelRelations\Relations\BelongsToFromApi;
use ApiModelRelations\Relations\HasManyThroughFromApi;
use Illuminate\Support\Str;

trait HasApiRelationships
{
    /**
     * Define a has-many relationship with an API endpoint.
     *
     * @param string $related Related model class
     * @param string|null $endpoint API endpoint for the relationship (null to use default)
     * @param string|null $foreignKey Foreign key on the related model
     * @param string|null $localKey Local key on this model
     * @return \ApiModelRelations\Relations\HasManyFromApi
     */
    public function hasManyFromApi($related, $endpoint = null, $foreignKey = null, $localKey = null)
    {
        $instance = $this->newRelatedInstance($related);
        
        // If no foreign key was provided, use the default naming convention
        $foreignKey = $foreignKey ?: $this->getForeignKey();
        
        // If no local key was provided, use the primary key of this model
        $localKey = $localKey ?: $this->getKeyName();
        
        // If no endpoint was provided, use the default endpoint of the related model
        if ($endpoint === null) {
            $endpoint = $instance->getApiEndpoint();
        }
        
        return new HasManyFromApi($instance->newQuery(), $this, $endpoint, $foreignKey, $localKey);
    }
    
    /**
     * Define a belongs-to relationship with an API endpoint.
     *
     * @param string $related Related model class
     * @param string|null $endpoint API endpoint for the relationship (null to use default)
     * @param string|null $foreignKey Foreign key on this model
     * @param string|null $ownerKey Key on the related model
     * @return \ApiModelRelations\Relations\BelongsToFromApi
     */
    public function belongsToFromApi($related, $endpoint = null, $foreignKey = null, $ownerKey = null)
    {
        // If no foreign key was provided, use the default naming convention
        if ($foreignKey === null) {
            $foreignKey = Str::snake(class_basename($related)) . '_id';
        }
        
        $instance = $this->newRelatedInstance($related);
        
        // If no owner key was provided, use the primary key of the related model
        $ownerKey = $ownerKey ?: $instance->getKeyName();
        
        // If no endpoint was provided, use the default endpoint of the related model
        if ($endpoint === null) {
            $endpoint = $instance->getApiEndpoint();
        }
        
        return new BelongsToFromApi($instance->newQuery(), $this, $endpoint, $foreignKey, $ownerKey);
    }
    
    /**
     * Define a has-many-through relationship with an API endpoint.
     *
     * @param string $related Related model class
     * @param string $through Intermediate model class
     * @param string|null $firstKey Foreign key on the intermediate model
     * @param string|null $secondKey Foreign key on the related model
     * @param string|null $localKey Local key on this model
     * @return \ApiModelRelations\Relations\HasManyThroughFromApi
     */
    public function hasManyThroughFromApi($related, $through, $firstKey = null, $secondKey = null, $localKey = null)
    {
        $through = $this->newRelatedInstance($through);
        $related = $this->newRelatedInstance($related);
        
        // If no first key was provided, use the default naming convention
        $firstKey = $firstKey ?: $this->getForeignKey();
        
        // If no second key was provided, use the default naming convention
        $secondKey = $secondKey ?: $through->getForeignKey();
        
        // If no local key was provided, use the primary key of this model
        $localKey = $localKey ?: $this->getKeyName();
        
        return new HasManyThroughFromApi($related, $through, $firstKey, $secondKey, $localKey);
    }
    
    /**
     * Instantiate a new related instance for a relationship.
     *
     * @param string $class
     * @return mixed
     */
    protected function newRelatedInstance($class)
    {
        return new $class;
    }
}
