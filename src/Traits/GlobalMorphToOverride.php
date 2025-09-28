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
     * Override morphTo method globally using Laravel's macro system.
     *
     * @return void
     */
    protected function overrideMorphToGlobally(): void
    {
        Model::macro('morphTo', function ($name = null, $type = null, $id = null, $ownerKey = null) {
            // Auto-detect relation name if not provided
            if (is_null($name)) {
                $name = $this->guessBelongsToRelation();
            }
            
            // Set default column names
            $type = $type ?: $name.'_type';
            $id = $id ?: $name.'_id';
            
            // Get the morphed type from the model
            $morphType = $this->getAttribute($type);
            
            if ($morphType) {
                // Resolve the actual class from morph map
                $class = Relation::getMorphedModel($morphType) ?: $morphType;
                
                // Check if target class extends ApiModel
                if (is_string($class) && is_subclass_of($class, ApiModel::class)) {
                    // Return our API-aware MorphTo relation
                    return new MorphToFromApi($this, $name, $type, $id, $ownerKey);
                }
            }
            
            // Fall back to original Eloquent morphTo
            return new \Illuminate\Database\Eloquent\Relations\MorphTo(
                $this->newQuery(), $this, $id, $ownerKey, $type, $name
            );
        });
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
