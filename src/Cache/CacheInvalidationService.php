<?php

namespace ApiModelRelations\Cache;

use ApiModelRelations\Models\ApiModel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class CacheInvalidationService
{
    /**
     * The cache strategy instance.
     *
     * @var \ApiModelRelations\Contracts\CacheStrategyInterface
     */
    protected $cacheStrategy;

    /**
     * Create a new cache invalidation service instance.
     *
     * @param  \ApiModelRelations\Contracts\CacheStrategyInterface  $cacheStrategy
     * @return void
     */
    public function __construct($cacheStrategy = null)
    {
        $this->cacheStrategy = $cacheStrategy ?? app('api-cache-strategy');
    }

    /**
     * Invalidate cache for a model.
     *
     * @param  \ApiModelRelations\Models\ApiModel  $model
     * @return void
     */
    public function invalidateModel(ApiModel $model)
    {
        $modelClass = get_class($model);
        $modelKey = $model->getKey();
        
        // Invalidate find cache
        $this->cacheStrategy->forget("find_{$modelClass}_{$modelKey}");
        
        // Invalidate all cache for this model type
        if (method_exists($this->cacheStrategy, 'flushByTags')) {
            $this->cacheStrategy->flushByTags([$this->getModelTag($model)]);
        }
        
        // Invalidate related models
        $this->invalidateRelatedModels($model);
    }

    /**
     * Invalidate cache for related models.
     *
     * @param  \ApiModelRelations\Models\ApiModel  $model
     * @return void
     */
    protected function invalidateRelatedModels(ApiModel $model)
    {
        // Get all relationship methods
        $relationships = $this->getRelationshipMethods($model);
        
        foreach ($relationships as $relation) {
            $relationName = $relation->getName();
            
            // Skip if the relation is not loaded
            if (!$model->relationLoaded($relationName)) {
                continue;
            }
            
            $relatedModels = $model->getRelation($relationName);
            
            // Handle both collections and single models
            if ($relatedModels instanceof ApiModel) {
                $this->invalidateModel($relatedModels);
            } elseif (is_iterable($relatedModels)) {
                foreach ($relatedModels as $relatedModel) {
                    if ($relatedModel instanceof ApiModel) {
                        $this->invalidateModel($relatedModel);
                    }
                }
            }
        }
    }

    /**
     * Invalidate cache for a specific API endpoint.
     *
     * @param  string  $endpoint
     * @return void
     */
    public function invalidateEndpoint($endpoint)
    {
        if (method_exists($this->cacheStrategy, 'flushByTags')) {
            $this->cacheStrategy->flushByTags([$this->getEndpointTag($endpoint)]);
        }
    }

    /**
     * Invalidate all cache for a model type.
     *
     * @param  string  $modelClass
     * @return void
     */
    public function invalidateModelType($modelClass)
    {
        if (method_exists($this->cacheStrategy, 'flushByTags')) {
            $this->cacheStrategy->flushByTags([$this->getModelTypeTag($modelClass)]);
        }
    }

    /**
     * Invalidate all API model cache.
     *
     * @return void
     */
    public function invalidateAll()
    {
        if (method_exists($this->cacheStrategy, 'flushByTags')) {
            $this->cacheStrategy->flushByTags(['api_model_relations']);
        } else {
            $this->cacheStrategy->flush();
        }
    }

    /**
     * Get the tag for a model.
     *
     * @param  \ApiModelRelations\Models\ApiModel  $model
     * @return string
     */
    protected function getModelTag(ApiModel $model)
    {
        $modelClass = get_class($model);
        $modelKey = $model->getKey();
        
        return "model_{$modelClass}_{$modelKey}";
    }

    /**
     * Get the tag for a model type.
     *
     * @param  string  $modelClass
     * @return string
     */
    protected function getModelTypeTag($modelClass)
    {
        return "model_type_" . str_replace('\\', '_', $modelClass);
    }

    /**
     * Get the tag for an API endpoint.
     *
     * @param  string  $endpoint
     * @return string
     */
    protected function getEndpointTag($endpoint)
    {
        return "endpoint_" . str_replace(['/', '?', '&', '='], '_', $endpoint);
    }

    /**
     * Get all relationship methods for a model.
     *
     * @param  \ApiModelRelations\Models\ApiModel  $model
     * @return array
     */
    protected function getRelationshipMethods(ApiModel $model)
    {
        $reflection = new \ReflectionClass($model);
        $methods = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC);
        
        return array_filter($methods, function ($method) use ($model) {
            // Skip methods that are not defined in the model
            if ($method->getDeclaringClass()->getName() !== get_class($model)) {
                return false;
            }
            
            // Check if the method returns a relationship
            $returnType = $method->getReturnType();
            if ($returnType) {
                $typeName = $returnType->getName();
                if (strpos($typeName, 'ApiModelRelations\\Relations\\') === 0) {
                    return true;
                }
            }
            
            // Check method body for relationship methods
            try {
                $body = $this->getMethodBody($method);
                return strpos($body, 'hasManyFromApi') !== false ||
                       strpos($body, 'belongsToFromApi') !== false ||
                       strpos($body, 'hasOneFromApi') !== false ||
                       strpos($body, 'belongsToManyFromApi') !== false ||
                       strpos($body, 'morphManyFromApi') !== false ||
                       strpos($body, 'hasManyThroughFromApi') !== false;
            } catch (\Exception $e) {
                return false;
            }
        });
    }

    /**
     * Get the body of a method.
     *
     * @param  \ReflectionMethod  $method
     * @return string
     */
    protected function getMethodBody(\ReflectionMethod $method)
    {
        $filename = $method->getFileName();
        $start_line = $method->getStartLine() - 1;
        $end_line = $method->getEndLine();
        $length = $end_line - $start_line;

        $source = file($filename);
        $body = implode('', array_slice($source, $start_line, $length));
        
        return $body;
    }
}
