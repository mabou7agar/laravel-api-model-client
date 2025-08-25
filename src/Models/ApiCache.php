<?php

namespace MTechStack\LaravelApiModelClient\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Carbon\Carbon;

class ApiCache extends Model
{
    use HasFactory;

    protected $table = 'api_cache';

    protected $fillable = [
        'cacheable_type',
        'cacheable_id',
        'api_endpoint',
        'cache_key',
        'api_synced_at',
        'expires_at',
        'api_data',
        'metadata',
    ];

    protected $casts = [
        'api_data' => 'array',
        'metadata' => 'array',
        'api_synced_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    /**
     * Get the parent cacheable model (Product, Category, Order, etc.).
     */
    public function cacheable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Check if the cached data is fresh based on expires_at or TTL.
     *
     * @param int|null $ttlMinutes Time to live in minutes (uses expires_at if null)
     * @return bool
     */
    public function isFresh($ttlMinutes = null)
    {
        // Use expires_at if available and no TTL specified
        if (!$ttlMinutes && $this->expires_at) {
            return $this->expires_at->gt(Carbon::now());
        }

        // Fallback to api_synced_at + TTL
        if (!$this->api_synced_at) {
            return false;
        }

        $ttl = $ttlMinutes ?? config('api-cache.default_ttl', 60);
        return $this->api_synced_at->gt(Carbon::now()->subMinutes($ttl));
    }

    /**
     * Check if the cached data is stale and needs refresh.
     *
     * @param int|null $ttlMinutes Time to live in minutes
     * @return bool
     */
    public function isStale($ttlMinutes = null)
    {
        return !$this->isFresh($ttlMinutes);
    }

    /**
     * Check if the cache has expired.
     *
     * @return bool
     */
    public function isExpired()
    {
        if ($this->expires_at) {
            return $this->expires_at->lt(Carbon::now());
        }

        return false;
    }

    /**
     * Get data from the API response by key path (dot notation supported).
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getData($key = null, $default = null)
    {
        if (!$key) {
            return $this->api_data;
        }

        return data_get($this->api_data, $key, $default);
    }

    /**
     * Set cache expiration time.
     *
     * @param int $minutes
     * @return $this
     */
    public function setTtl($minutes)
    {
        $this->expires_at = Carbon::now()->addMinutes($minutes);
        return $this;
    }

    /**
     * Scope to get only fresh cached items.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int|null $ttlMinutes
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeFresh($query, $ttlMinutes = null)
    {
        $ttl = $ttlMinutes ?? config('api-cache.default_ttl', 60);
        
        return $query->where(function ($q) use ($ttl) {
            // Check expires_at first
            $q->where(function ($subQ) {
                $subQ->whereNotNull('expires_at')
                     ->where('expires_at', '>', Carbon::now());
            })
            // Fallback to api_synced_at + TTL
            ->orWhere(function ($subQ) use ($ttl) {
                $subQ->whereNull('expires_at')
                     ->where('api_synced_at', '>', Carbon::now()->subMinutes($ttl));
            });
        });
    }

    /**
     * Scope to get only stale cached items.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int|null $ttlMinutes
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeStale($query, $ttlMinutes = null)
    {
        $ttl = $ttlMinutes ?? config('api-cache.default_ttl', 60);
        
        return $query->where(function ($q) use ($ttl) {
            $q->where(function ($subQ) {
                // Expired based on expires_at
                $subQ->whereNotNull('expires_at')
                     ->where('expires_at', '<=', Carbon::now());
            })
            ->orWhere(function ($subQ) use ($ttl) {
                // Expired based on api_synced_at + TTL
                $subQ->whereNull('expires_at')
                     ->where(function ($innerQ) use ($ttl) {
                         $innerQ->whereNull('api_synced_at')
                                ->orWhere('api_synced_at', '<=', Carbon::now()->subMinutes($ttl));
                     });
            });
        });
    }

    /**
     * Scope to filter by cacheable type.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $type
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForType($query, $type)
    {
        return $query->where('cacheable_type', $type);
    }

    /**
     * Scope to filter by API endpoint.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $endpoint
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForEndpoint($query, $endpoint)
    {
        return $query->where('api_endpoint', $endpoint);
    }

    /**
     * Create or update cache entry from API data.
     *
     * @param string $type Model type (e.g., 'Product', 'Category')
     * @param int $id API entity ID
     * @param array $apiData Complete API response data
     * @param array $options Additional options (endpoint, cache_key, ttl, metadata)
     * @return static
     */
    public static function createOrUpdateFromApi($type, $id, array $apiData, array $options = [])
    {
        $cache = static::updateOrCreate(
            [
                'cacheable_type' => $type,
                'cacheable_id' => $id,
            ],
            [
                'api_endpoint' => $options['endpoint'] ?? null,
                'cache_key' => $options['cache_key'] ?? null,
                'api_data' => $apiData,
                'metadata' => $options['metadata'] ?? null,
                'api_synced_at' => Carbon::now(),
            ]
        );

        // Set TTL if provided
        if (isset($options['ttl'])) {
            $cache->setTtl($options['ttl'])->save();
        }

        return $cache;
    }

    /**
     * Get cached data for a specific type and ID.
     *
     * @param string $type
     * @param int $id
     * @return static|null
     */
    public static function getForTypeAndId($type, $id)
    {
        return static::where('cacheable_type', $type)
                    ->where('cacheable_id', $id)
                    ->first();
    }

    /**
     * Get fresh cached data for a specific type and ID.
     *
     * @param string $type
     * @param int $id
     * @param int|null $ttlMinutes
     * @return static|null
     */
    public static function getFreshForTypeAndId($type, $id, $ttlMinutes = null)
    {
        return static::where('cacheable_type', $type)
                    ->where('cacheable_id', $id)
                    ->fresh($ttlMinutes)
                    ->first();
    }

    /**
     * Clear cache for a specific type.
     *
     * @param string $type
     * @return int Number of deleted records
     */
    public static function clearForType($type)
    {
        return static::where('cacheable_type', $type)->delete();
    }

    /**
     * Clear stale cache entries.
     *
     * @param int|null $ttlMinutes
     * @return int Number of deleted records
     */
    public static function clearStale($ttlMinutes = null)
    {
        return static::stale($ttlMinutes)->delete();
    }
}
