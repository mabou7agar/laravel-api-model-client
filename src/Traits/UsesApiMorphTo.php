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

                // If target class extends ApiModel, return null.
                // API models have no DB table so morphTo() would fail, and
                // calling ::find() here adds latency (HTTP call) or OOM risk.
                // Views should use null-safe access ($detail->entity?->title)
                // or store denormalized data (title, img) on the parent table.
                if (is_string($targetClass) && class_exists($targetClass) && is_subclass_of($targetClass, ApiModel::class)) {
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
     * Check if the API client can be resolved without triggering OOM.
     * Returns false if the api-client binding doesn't exist or will fail.
     */
    protected function canResolveApiClient(string $targetClass): bool
    {
        try {
            return app()->bound('api-client') || app()->bound($targetClass);
        } catch (\Throwable $e) {
            return false;
        }
    }
}
