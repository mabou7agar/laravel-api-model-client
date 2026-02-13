<?php

namespace MTechStack\LaravelApiModelClient\Traits;

use Illuminate\Database\Eloquent\Relations\Relation;
use MTechStack\LaravelApiModelClient\Models\ApiModel;

/**
 * Trait UsesApiMorphTo
 *
 * Provides automatic API model detection and fetching for morphTo relationships.
 * Works with any morphTo relationship — auto-detects all morphTo methods on the model.
 *
 * Usage:
 *   use UsesApiMorphTo;
 *
 * The trait auto-discovers morphTo relationships by scanning for methods that
 * return MorphTo instances. Override $apiMorphRelations to list them explicitly.
 */
trait UsesApiMorphTo
{
    /** @var array Guard against re-entrant accessor calls per relation */
    protected array $resolvingMorphTo = [];

    /**
     * Override in your model to explicitly list morphTo relation names.
     * If empty, the trait auto-discovers them.
     * Example: protected $apiMorphRelations = ['entity', 'commentable'];
     */
    // protected $apiMorphRelations = [];

    /**
     * Boot the trait — register dynamic accessors for all morphTo relations.
     */
    public static function bootUsesApiMorphTo(): void
    {
        // Nothing needed at boot — we use __get override instead.
    }

    /**
     * Get the list of morphTo relation names this trait should handle.
     */
    protected function getApiMorphRelations(): array
    {
        if (property_exists($this, 'apiMorphRelations') && !empty($this->apiMorphRelations)) {
            return $this->apiMorphRelations;
        }

        // Auto-discover: check for common morphTo patterns
        $relations = [];
        foreach (['entity', 'commentable', 'taggable', 'likeable', 'morphable', 'relatable'] as $name) {
            if (method_exists($this, $name)) {
                $relations[] = $name;
            }
        }

        // Also check for {name}_type + {name}_id column pairs in fillable
        $fillable = $this->getFillable();
        foreach ($fillable as $col) {
            if (str_ends_with($col, '_type')) {
                $rel = substr($col, 0, -5); // strip _type
                if (in_array($rel . '_id', $fillable) && method_exists($this, $rel) && !in_array($rel, $relations)) {
                    $relations[] = $rel;
                }
            }
        }

        return $relations;
    }

    /**
     * Intercept attribute access for morphTo relations.
     */
    public function getAttribute($key)
    {
        // Only intercept known morphTo relation names
        if (in_array($key, $this->getApiMorphRelations())) {
            return $this->resolveApiMorphTo($key);
        }

        return parent::getAttribute($key);
    }

    /**
     * Resolve a morphTo relation, fetching from API if the target is an API model.
     */
    protected function resolveApiMorphTo(string $relationName)
    {
        // Already loaded
        if (array_key_exists($relationName, $this->relations)) {
            return $this->relations[$relationName];
        }

        // Guard against recursion
        if (!empty($this->resolvingMorphTo[$relationName])) {
            return null;
        }
        $this->resolvingMorphTo[$relationName] = true;

        try {
            $typeCol = $relationName . '_type';
            $idCol = $relationName . '_id';

            $morphType = $this->getOriginal($typeCol) ?? $this->getAttribute($typeCol);
            $entityId = $this->getOriginal($idCol) ?? $this->getAttribute($idCol);

            if ($morphType && $entityId) {
                $targetClass = Relation::getMorphedModel($morphType) ?: $morphType;

                // API model — fetch via ::find()
                if (is_string($targetClass) && $this->looksLikeApiModel($targetClass)) {
                    try {
                        $result = $targetClass::find($entityId);
                        $this->setRelation($relationName, $result);
                        return $result;
                    } catch (\Exception $e) {
                        $this->setRelation($relationName, null);
                        return null;
                    }
                }
            }

            // Non-API model — resolve via the relationship method
            if (method_exists($this, $relationName)) {
                $result = $this->{$relationName}()->first();
                $this->setRelation($relationName, $result);
                return $result;
            }

            return null;
        } finally {
            unset($this->resolvingMorphTo[$relationName]);
        }
    }

    /**
     * Check if a class looks like an ApiModel without autoloading it.
     */
    protected function looksLikeApiModel(string $className): bool
    {
        if (class_exists($className, false)) {
            return is_subclass_of($className, ApiModel::class);
        }

        return str_contains($className, '\\Api\\')
            || str_contains($className, '\\ApiModel')
            || str_contains($className, 'ApiModel');
    }
}
