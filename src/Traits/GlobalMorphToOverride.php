<?php

namespace MTechStack\LaravelApiModelClient\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use MTechStack\LaravelApiModelClient\Models\ApiModel;
use MTechStack\LaravelApiModelClient\Relations\MorphToFromApi;

/**
 * Trait GlobalMorphToOverride
 * 
 * Provides global morphTo override functionality to automatically use
 * MorphToFromApi when the target model extends ApiModel.
 */
trait GlobalMorphToOverride
{
    /**
     * Register global morphTo override system.
     * 
     * This method should be called in the service provider's boot() method.
     *
     * @return void
     */
    protected function registerGlobalMorphToOverride(): void
    {
        // 1. Set up morph map for consistent type resolution
        $this->setupMorphMap();
        
        // 2. Override morphTo method globally
        $this->overrideMorphToGlobally();
    }

    /**
     * Set up morph map from configuration.
     *
     * @return void
     */
    protected function setupMorphMap(): void
    {
        $morphMap = config('api-model-client.morph_map', []);
        
        if (!empty($morphMap)) {
            Relation::morphMap($morphMap);
        }

        // Optionally enforce morph map (prevents FQCN in database)
        if (config('api-model-client.enforce_morph_map', false)) {
            Relation::enforceMorphMap();
        }
    }

    /**
     * Override morphTo method globally using relation resolvers.
     *
     * @return void
     */
    protected function overrideMorphToGlobally(): void
    {
        // Get common morph relation names from config
        $morphRelationNames = config('api-model-client.morph_relation_names', [
            'entity', 'subject', 'target', 'owner', 'morph', 'morphable'
        ]);

        // Register resolvers for each common morph relation name
        foreach ($morphRelationNames as $relationName) {
            Model::resolveRelationUsing($relationName, function (Model $model) use ($relationName) {
                return static::createSmartMorphToRelation($model, $relationName);
            });
        }
    }

    /**
     * Create a smart morphTo relation that detects ApiModel targets.
     *
     * @param Model $model
     * @param string $relationName
     * @return \Illuminate\Database\Eloquent\Relations\Relation
     */
    protected static function createSmartMorphToRelation(Model $model, string $relationName)
    {
        // Set default column names
        $typeColumn = $relationName . '_type';
        $idColumn = $relationName . '_id';
        
        // Get the morphed type from the model
        $morphType = $model->getAttribute($typeColumn);
        
        if ($morphType) {
            // Resolve the actual class from morph map
            $class = Relation::getMorphedModel($morphType) ?: $morphType;
            
            // Check if target class exists and extends ApiModel
            if (is_string($class) && class_exists($class) && is_subclass_of($class, ApiModel::class)) {
                // Return our API-aware MorphTo relation
                return new MorphToFromApi($model, $relationName, $typeColumn, $idColumn);
            }
        }
        
        // Fall back to standard Eloquent MorphTo (create directly to avoid recursion)
        return new \Illuminate\Database\Eloquent\Relations\MorphTo(
            $model->newQuery(), $model, $idColumn, null, $typeColumn, $relationName
        );
    }

    /**
     * Get the default morph map configuration.
     *
     * @return array
     */
    protected function getDefaultMorphMap(): array
    {
        return [
            // Add default mappings here if needed
            // 'product' => \Modules\BagistoProduct\Models\Api\Product::class,
        ];
    }

    /**
     * Check if a class extends ApiModel.
     *
     * @param string $class
     * @return bool
     */
    protected function isApiModelClass(string $class): bool
    {
        try {
            return is_subclass_of($class, ApiModel::class);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Log morphTo override activity for debugging.
     *
     * @param string $action
     * @param array $context
     * @return void
     */
    protected function logMorphToActivity(string $action, array $context = []): void
    {
        if (config('api-model-client.debug_morph_override', false)) {
            logger()->debug("MorphTo Override: {$action}", $context);
        }
    }
}
