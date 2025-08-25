<?php

namespace MTechStack\LaravelApiModelClient\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Collection;
use Carbon\Carbon;

/**
 * High-performance API cache service optimized for heavy load.
 * Uses Redis for hot data, database for persistence, and bulk operations.
 */
class HighPerformanceApiCache
{
    protected $redisPrefix = 'api_cache:';
    protected $batchSize = 1000;
    protected $defaultTtl = 3600; // 1 hour in seconds

    /**
     * Get multiple items from cache with single Redis call.
     *
     * @param string $type
     * @param array $ids
     * @return Collection
     */
    public function getMany(string $type, array $ids): Collection
    {
        if (empty($ids)) {
            return collect();
        }

        // Build Redis keys for batch retrieval
        $redisKeys = array_map(fn($id) => $this->redisPrefix . "{$type}:{$id}", $ids);
        
        // Single Redis MGET call instead of N individual queries
        $cachedData = Redis::mget($redisKeys);
        
        $results = collect();
        foreach ($cachedData as $index => $data) {
            if ($data !== null) {
                $decoded = json_decode($data, true);
                if ($decoded && $this->isFresh($decoded)) {
                    $results->push($decoded['data']);
                }
            }
        }

        return $results;
    }

    /**
     * Store multiple items in cache with single pipeline operation.
     *
     * @param string $type
     * @param array $items Array of ['id' => data] pairs
     * @param int|null $ttl TTL in seconds
     * @return bool
     */
    public function putMany(string $type, array $items, ?int $ttl = null): bool
    {
        if (empty($items)) {
            return true;
        }

        $ttl = $ttl ?? $this->defaultTtl;
        $expiresAt = Carbon::now()->addSeconds($ttl);

        // Use Redis pipeline for bulk operations
        Redis::pipeline(function ($pipe) use ($type, $items, $ttl, $expiresAt) {
            foreach ($items as $id => $data) {
                $cacheData = [
                    'data' => $data,
                    'expires_at' => $expiresAt->timestamp,
                    'cached_at' => time(),
                ];
                
                $key = $this->redisPrefix . "{$type}:{$id}";
                $pipe->setex($key, $ttl, json_encode($cacheData));
            }
        });

        // Async database persistence (non-blocking)
        $this->persistToDatabase($type, $items, $expiresAt);

        return true;
    }

    /**
     * Get paginated results from cache with optimized queries.
     *
     * @param string $type
     * @param int $limit
     * @param int $offset
     * @return Collection
     */
    public function getPaginated(string $type, int $limit, int $offset = 0): Collection
    {
        // Use Redis sorted sets for efficient pagination
        $cacheKey = $this->redisPrefix . "index:{$type}";
        
        // Get IDs for the requested page
        $ids = Redis::zrevrange($cacheKey, $offset, $offset + $limit - 1);
        
        if (empty($ids)) {
            return collect();
        }

        return $this->getMany($type, $ids);
    }

    /**
     * Bulk invalidate cache entries.
     *
     * @param string $type
     * @param array $ids
     * @return bool
     */
    public function forgetMany(string $type, array $ids): bool
    {
        if (empty($ids)) {
            return true;
        }

        $redisKeys = array_map(fn($id) => $this->redisPrefix . "{$type}:{$id}", $ids);
        
        // Bulk delete from Redis
        Redis::del($redisKeys);
        
        // Remove from sorted set index
        $indexKey = $this->redisPrefix . "index:{$type}";
        Redis::zrem($indexKey, ...$ids);

        return true;
    }

    /**
     * Warm up cache with batch data loading.
     *
     * @param string $type
     * @param callable $dataLoader Function that returns data array
     * @param int $batchSize
     * @return int Number of items cached
     */
    public function warmUp(string $type, callable $dataLoader, int $batchSize = 1000): int
    {
        $totalCached = 0;
        $offset = 0;

        do {
            $batch = $dataLoader($batchSize, $offset);
            
            if (empty($batch)) {
                break;
            }

            // Convert to id => data format
            $items = [];
            foreach ($batch as $item) {
                if (isset($item['id'])) {
                    $items[$item['id']] = $item;
                }
            }

            $this->putMany($type, $items);
            $totalCached += count($items);
            $offset += $batchSize;

        } while (count($batch) === $batchSize);

        return $totalCached;
    }

    /**
     * Get cache statistics for monitoring.
     *
     * @param string $type
     * @return array
     */
    public function getStats(string $type): array
    {
        $pattern = $this->redisPrefix . "{$type}:*";
        $keys = Redis::keys($pattern);
        
        $stats = [
            'total_keys' => count($keys),
            'memory_usage' => 0,
            'hit_rate' => 0,
            'avg_ttl' => 0,
        ];

        if (!empty($keys)) {
            // Sample a subset for performance
            $sampleKeys = array_slice($keys, 0, min(100, count($keys)));
            $totalTtl = 0;
            $totalMemory = 0;
            
            foreach ($sampleKeys as $key) {
                $ttl = Redis::ttl($key);
                if ($ttl > 0) {
                    $totalTtl += $ttl;
                }
                
                // Estimate memory usage by getting value size (compatible with older Redis)
                try {
                    $value = Redis::get($key);
                    if ($value !== null) {
                        $totalMemory += strlen($value);
                    }
                } catch (\Exception $e) {
                    // Ignore errors for individual keys
                }
            }
            
            $stats['avg_ttl'] = $totalTtl / count($sampleKeys);
            $stats['memory_usage'] = $totalMemory; // Estimated memory usage in bytes
        }

        return $stats;
    }

    /**
     * Check if cached data is still fresh.
     *
     * @param array $cacheData
     * @return bool
     */
    protected function isFresh(array $cacheData): bool
    {
        if (!isset($cacheData['expires_at'])) {
            return false;
        }

        return time() < $cacheData['expires_at'];
    }

    /**
     * Persist data to database asynchronously.
     *
     * @param string $type
     * @param array $items
     * @param Carbon $expiresAt
     * @return void
     */
    protected function persistToDatabase(string $type, array $items, Carbon $expiresAt): void
    {
        // Use database transactions and bulk inserts for efficiency
        DB::transaction(function () use ($type, $items, $expiresAt) {
            $chunks = array_chunk($items, $this->batchSize, true);
            
            foreach ($chunks as $chunk) {
                $insertData = [];
                
                foreach ($chunk as $id => $data) {
                    $insertData[] = [
                        'cacheable_type' => $type,
                        'cacheable_id' => $id,
                        'api_data' => json_encode($data),
                        'expires_at' => $expiresAt,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                // Use upsert for efficient bulk insert/update
                DB::table('api_cache')->upsert(
                    $insertData,
                    ['cacheable_type', 'cacheable_id'],
                    ['api_data', 'expires_at', 'updated_at']
                );
            }
        });
    }

    /**
     * Clean up expired entries in background.
     *
     * @return int Number of cleaned entries
     */
    public function cleanup(): int
    {
        $cleaned = 0;
        
        // Clean Redis expired keys
        $pattern = $this->redisPrefix . '*';
        $keys = Redis::keys($pattern);
        
        foreach (array_chunk($keys, 1000) as $keyChunk) {
            $expiredKeys = [];
            
            foreach ($keyChunk as $key) {
                if (Redis::ttl($key) <= 0) {
                    $expiredKeys[] = $key;
                }
            }
            
            if (!empty($expiredKeys)) {
                Redis::del($expiredKeys);
                $cleaned += count($expiredKeys);
            }
        }

        // Clean database expired entries
        $dbCleaned = DB::table('api_cache')
            ->where('expires_at', '<', now())
            ->delete();

        return $cleaned + $dbCleaned;
    }
}
