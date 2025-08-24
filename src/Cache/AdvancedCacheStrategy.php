<?php

namespace MTechStack\LaravelApiModelClient\Cache;

use MTechStack\LaravelApiModelClient\Contracts\CacheStrategyInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class AdvancedCacheStrategy implements CacheStrategyInterface
{
    /**
     * The cache store to use.
     *
     * @var string|null
     */
    protected $store;

    /**
     * The cache TTL in seconds.
     *
     * @var int|null
     */
    protected $ttl;

    /**
     * The cache prefix.
     *
     * @var string
     */
    protected $prefix;

    /**
     * Whether to use cache tags.
     *
     * @var bool
     */
    protected $useTags;

    /**
     * The cache tags to use.
     *
     * @var array
     */
    protected $tags = [];

    /**
     * Create a new advanced cache strategy instance.
     *
     * @param  string|null  $store
     * @param  int|null  $ttl
     * @param  string  $prefix
     * @param  bool  $useTags
     * @param  array  $tags
     * @return void
     */
    public function __construct($store = null, $ttl = null, $prefix = 'api_model_', $useTags = true, array $tags = [])
    {
        $this->store = $store;
        $this->ttl = $ttl;
        $this->prefix = $prefix;
        $this->useTags = $useTags;
        $this->tags = $tags;
    }

    /**
     * Get an item from the cache.
     *
     * @param  string  $key
     * @param  mixed  $default
     * @return mixed
     */
    public function get($key, $default = null)
    {
        $cacheKey = $this->generateCacheKey($key);
        
        $cache = $this->getCacheInstance();
        
        return $cache->get($cacheKey, $default);
    }

    /**
     * Store an item in the cache.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @param  int|null  $ttl
     * @return bool
     */
    public function put($key, $value, $ttl = null)
    {
        $cacheKey = $this->generateCacheKey($key);
        $ttl = $ttl ?? $this->ttl;
        
        $cache = $this->getCacheInstance();
        
        if (is_null($ttl)) {
            return $cache->forever($cacheKey, $value);
        }
        
        return $cache->put($cacheKey, $value, $ttl);
    }

    /**
     * Remove an item from the cache.
     *
     * @param  string  $key
     * @return bool
     */
    public function forget($key)
    {
        $cacheKey = $this->generateCacheKey($key);
        
        $cache = $this->getCacheInstance();
        
        return $cache->forget($cacheKey);
    }

    /**
     * Remove all items from the cache.
     *
     * @return bool
     */
    public function flush()
    {
        $cache = $this->getCacheInstance();
        
        if ($this->useTags && $this->supportsTagging()) {
            return $cache->flush();
        }
        
        return Cache::store($this->store)->flush();
    }

    /**
     * Determine if an item exists in the cache.
     *
     * @param  string  $key
     * @return bool
     */
    public function has($key)
    {
        $cacheKey = $this->generateCacheKey($key);
        
        $cache = $this->getCacheInstance();
        
        return $cache->has($cacheKey);
    }

    /**
     * Get an item from the cache, or execute the given Closure and store the result.
     *
     * @param  string  $key
     * @param  int|null  $ttl
     * @param  \Closure  $callback
     * @return mixed
     */
    public function remember($key, $ttl, \Closure $callback)
    {
        $cacheKey = $this->generateCacheKey($key);
        $ttl = $ttl ?? $this->ttl;
        
        $cache = $this->getCacheInstance();
        
        return $cache->remember($cacheKey, $ttl, $callback);
    }

    /**
     * Get the cache instance with tags if supported.
     *
     * @return \Illuminate\Contracts\Cache\Repository
     */
    protected function getCacheInstance()
    {
        $cache = Cache::store($this->store);
        
        if ($this->useTags && $this->supportsTagging()) {
            $tags = array_merge($this->tags, ['api_model_relations']);
            return $cache->tags($tags);
        }
        
        return $cache;
    }

    /**
     * Generate a cache key.
     *
     * @param  string  $key
     * @return string
     */
    protected function generateCacheKey($key)
    {
        return $this->prefix . $key;
    }

    /**
     * Determine if the cache store supports tagging.
     *
     * @return bool
     */
    protected function supportsTagging()
    {
        $store = $this->store ?? config('cache.default');
        
        return in_array($store, ['redis', 'memcached', 'dynamodb', 'array']);
    }

    /**
     * Set the cache tags.
     *
     * @param  array  $tags
     * @return $this
     */
    public function setTags(array $tags)
    {
        $this->tags = $tags;
        
        return $this;
    }

    /**
     * Add a cache tag.
     *
     * @param  string  $tag
     * @return $this
     */
    public function addTag($tag)
    {
        $this->tags[] = $tag;
        
        return $this;
    }

    /**
     * Set the cache TTL.
     *
     * @param  int|null  $ttl
     * @return $this
     */
    public function setTtl($ttl)
    {
        $this->ttl = $ttl;
        
        return $this;
    }

    /**
     * Set the cache store.
     *
     * @param  string|null  $store
     * @return $this
     */
    public function setStore($store)
    {
        $this->store = $store;
        
        return $this;
    }

    /**
     * Set the cache prefix.
     *
     * @param  string  $prefix
     * @return $this
     */
    public function setPrefix($prefix)
    {
        $this->prefix = $prefix;
        
        return $this;
    }

    /**
     * Enable or disable cache tags.
     *
     * @param  bool  $useTags
     * @return $this
     */
    public function useTags($useTags = true)
    {
        $this->useTags = $useTags;
        
        return $this;
    }

    /**
     * Flush cache by tags.
     *
     * @param  array  $tags
     * @return bool
     */
    public function flushByTags(array $tags)
    {
        if (!$this->useTags || !$this->supportsTagging()) {
            return false;
        }
        
        return Cache::store($this->store)->tags($tags)->flush();
    }
}
