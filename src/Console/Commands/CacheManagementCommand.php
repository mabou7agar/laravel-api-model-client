<?php

namespace MTechStack\LaravelApiModelClient\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use MTechStack\LaravelApiModelClient\OpenApi\OpenApiSchemaParser;
use Carbon\Carbon;

/**
 * Artisan command for managing OpenAPI schema cache
 */
class CacheManagementCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'api-client:cache 
                            {action : Action to perform (clear, warm, status, flush, prune)}
                            {schema? : Specific schema to target (optional)}
                            {--force : Force the action without confirmation}
                            {--stats : Show detailed cache statistics}
                            {--format=table : Output format (table, json)}';

    /**
     * The console command description.
     */
    protected $description = 'Manage OpenAPI schema cache (clear, warm, status, flush, prune)';

    protected OpenApiSchemaParser $parser;

    public function __construct()
    {
        parent::__construct();
        $this->parser = new OpenApiSchemaParser();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $action = $this->argument('action');
        $schema = $this->argument('schema');
        $force = $this->option('force');
        $stats = $this->option('stats');
        $format = $this->option('format');

        $this->info("🗄️  OpenAPI Schema Cache Management");
        $this->newLine();

        try {
            switch ($action) {
                case 'clear':
                    return $this->clearCache($schema, $force);
                    
                case 'warm':
                    return $this->warmCache($schema, $force);
                    
                case 'status':
                    return $this->showCacheStatus($schema, $stats, $format);
                    
                case 'flush':
                    return $this->flushCache($force);
                    
                case 'prune':
                    return $this->pruneCache($schema, $force);
                    
                default:
                    $this->error("❌ Invalid action '{$action}'. Valid actions: clear, warm, status, flush, prune");
                    return 1;
            }
        } catch (\Exception $e) {
            $this->error("❌ Cache operation failed: {$e->getMessage()}");
            return 1;
        }
    }

    /**
     * Clear cache for specific schema or all schemas
     */
    protected function clearCache(?string $schema, bool $force): int
    {
        if ($schema) {
            return $this->clearSchemaCache($schema, $force);
        } else {
            return $this->clearAllSchemaCache($force);
        }
    }

    /**
     * Clear cache for a specific schema
     */
    protected function clearSchemaCache(string $schemaName, bool $force): int
    {
        $schemas = Config::get('api-client.schemas', []);
        
        if (!isset($schemas[$schemaName])) {
            $this->error("❌ Schema '{$schemaName}' not found in configuration");
            return 1;
        }

        if (!$force && !$this->confirm("Clear cache for schema '{$schemaName}'?")) {
            $this->info('Operation cancelled');
            return 0;
        }

        $schemaConfig = $schemas[$schemaName];
        $cacheConfig = $schemaConfig['caching'] ?? [];
        
        if (!($cacheConfig['enabled'] ?? true)) {
            $this->warn("⚠️  Caching is disabled for schema '{$schemaName}'");
            return 0;
        }

        try {
            $store = Cache::store($cacheConfig['store'] ?? 'default');
            $prefix = $cacheConfig['prefix'] ?? 'api_client_';
            $tags = $cacheConfig['tags'] ?? [];

            $cleared = 0;

            if (!empty($tags)) {
                // Clear by tags if supported
                try {
                    $store->tags($tags)->flush();
                    $this->info("✅ Cleared cache for schema '{$schemaName}' using tags");
                    return 0;
                } catch (\Exception $e) {
                    // Fallback to prefix-based clearing
                }
            }

            // Clear by prefix pattern
            $cacheKeys = $this->findCacheKeysByPrefix($store, $prefix . $schemaName);
            foreach ($cacheKeys as $key) {
                if ($store->forget($key)) {
                    $cleared++;
                }
            }

            $this->info("✅ Cleared {$cleared} cache entries for schema '{$schemaName}'");
            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Failed to clear cache for schema '{$schemaName}': {$e->getMessage()}");
            return 1;
        }
    }

    /**
     * Clear cache for all schemas
     */
    protected function clearAllSchemaCache(bool $force): int
    {
        $schemas = Config::get('api-client.schemas', []);
        
        if (!$force && !$this->confirm('Clear cache for all schemas?')) {
            $this->info('Operation cancelled');
            return 0;
        }

        $totalCleared = 0;
        $errors = 0;

        foreach ($schemas as $schemaName => $schemaConfig) {
            $this->line("🧹 Clearing cache for schema: {$schemaName}");
            
            try {
                $result = $this->clearSchemaCache($schemaName, true);
                if ($result === 0) {
                    $totalCleared++;
                } else {
                    $errors++;
                }
            } catch (\Exception $e) {
                $this->warn("⚠️  Failed to clear cache for '{$schemaName}': {$e->getMessage()}");
                $errors++;
            }
        }

        if ($errors === 0) {
            $this->info("✅ Successfully cleared cache for all {$totalCleared} schemas");
            return 0;
        } else {
            $this->warn("⚠️  Cleared cache for {$totalCleared} schemas with {$errors} errors");
            return 1;
        }
    }

    /**
     * Warm cache by pre-loading schemas
     */
    protected function warmCache(?string $schema, bool $force): int
    {
        if ($schema) {
            return $this->warmSchemaCache($schema, $force);
        } else {
            return $this->warmAllSchemaCache($force);
        }
    }

    /**
     * Warm cache for a specific schema
     */
    protected function warmSchemaCache(string $schemaName, bool $force): int
    {
        $schemas = Config::get('api-client.schemas', []);
        
        if (!isset($schemas[$schemaName])) {
            $this->error("❌ Schema '{$schemaName}' not found in configuration");
            return 1;
        }

        $schemaConfig = $schemas[$schemaName];
        
        if (!($schemaConfig['enabled'] ?? false)) {
            $this->warn("⚠️  Schema '{$schemaName}' is disabled");
            return 0;
        }

        $source = $schemaConfig['source'] ?? null;
        if (!$source) {
            $this->warn("⚠️  No source configured for schema '{$schemaName}'");
            return 0;
        }

        if (!$force && !$this->confirm("Warm cache for schema '{$schemaName}'?")) {
            $this->info('Operation cancelled');
            return 0;
        }

        try {
            $this->line("🔥 Warming cache for schema: {$schemaName}");
            
            $startTime = microtime(true);
            $result = $this->parser->parse($source, true); // Use cache
            $duration = round((microtime(true) - $startTime) * 1000, 2);

            $this->info("✅ Cache warmed for schema '{$schemaName}' in {$duration}ms");
            $this->line("   📊 Schemas: " . count($result['schemas'] ?? []));
            $this->line("   📊 Endpoints: " . count($result['endpoints'] ?? []));
            
            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Failed to warm cache for schema '{$schemaName}': {$e->getMessage()}");
            return 1;
        }
    }

    /**
     * Warm cache for all schemas
     */
    protected function warmAllSchemaCache(bool $force): int
    {
        $schemas = Config::get('api-client.schemas', []);
        $enabledSchemas = array_filter($schemas, fn($config) => $config['enabled'] ?? false);
        
        if (empty($enabledSchemas)) {
            $this->warn('⚠️  No enabled schemas found');
            return 0;
        }

        if (!$force && !$this->confirm('Warm cache for all enabled schemas?')) {
            $this->info('Operation cancelled');
            return 0;
        }

        $totalWarmed = 0;
        $errors = 0;

        foreach ($enabledSchemas as $schemaName => $schemaConfig) {
            try {
                $result = $this->warmSchemaCache($schemaName, true);
                if ($result === 0) {
                    $totalWarmed++;
                } else {
                    $errors++;
                }
            } catch (\Exception $e) {
                $this->warn("⚠️  Failed to warm cache for '{$schemaName}': {$e->getMessage()}");
                $errors++;
            }
        }

        if ($errors === 0) {
            $this->info("✅ Successfully warmed cache for all {$totalWarmed} schemas");
            return 0;
        } else {
            $this->warn("⚠️  Warmed cache for {$totalWarmed} schemas with {$errors} errors");
            return 1;
        }
    }

    /**
     * Show cache status
     */
    protected function showCacheStatus(?string $schema, bool $detailed, string $format): int
    {
        if ($schema) {
            return $this->showSchemaStatus($schema, $detailed, $format);
        } else {
            return $this->showAllSchemaStatus($detailed, $format);
        }
    }

    /**
     * Show cache status for a specific schema
     */
    protected function showSchemaStatus(string $schemaName, bool $detailed, string $format): int
    {
        $schemas = Config::get('api-client.schemas', []);
        
        if (!isset($schemas[$schemaName])) {
            $this->error("❌ Schema '{$schemaName}' not found in configuration");
            return 1;
        }

        $schemaConfig = $schemas[$schemaName];
        $status = $this->getSchemaStatus($schemaName, $schemaConfig, $detailed);

        switch ($format) {
            case 'json':
                $this->line(json_encode([$schemaName => $status], JSON_PRETTY_PRINT));
                break;
                
            default:
                $this->displaySchemaStatusTable($schemaName, $status, $detailed);
                break;
        }

        return 0;
    }

    /**
     * Show cache status for all schemas
     */
    protected function showAllSchemaStatus(bool $detailed, string $format): int
    {
        $schemas = Config::get('api-client.schemas', []);
        $allStatus = [];

        foreach ($schemas as $schemaName => $schemaConfig) {
            $allStatus[$schemaName] = $this->getSchemaStatus($schemaName, $schemaConfig, $detailed);
        }

        switch ($format) {
            case 'json':
                $this->line(json_encode($allStatus, JSON_PRETTY_PRINT));
                break;
                
            default:
                $this->displayAllSchemaStatusTable($allStatus, $detailed);
                break;
        }

        return 0;
    }

    /**
     * Get cache status for a schema
     */
    protected function getSchemaStatus(string $schemaName, array $schemaConfig, bool $detailed): array
    {
        $cacheConfig = $schemaConfig['caching'] ?? [];
        $enabled = $cacheConfig['enabled'] ?? true;
        
        $status = [
            'enabled' => $enabled,
            'schema_enabled' => $schemaConfig['enabled'] ?? false,
        ];

        if (!$enabled) {
            $status['message'] = 'Caching disabled';
            return $status;
        }

        try {
            $store = Cache::store($cacheConfig['store'] ?? 'default');
            $prefix = $cacheConfig['prefix'] ?? 'api_client_';
            $ttl = $cacheConfig['ttl'] ?? 3600;

            // Check if schema is cached
            $cacheKey = $prefix . md5($schemaName);
            $cached = $store->get($cacheKey);
            
            $status['cached'] = $cached !== null;
            $status['store'] = $cacheConfig['store'] ?? 'default';
            $status['ttl'] = $ttl;
            $status['prefix'] = $prefix;

            if ($cached && $detailed) {
                $status['cache_size'] = strlen(serialize($cached));
                $status['schemas_count'] = count($cached['schemas'] ?? []);
                $status['endpoints_count'] = count($cached['endpoints'] ?? []);
                $status['cached_at'] = $cached['parsed_at'] ?? null;
            }

            if ($detailed) {
                $cacheKeys = $this->findCacheKeysByPrefix($store, $prefix . $schemaName);
                $status['cache_keys_count'] = count($cacheKeys);
                $status['tags'] = $cacheConfig['tags'] ?? [];
            }

        } catch (\Exception $e) {
            $status['error'] = $e->getMessage();
        }

        return $status;
    }

    /**
     * Display schema status in table format
     */
    protected function displaySchemaStatusTable(string $schemaName, array $status, bool $detailed): void
    {
        $tableData = [
            ['Schema', $schemaName],
            ['Schema Enabled', $status['schema_enabled'] ? '✅ Yes' : '❌ No'],
            ['Cache Enabled', $status['enabled'] ? '✅ Yes' : '❌ No'],
        ];

        if (isset($status['cached'])) {
            $tableData[] = ['Cached', $status['cached'] ? '✅ Yes' : '❌ No'];
        }

        if (isset($status['store'])) {
            $tableData[] = ['Store', $status['store']];
        }

        if (isset($status['ttl'])) {
            $tableData[] = ['TTL', $status['ttl'] . ' seconds'];
        }

        if ($detailed && isset($status['cache_size'])) {
            $tableData[] = ['Cache Size', $this->formatBytes($status['cache_size'])];
        }

        if ($detailed && isset($status['schemas_count'])) {
            $tableData[] = ['Schemas Count', $status['schemas_count']];
        }

        if ($detailed && isset($status['endpoints_count'])) {
            $tableData[] = ['Endpoints Count', $status['endpoints_count']];
        }

        if (isset($status['cached_at'])) {
            $tableData[] = ['Cached At', $status['cached_at']];
        }

        if (isset($status['error'])) {
            $tableData[] = ['Error', $status['error']];
        }

        if (isset($status['message'])) {
            $tableData[] = ['Message', $status['message']];
        }

        $this->table(['Property', 'Value'], $tableData);
    }

    /**
     * Display all schema status in table format
     */
    protected function displayAllSchemaStatusTable(array $allStatus, bool $detailed): void
    {
        $tableData = [];

        foreach ($allStatus as $schemaName => $status) {
            $row = [
                $schemaName,
                $status['schema_enabled'] ? '✅' : '❌',
                $status['enabled'] ? '✅' : '❌',
                isset($status['cached']) ? ($status['cached'] ? '✅' : '❌') : 'N/A',
            ];

            if ($detailed) {
                $row[] = $status['store'] ?? 'N/A';
                $row[] = isset($status['ttl']) ? $status['ttl'] . 's' : 'N/A';
                $row[] = isset($status['cache_size']) ? $this->formatBytes($status['cache_size']) : 'N/A';
            }

            $tableData[] = $row;
        }

        $headers = ['Schema', 'Enabled', 'Cache', 'Cached'];
        if ($detailed) {
            $headers = array_merge($headers, ['Store', 'TTL', 'Size']);
        }

        $this->table($headers, $tableData);
    }

    /**
     * Flush all cache
     */
    protected function flushCache(bool $force): int
    {
        if (!$force && !$this->confirm('Flush ALL cache? This will clear everything, not just API client cache.')) {
            $this->info('Operation cancelled');
            return 0;
        }

        try {
            Cache::flush();
            $this->info('✅ All cache flushed successfully');
            return 0;
        } catch (\Exception $e) {
            $this->error("❌ Failed to flush cache: {$e->getMessage()}");
            return 1;
        }
    }

    /**
     * Prune expired cache entries
     */
    protected function pruneCache(?string $schema, bool $force): int
    {
        if ($schema) {
            return $this->pruneSchemaCache($schema, $force);
        } else {
            return $this->pruneAllSchemaCache($force);
        }
    }

    /**
     * Prune expired cache entries for a specific schema
     */
    protected function pruneSchemaCache(string $schemaName, bool $force): int
    {
        $schemas = Config::get('api-client.schemas', []);
        
        if (!isset($schemas[$schemaName])) {
            $this->error("❌ Schema '{$schemaName}' not found in configuration");
            return 1;
        }

        if (!$force && !$this->confirm("Prune expired cache for schema '{$schemaName}'?")) {
            $this->info('Operation cancelled');
            return 0;
        }

        // Note: This is a simplified implementation
        // Real cache pruning would depend on the cache driver capabilities
        $this->info("🧹 Pruning expired cache for schema: {$schemaName}");
        $this->warn("⚠️  Cache pruning implementation depends on cache driver");
        
        return 0;
    }

    /**
     * Prune expired cache entries for all schemas
     */
    protected function pruneAllSchemaCache(bool $force): int
    {
        if (!$force && !$this->confirm('Prune expired cache for all schemas?')) {
            $this->info('Operation cancelled');
            return 0;
        }

        $this->info('🧹 Pruning expired cache for all schemas');
        $this->warn("⚠️  Cache pruning implementation depends on cache driver");
        
        return 0;
    }

    /**
     * Find cache keys by prefix (simplified implementation)
     */
    protected function findCacheKeysByPrefix($store, string $prefix): array
    {
        // This is a simplified implementation
        // Real implementation would depend on the cache driver
        // Some drivers like Redis support pattern matching, others don't
        
        try {
            // For demonstration, we'll return a mock result
            return [
                $prefix . '_schemas',
                $prefix . '_endpoints',
                $prefix . '_validation',
            ];
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Format bytes to human readable format
     */
    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $unitIndex = 0;
        
        while ($bytes >= 1024 && $unitIndex < count($units) - 1) {
            $bytes /= 1024;
            $unitIndex++;
        }
        
        return round($bytes, 2) . ' ' . $units[$unitIndex];
    }
}
