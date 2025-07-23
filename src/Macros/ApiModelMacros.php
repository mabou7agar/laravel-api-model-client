<?php

namespace ApiModelRelations\Macros;

use ApiModelRelations\Models\ApiModel;
use ApiModelRelations\Query\ApiQueryBuilder;
use Illuminate\Support\Facades\Config;

class ApiModelMacros
{
    /**
     * Register all macros.
     *
     * @return void
     */
    public static function register(): void
    {
        static::registerApiModelMacros();
        static::registerApiQueryBuilderMacros();
    }

    /**
     * Register macros for the ApiModel class.
     *
     * @return void
     */
    protected static function registerApiModelMacros(): void
    {
        /**
         * Refresh the model instance from the API.
         *
         * @return $this
         */
        ApiModel::macro('refreshFromApi', function () {
            $freshModel = static::find($this->getKey());
            
            if ($freshModel) {
                $this->setRawAttributes($freshModel->getAttributes(), true);
            }
            
            return $this;
        });

        /**
         * Force the model to use the API even if a local record exists.
         *
         * @param bool $force
         * @return $this
         */
        ApiModel::macro('forceApi', function (bool $force = true) {
            $this->forceApiMode = $force;
            return $this;
        });

        /**
         * Clear the API cache for this model.
         *
         * @return bool
         */
        ApiModel::macro('clearApiCache', function () {
            $cacheKey = $this->getApiCacheKey($this->getKey());
            return app('cache')->forget($cacheKey);
        });

        /**
         * Clear all API cache for this model type.
         *
         * @return bool
         */
        ApiModel::macro('clearAllApiCache', function () {
            $prefix = Config::get('api-model-relations.cache.prefix', 'api_model_relations_');
            $modelType = strtolower(class_basename(static::class));
            
            // This is a simplistic approach - in a real-world scenario,
            // you might want to use a more sophisticated cache tag system
            return app('cache')->forget($prefix . $modelType . '_all');
        });
    }

    /**
     * Register macros for the ApiQueryBuilder class.
     *
     * @return void
     */
    protected static function registerApiQueryBuilderMacros(): void
    {
        /**
         * Add a where clause that checks if a field contains a value.
         *
         * @param string $field
         * @param mixed $value
         * @return \ApiModelRelations\Query\ApiQueryBuilder
         */
        ApiQueryBuilder::macro('whereContains', function (string $field, $value) {
            return $this->where($field, 'contains', $value);
        });

        /**
         * Add a where clause that checks if a field starts with a value.
         *
         * @param string $field
         * @param mixed $value
         * @return \ApiModelRelations\Query\ApiQueryBuilder
         */
        ApiQueryBuilder::macro('whereStartsWith', function (string $field, $value) {
            return $this->where($field, 'startsWith', $value);
        });

        /**
         * Add a where clause that checks if a field ends with a value.
         *
         * @param string $field
         * @param mixed $value
         * @return \ApiModelRelations\Query\ApiQueryBuilder
         */
        ApiQueryBuilder::macro('whereEndsWith', function (string $field, $value) {
            return $this->where($field, 'endsWith', $value);
        });

        /**
         * Skip caching for this query.
         *
         * @return \ApiModelRelations\Query\ApiQueryBuilder
         */
        ApiQueryBuilder::macro('withoutCache', function () {
            $this->useCache = false;
            return $this;
        });

        /**
         * Set a specific cache TTL for this query.
         *
         * @param int $seconds
         * @return \ApiModelRelations\Query\ApiQueryBuilder
         */
        ApiQueryBuilder::macro('cacheFor', function (int $seconds) {
            $this->cacheTtl = $seconds;
            return $this;
        });
    }
}
