<?php

namespace MTechStack\LaravelApiModelClient\Traits;

use Illuminate\Database\Eloquent\Relations\Relation;
use MTechStack\LaravelApiModelClient\Models\ApiModel;

/**
 * Trait UsesApiMorphTo
 *
 * Provides automatic API model detection and fetching for morphTo relationships.
 * Simply add this trait to any model with morphTo relationships and it will
 * automatically fetch API models when the target extends ApiModel.
 */
trait UsesApiMorphTo
{
    /** @var bool Guard against re-entrant getEntityAttribute calls */
    protected bool $resolvingEntity = false;

    /**
     * Get the entity attribute with API model override.
     * This method is called when accessing $model->entity
     */
    public function getEntityAttribute()
    {
        // Check if we already have the entity loaded
        if (array_key_exists('entity', $this->relations)) {
            return $this->relations['entity'];
        }

        // Guard: if we're already resolving, return null to break recursion
        if ($this->resolvingEntity) {
            return null;
        }
        $this->resolvingEntity = true;

        try {
            // Get the morph type and ID directly from attributes
            $morphType = $this->getOriginal('entity_type') ?? $this->getAttribute('entity_type');
            $entityId = $this->getOriginal('entity_id') ?? $this->getAttribute('entity_id');

            if ($morphType && $entityId) {
                // Resolve the target class using morph map
                $targetClass = Relation::getMorphedModel($morphType) ?: $morphType;

                // If target class is an API model, return null.
                // Avoid class_exists()/is_subclass_of() — autoloading + booting
                // ApiModel's 12+ traits can exhaust 512MB of memory.
                if (is_string($targetClass) && $this->looksLikeApiModel($targetClass)) {
                    $this->setRelation('entity', null);
                    return null;
                }
            }

            // For non-API models, resolve via the relationship method directly.
            // Do NOT call getRelationValue('entity') — it can re-trigger this accessor.
            if (method_exists($this, 'entity')) {
                $entity = $this->entity()->first();
                $this->setRelation('entity', $entity);
                return $entity;
            }

            return null;
        } finally {
            $this->resolvingEntity = false;
        }
    }

    /**
     * Check if a class looks like an ApiModel without autoloading it.
     * Uses reflection only if the class is already loaded.
     */
    protected function looksLikeApiModel(string $className): bool
    {
        // Fast check: if class is already loaded, use is_subclass_of
        if (class_exists($className, false)) {
            return is_subclass_of($className, ApiModel::class);
        }

        // Class not loaded — check by convention (namespace/name patterns)
        // This avoids autoloading which boots all ApiModel traits and can OOM
        return str_contains($className, '\\Api\\')
            || str_contains($className, '\\ApiModel')
            || str_contains($className, 'ApiModel');
    }
}
