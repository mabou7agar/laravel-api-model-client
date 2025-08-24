<?php

namespace MTechStack\LaravelApiModelClient\Traits;

use MTechStack\LaravelApiModelClient\Cache\AdvancedCacheStrategy;
use MTechStack\LaravelApiModelClient\Contracts\CacheStrategyInterface;
use Illuminate\Support\Facades\Cache;

/**
 * Trait for handling API model caching
 */
trait ApiModelCaching
{
    /**
     * Whether to cache API responses.
     *
     * @var bool
     */
    protected $cacheEnabled = true;

    /**
     * The cache TTL in seconds.
     *
     * @var int|null
     */
    protected $cacheTtl;

    /**
     * The cache store to use.
     *
     * @var string|null
     */
    protected $cacheStore;

    /**
     * The cache strategy instance.
     *
     * @var \MTechStack\LaravelApiModelClient\Contracts\CacheStrategyInterface|null
     */
    protected $cacheStrategy;

    /**
     * The cache tags to use.
     *
     * @var array
     */
    protected $cacheTags = [];

    /**
     * Determine if caching is enabled for this model.
     *
     * @return bool
     */
    public function isCacheEnabled()
    {
        return $this->cacheEnabled && config('api-model-relations.cache.enabled', true);
    }

    /**
     * Enable or disable caching for this model.
     *
     * @param  bool  $enabled
     * @return $this
     */
    public function setCacheEnabled($enabled = true)
    {
        $this->cacheEnabled = $enabled;

        return $this;
    }

    /**
     * Get the cache TTL in seconds.
     *
     * @return int|null
     */
    public function getCacheTtl()
    {
        return $this->cacheTtl ?? config('api-model-relations.cache.ttl');
    }

    /**
     * Set the cache TTL in seconds.
     *
     * @param  int|null  $ttl
     * @return $this
     */
    public function setCacheTtl($ttl)
    {
        $this->cacheTtl = $ttl;

        return $this;
    }

    /**
     * Get the cache store to use.
     *
     * @return string|null
     */
    public function getCacheStore()
    {
        return $this->cacheStore ?? config('api-model-relations.cache.store');
    }

    /**
     * Set the cache store to use.
     *
     * @param  string|null  $store
     * @return $this
     */
    public function setCacheStore($store)
    {
        $this->cacheStore = $store;

        return $this;
    }

    /**
     * Get the cache strategy instance.
     *
     * @return \MTechStack\LaravelApiModelClient\Contracts\CacheStrategyInterface
     */
    public function getCacheStrategy()
    {
        if ($this->cacheStrategy) {
            return $this->cacheStrategy;
        }

        $strategy = config('api-model-relations.cache.strategy');
        
        if ($strategy === 'advanced') {
            $this->cacheStrategy = new AdvancedCacheStrategy(
                $this->getCacheStore(),
                $this->getCacheTtl(),
                'api_model_' . strtolower(class_basename($this)) . '_',
                true,
                $this->getCacheTags()
            );
        } else {
            $this->cacheStrategy = app('api-cache-strategy');
        }

        return $this->cacheStrategy;
    }

    /**
     * Set the cache strategy instance.
     *
     * @param  \MTechStack\LaravelApiModelClient\Contracts\CacheStrategyInterface  $strategy
     * @return $this
     */
    public function setCacheStrategy(CacheStrategyInterface $strategy)
    {
        $this->cacheStrategy = $strategy;

        return $this;
    }

    /**
     * Get the cache tags to use.
     *
     * @return array
     */
    public function getCacheTags()
    {
        $modelTag = strtolower(class_basename($this));
        
        return array_merge([$modelTag], $this->cacheTags);
    }

    /**
     * Set the cache tags to use.
     *
     * @param  array  $tags
     * @return $this
     */
    public function setCacheTags(array $tags)
    {
        $this->cacheTags = $tags;

        return $this;
    }

    /**
     * Add a cache tag.
     *
     * @param  string  $tag
     * @return $this
     */
    public function addCacheTag($tag)
    {
        $this->cacheTags[] = $tag;

        return $this;
    }

    /**
     * Generate a cache key for a model.
     *
     * @param  string  $method
     * @param  mixed  $id
     * @return string
     */
    protected function generateCacheKey($method, $id = null)
    {
        $class = get_class($this);
        
        if ($id !== null) {
            return "{$method}_{$class}_{$id}";
        }
        
        return "{$method}_{$class}";
    }

    /**
     * Flush the cache for this model.
     *
     * @return bool
     */
    public function flushCache()
    {
        $strategy = $this->getCacheStrategy();
        
        if (method_exists($strategy, 'flushByTags')) {
            return $strategy->flushByTags($this->getCacheTags());
        }
        
        return $strategy->flush();
    }

    /**
     * Remember an item in the cache.
     *
     * @param  string  $key
     * @param  \Closure  $callback
     * @param  int|null  $ttl
     * @return mixed
     */
    protected function cacheRemember($key, \Closure $callback, $ttl = null)
    {
        if (!$this->isCacheEnabled()) {
            return $callback();
        }
        
        $strategy = $this->getCacheStrategy();
        
        return $strategy->remember($key, $ttl ?? $this->getCacheTtl(), $callback);
    }
}
