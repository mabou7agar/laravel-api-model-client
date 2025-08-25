<?php

namespace MTechStack\LaravelApiModelClient\Traits;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

trait HasApiCache
{
    /**
     * Cache strategy for this model instance.
     * Can be overridden per model or per request.
     */
    protected $cacheStrategy = null;

    /**
     * TTL for this model type in minutes.
     */
    protected $cacheTtl = null;

    /**
     * Get the cache strategy for this model.
     *
     * @return string
     */
    public function getCacheStrategy()
    {
        if ($this->cacheStrategy) {
            return $this->cacheStrategy;
        }

        $modelType = $this->getCacheableType();
        
        // Check if this type should always be real-time
        if (in_array($modelType, config('api-cache.real_time_types', []))) {
            return 'api_only';
        }

        return config('api-cache.default_strategy', 'hybrid');
    }

    /**
     * Get the TTL for this model type.
     *
     * @return int
     */
    public function getCacheTtl()
    {
        if ($this->cacheTtl) {
            return $this->cacheTtl;
        }

        $modelType = $this->getCacheableType();
        $ttlByType = config('api-cache.ttl_by_type', []);
        
        return $ttlByType[$modelType] ?? config('api-cache.default_ttl', 60);
    }

    /**
     * Get the cacheable type name for this model.
     *
     * @return string
     */
    public function getCacheableType()
    {
        return class_basename(static::class);
    }

    /**
     * Set cache strategy for this instance.
     *
     * @param string $strategy
     * @return $this
     */
    public function setCacheStrategy($strategy)
    {
        $this->cacheStrategy = $strategy;
        return $this;
    }

    /**
     * Set cache TTL for this instance.
     *
     * @param int $minutes
     * @return $this
     */
    public function setCacheTtl($minutes)
    {
        $this->cacheTtl = $minutes;
        return $this;
    }

    /**
     * Get cached data for this model instance.
     *
     * @return \App\Models\ApiCache|null
     */
    public function getCacheEntry()
    {
        if (!isset($this->id)) {
            return null;
        }

        $apiCacheClass = app()->bound('ApiCache') ? app('ApiCache') : '\MTechStack\LaravelApiModelClient\Models\ApiCache';
        return $apiCacheClass::getForTypeAndId($this->getCacheableType(), $this->id);
    }

    /**
     * Get fresh cached data for this model instance.
     *
     * @return \App\Models\ApiCache|null
     */
    public function getFreshCacheEntry()
    {
        if (!isset($this->id)) {
            return null;
        }

        $apiCacheClass = app()->bound('ApiCache') ? app('ApiCache') : '\MTechStack\LaravelApiModelClient\Models\ApiCache';
        return $apiCacheClass::getFreshForTypeAndId(
            $this->getCacheableType(), 
            $this->id, 
            $this->getCacheTtl()
        );
    }

    /**
     * Cache this model's data.
     *
     * @param array $apiData
     * @param array $options
     * @return \App\Models\ApiCache
     */
    public function cacheApiData(array $apiData, array $options = [])
    {
        if (!isset($this->id)) {
            throw new \InvalidArgumentException('Model must have an ID to be cached');
        }

        $defaultOptions = [
            'endpoint' => $this->apiEndpoint ?? null,
            'ttl' => $this->getCacheTtl(),
            'metadata' => [
                'model_class' => static::class,
                'cached_at' => Carbon::now()->toISOString(),
            ],
        ];

        $options = array_merge($defaultOptions, $options);

        $apiCacheClass = app()->bound('ApiCache') ? app('ApiCache') : '\MTechStack\LaravelApiModelClient\Models\ApiCache';
        return $apiCacheClass::createOrUpdateFromApi(
            $this->getCacheableType(),
            $this->id,
            $apiData,
            $options
        );
    }

    /**
     * Override allFromApi to support hybrid caching with pagination.
     *
     * @param array $queryParams Query parameters (limit, offset, etc.)
     * @return Collection
     */
    public static function allFromApi($queryParams = [])
    {
        $instance = new static();
        $strategy = $instance->getCacheStrategy();

        switch ($strategy) {
            case 'cache_only':
                return static::allFromCache($queryParams);
                
            case 'api_only':
                return static::allFromApiOnly($queryParams);
                
            case 'cache_first':
                $cached = static::allFromCache($queryParams);
                return $cached->isNotEmpty() ? $cached : static::allFromApiOnly($queryParams);
                
            case 'api_first':
                try {
                    return static::allFromApiOnly($queryParams);
                } catch (\Exception $e) {
                    return static::allFromCache($queryParams);
                }
                
            case 'hybrid':
            default:
                return static::allFromHybrid($queryParams);
        }
    }

    /**
     * Get all items from cache only with pagination support.
     *
     * @param array $queryParams Query parameters (limit, offset, etc.)
     * @return Collection
     */
    public static function allFromCache($queryParams = [])
    {
        $instance = new static();
        $apiCacheClass = app()->bound('ApiCache') ? app('ApiCache') : '\MTechStack\LaravelApiModelClient\Models\ApiCache';
        $cacheQuery = $apiCacheClass::forType($instance->getCacheableType())
                                ->fresh($instance->getCacheTtl());
        
        // Apply pagination to cache query
        if (isset($queryParams['limit']) && $queryParams['limit'] > 0) {
            $cacheQuery->limit($queryParams['limit']);
        }
        if (isset($queryParams['offset']) && $queryParams['offset'] > 0) {
            $cacheQuery->offset($queryParams['offset']);
        }
        
        $cacheEntries = $cacheQuery->get();

        return $cacheEntries->map(function ($cache) {
            $instance = new static();
            return $instance->newFromApiResponse($cache->api_data);
        });
    }

    /**
     * Get all items from API only (no cache) with pagination support.
     *
     * @param array $queryParams Query parameters (limit, offset, etc.)
     * @return Collection
     */
    public static function allFromApiOnly($queryParams = [])
    {
        // Call the original parent method to avoid recursion
        $items = parent::allFromApi();
        
        // Apply pagination to the results if specified
        if (!empty($queryParams['limit']) || !empty($queryParams['offset'])) {
            $offset = $queryParams['offset'] ?? 0;
            $limit = $queryParams['limit'] ?? null;
            
            if ($limit) {
                $items = $items->slice($offset, $limit)->values();
            } else {
                $items = $items->slice($offset)->values();
            }
        }
        
        // Cache the results for future use
        $instance = new static();
        foreach ($items as $item) {
            if (isset($item->id) && is_array($item->getAttributes())) {
                $item->cacheApiData($item->getAttributes());
            }
        }
        
        return $items;
    }

    /**
     * Get all items using hybrid strategy with pagination support.
     *
     * @param array $queryParams Query parameters (limit, offset, etc.)
     * @return Collection
     */
    public static function allFromHybrid($queryParams = [])
    {
        $instance = new static();
        
        // For paginated requests, prefer API to ensure accurate pagination
        if (!empty($queryParams['limit']) || !empty($queryParams['offset'])) {
            return static::allFromApiOnly($queryParams);
        }
        
        // Try to get fresh cached data first for non-paginated requests
        $cached = static::allFromCache($queryParams);
        
        if ($cached->isNotEmpty()) {
            // Log cache hit if debugging enabled
            if (config('api-cache.debug.log_hits')) {
                \Log::info("API Cache HIT for {$instance->getCacheableType()}::all()");
            }
            return $cached;
        }
        
        // Cache miss - fetch from API
        if (config('api-cache.debug.log_misses')) {
            \Log::info("API Cache MISS for {$instance->getCacheableType()}::all() - fetching from API");
        }
        
        return static::allFromApiOnly();
    }

    /**
     * Override findFromApi to support hybrid caching.
     *
     * @param int $id
     * @return static|null
     */
    public static function findFromApi($id)
    {
        $instance = new static();
        $instance->id = $id;
        $strategy = $instance->getCacheStrategy();

        switch ($strategy) {
            case 'cache_only':
                return static::findFromCache($id);
                
            case 'api_only':
                return static::findFromApiOnly($id);
                
            case 'cache_first':
                $cached = static::findFromCache($id);
                return $cached ?: static::findFromApiOnly($id);
                
            case 'api_first':
                try {
                    return static::findFromApiOnly($id);
                } catch (\Exception $e) {
                    return static::findFromCache($id);
                }
                
            case 'hybrid':
            default:
                return static::findFromHybrid($id);
        }
    }

    /**
     * Find item from cache only.
     *
     * @param int $id
     * @return static|null
     */
    public static function findFromCache($id)
    {
        $instance = new static();
        $apiCacheClass = app()->bound('ApiCache') ? app('ApiCache') : '\MTechStack\LaravelApiModelClient\Models\ApiCache';
        $cache = $apiCacheClass::getFreshForTypeAndId(
            $instance->getCacheableType(), 
            $id, 
            $instance->getCacheTtl()
        );

        if ($cache) {
            $instance = new static();
            return $instance->newFromApiResponse($cache->api_data);
        }
        return null;
    }

    /**
     * Find item from API only (no cache).
     *
     * @param int $id
     * @return static|null
     */
    public static function findFromApiOnly($id)
    {
        // Call the original parent method
        $item = parent::findFromApi($id);
        
        // Cache the result for future use
        if ($item && isset($item->id) && is_array($item->getAttributes())) {
            $item->cacheApiData($item->getAttributes());
        }
        
        return $item;
    }

    /**
     * Find item using hybrid strategy.
     *
     * @param int $id
     * @return static|null
     */
    public static function findFromHybrid($id)
    {
        $instance = new static();
        
        // Try to get fresh cached data first
        $cached = static::findFromCache($id);
        
        if ($cached) {
            // Log cache hit if debugging enabled
            if (config('api-cache.debug.log_hits')) {
                \Log::info("API Cache HIT for {$instance->getCacheableType()}::find({$id})");
            }
            return $cached;
        }
        
        // Cache miss - fetch from API
        if (config('api-cache.debug.log_misses')) {
            \Log::info("API Cache MISS for {$instance->getCacheableType()}::find({$id}) - fetching from API");
        }
        
        return static::findFromApiOnly($id);
    }

    /**
     * Force fresh data from API (bypass cache).
     *
     * @return static
     */
    public static function freshApi()
    {
        $instance = new static();
        return $instance->setCacheStrategy('api_only');
    }

    /**
     * Use cached data only (offline mode).
     *
     * @return static
     */
    public static function cachedOnly()
    {
        $instance = new static();
        return $instance->setCacheStrategy('cache_only');
    }

    /**
     * Sync data from API and update cache.
     *
     * @return static
     */
    public static function syncApi()
    {
        $instance = new static();
        return $instance->setCacheStrategy('api_first');
    }

    /**
     * Clear cache for this model type.
     *
     * @return int
     */
    public static function clearCache()
    {
        $instance = new static();
        $apiCacheClass = app()->bound('ApiCache') ? app('ApiCache') : '\MTechStack\LaravelApiModelClient\Models\ApiCache';
        return $apiCacheClass::clearForType($instance->getCacheableType());
    }

    /**
     * Polymorphic relationship to cache entries.
     */
    public function cacheEntries()
    {
        $apiCacheClass = app()->bound('ApiCache') ? app('ApiCache') : '\MTechStack\LaravelApiModelClient\Models\ApiCache';
        return $this->morphMany($apiCacheClass, 'cacheable');
    }
}
