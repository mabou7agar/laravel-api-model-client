<?php

namespace MTechStack\LaravelApiModelClient\Traits;

use MTechStack\LaravelApiModelClient\Services\HighPerformanceApiCache;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Queue;
use Carbon\Carbon;

/**
 * High-performance API cache trait optimized for heavy load scenarios.
 * Uses Redis for hot data, bulk operations, and async processing.
 */
trait UsesHighPerformanceCache
{
    /**
     * Cache service instance.
     */
    protected static $cacheService;

    /**
     * Get the high-performance cache service.
     *
     * @return HighPerformanceApiCache
     */
    protected static function getCacheService(): HighPerformanceApiCache
    {
        if (!static::$cacheService) {
            static::$cacheService = app(HighPerformanceApiCache::class);
        }

        return static::$cacheService;
    }

    /**
     * Get all items with high-performance caching.
     *
     * @param array $queryParams
     * @return Collection
     */
    public static function allFromApi($queryParams = []): Collection
    {
        $instance = new static();
        $type = $instance->getCacheableType();
        $cacheService = static::getCacheService();

        // Handle pagination efficiently
        if (isset($queryParams['limit'])) {
            $limit = (int) $queryParams['limit'];
            $offset = (int) ($queryParams['offset'] ?? 0);
            
            $cached = $cacheService->getPaginated($type, $limit, $offset);
            
            if ($cached->isNotEmpty()) {
                return $cached->map(fn($data) => $instance->newFromApiResponse($data));
            }
        }

        // For non-paginated or cache miss, use bulk API fetch
        return static::fetchAndCacheBulk($queryParams);
    }

    /**
     * Fetch data from API and cache in bulk operations.
     *
     * @param array $queryParams
     * @return Collection
     */
    protected static function fetchAndCacheBulk($queryParams = []): Collection
    {
        $instance = new static();
        $type = $instance->getCacheableType();
        
        try {
            // Fetch from API
            $items = parent::allFromApi();
            
            if ($items->isEmpty()) {
                return $items;
            }

            // Prepare bulk cache data
            $cacheData = [];
            foreach ($items as $item) {
                if (isset($item->id)) {
                    $cacheData[$item->id] = $item->getAttributes();
                }
            }

            // Bulk cache operation (non-blocking)
            if (!empty($cacheData)) {
                static::getCacheService()->putMany($type, $cacheData, $instance->getCacheTtl() * 60);
            }

            // Apply pagination to results if needed
            if (isset($queryParams['limit']) || isset($queryParams['offset'])) {
                $offset = (int) ($queryParams['offset'] ?? 0);
                $limit = isset($queryParams['limit']) ? (int) $queryParams['limit'] : null;
                
                if ($limit) {
                    $items = $items->slice($offset, $limit)->values();
                } else {
                    $items = $items->slice($offset)->values();
                }
            }

            return $items;

        } catch (\Exception $e) {
            // Log error and return empty collection
            \Log::error("High-performance cache fetch failed for {$type}", [
                'error' => $e->getMessage(),
                'query_params' => $queryParams
            ]);
            
            return collect();
        }
    }

    /**
     * Find multiple items by IDs with bulk cache lookup.
     *
     * @param array $ids
     * @return Collection
     */
    public static function findManyFromCache(array $ids): Collection
    {
        if (empty($ids)) {
            return collect();
        }

        $instance = new static();
        $type = $instance->getCacheableType();
        $cacheService = static::getCacheService();

        $cachedData = $cacheService->getMany($type, $ids);
        
        return $cachedData->map(function ($data) use ($instance) {
            return $instance->newFromApiResponse($data);
        });
    }

    /**
     * Warm up cache for this model type.
     *
     * @param int $batchSize
     * @return int Number of items cached
     */
    public static function warmUpCache(int $batchSize = 1000): int
    {
        $instance = new static();
        $type = $instance->getCacheableType();
        $cacheService = static::getCacheService();

        return $cacheService->warmUp($type, function ($limit, $offset) use ($instance) {
            // Fetch data in batches from API
            $query = $instance->newQuery();
            
            if (method_exists($query, 'limit') && method_exists($query, 'offset')) {
                $query->limit($limit)->offset($offset);
            }
            
            $items = $query->getFromApi();
            
            return $items->map(function ($item) {
                return $item->getAttributes();
            })->toArray();
            
        }, $batchSize);
    }

    /**
     * Invalidate cache for multiple items.
     *
     * @param array $ids
     * @return bool
     */
    public static function invalidateCache(array $ids): bool
    {
        if (empty($ids)) {
            return true;
        }

        $instance = new static();
        $type = $instance->getCacheableType();
        
        return static::getCacheService()->forgetMany($type, $ids);
    }

    /**
     * Get cache statistics for monitoring.
     *
     * @return array
     */
    public static function getCacheStats(): array
    {
        $instance = new static();
        $type = $instance->getCacheableType();
        
        return static::getCacheService()->getStats($type);
    }

    /**
     * Async cache refresh job.
     *
     * @param array $ids
     * @return void
     */
    public static function refreshCacheAsync(array $ids): void
    {
        if (empty($ids)) {
            return;
        }

        // Queue cache refresh job for background processing
        Queue::push(function ($job) use ($ids) {
            $instance = new static();
            $type = $instance->getCacheableType();
            
            try {
                // Fetch fresh data from API
                $freshData = [];
                foreach ($ids as $id) {
                    $item = static::findFromApi($id);
                    if ($item) {
                        $freshData[$id] = $item->getAttributes();
                    }
                }

                // Bulk update cache
                if (!empty($freshData)) {
                    static::getCacheService()->putMany($type, $freshData);
                }

            } catch (\Exception $e) {
                \Log::error("Async cache refresh failed for {$type}", [
                    'ids' => $ids,
                    'error' => $e->getMessage()
                ]);
            }

            $job->delete();
        });
    }

    /**
     * Cache cleanup for expired entries.
     *
     * @return int Number of cleaned entries
     */
    public static function cleanupCache(): int
    {
        return static::getCacheService()->cleanup();
    }

    /**
     * Get cache-first with API fallback strategy.
     *
     * @param array $queryParams
     * @return Collection
     */
    public static function getCacheFirst($queryParams = []): Collection
    {
        $instance = new static();
        $type = $instance->getCacheableType();
        
        // Try cache first
        if (isset($queryParams['limit'])) {
            $limit = (int) $queryParams['limit'];
            $offset = (int) ($queryParams['offset'] ?? 0);
            
            $cached = static::getCacheService()->getPaginated($type, $limit, $offset);
            
            if ($cached->isNotEmpty()) {
                return $cached->map(fn($data) => $instance->newFromApiResponse($data));
            }
        }

        // Fallback to API
        return static::fetchAndCacheBulk($queryParams);
    }

    /**
     * Get API-first with cache update strategy.
     *
     * @param array $queryParams
     * @return Collection
     */
    public static function getApiFirst($queryParams = []): Collection
    {
        // Always fetch from API first
        $items = static::fetchAndCacheBulk($queryParams);
        
        // Async cache update in background
        if ($items->isNotEmpty()) {
            $ids = $items->pluck('id')->filter()->toArray();
            static::refreshCacheAsync($ids);
        }

        return $items;
    }

    /**
     * Batch cache operations for multiple model instances.
     *
     * @param Collection $models
     * @return bool
     */
    public static function batchCache(Collection $models): bool
    {
        if ($models->isEmpty()) {
            return true;
        }

        $instance = $models->first();
        $type = $instance->getCacheableType();
        
        $cacheData = [];
        foreach ($models as $model) {
            if (isset($model->id)) {
                $cacheData[$model->id] = $model->getAttributes();
            }
        }

        return static::getCacheService()->putMany($type, $cacheData);
    }
}
