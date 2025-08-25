<?php

namespace Examples;

use MTechStack\LaravelApiModelClient\Models\ApiModel;
use MTechStack\LaravelApiModelClient\Traits\SyncWithApi;
use MTechStack\LaravelApiModelClient\Traits\HasApiCache;
use MTechStack\LaravelApiModelClient\Traits\UsesHighPerformanceCache;

/**
 * Example: Hybrid Data Source Usage
 * 
 * This example demonstrates how to use the hybrid data source system
 * that intelligently switches between database and API operations
 * based on configuration modes.
 */
class HybridProduct extends ApiModel
{
    use SyncWithApi, HasApiCache, UsesHighPerformanceCache {
        UsesHighPerformanceCache::allFromApi insteadof HasApiCache;
        HasApiCache::allFromApi as allFromBasicCache;
    }

    protected $apiEndpoint = 'api/v1/products';
    
    // Override data source mode for this specific model
    protected $dataSourceMode = 'hybrid'; // Can be: api_only, db_only, hybrid, api_first, dual_sync
    
    protected $fillable = [
        'id', 'name', 'sku', 'price', 'description', 'category_id'
    ];

    /**
     * Configure cache TTL (in minutes)
     */
    public function getCacheTtl(): int
    {
        return 60; // 1 hour
    }

    /**
     * Get cacheable type for polymorphic storage
     */
    public function getCacheableType(): string
    {
        return 'products';
    }
}

/**
 * Usage Examples for Different Hybrid Modes
 */
class HybridDataSourceExamples
{
    /**
     * Example 1: API Only Mode
     * All operations use API exclusively
     */
    public function apiOnlyMode()
    {
        // Set mode programmatically or via config
        config(['hybrid-data-source.models.product.data_source_mode' => 'api_only']);
        
        // All these operations will use API only
        $products = HybridProduct::all();           // Fetches from API
        $product = HybridProduct::find(1);          // Finds from API
        
        $newProduct = new HybridProduct([
            'name' => 'New Product',
            'sku' => 'NP001',
            'price' => 99.99
        ]);
        $newProduct->save();                        // Saves to API only
        
        $product->delete();                         // Deletes from API only
        
        echo "API Only Mode: All operations use API exclusively\n";
    }

    /**
     * Example 2: Database Only Mode
     * All operations use database exclusively
     */
    public function databaseOnlyMode()
    {
        config(['hybrid-data-source.models.product.data_source_mode' => 'db_only']);
        
        // All these operations will use database only
        $products = HybridProduct::all();           // Fetches from database
        $product = HybridProduct::find(1);          // Finds from database
        
        $newProduct = HybridProduct::create([
            'name' => 'DB Product',
            'sku' => 'DB001',
            'price' => 49.99
        ]);                                         // Creates in database only
        
        echo "Database Only Mode: All operations use database exclusively\n";
    }

    /**
     * Example 3: Hybrid Mode (Default)
     * Check database first, fallback to API
     */
    public function hybridMode()
    {
        config(['hybrid-data-source.models.product.data_source_mode' => 'hybrid']);
        
        // These operations will try database first, then API
        $products = HybridProduct::all();           // DB first, API fallback
        $product = HybridProduct::find(1);          // DB first, API fallback
        
        // If found in API, automatically syncs to database for future queries
        $apiProduct = HybridProduct::find(999);     // Not in DB, fetched from API, synced to DB
        
        echo "Hybrid Mode: Database first with API fallback and auto-sync\n";
    }

    /**
     * Example 4: API First Mode
     * Check API first, sync to database
     */
    public function apiFirstMode()
    {
        config(['hybrid-data-source.models.product.data_source_mode' => 'api_first']);
        
        // These operations will try API first, then sync to database
        $products = HybridProduct::all();           // API first, sync to DB
        $product = HybridProduct::find(1);          // API first, sync to DB
        
        $newProduct = HybridProduct::create([
            'name' => 'API First Product',
            'sku' => 'AF001',
            'price' => 79.99
        ]);                                         // Creates in API, syncs to DB
        
        echo "API First Mode: API first with database sync\n";
    }

    /**
     * Example 5: Dual Sync Mode
     * Keep both database and API in sync
     */
    public function dualSyncMode()
    {
        config(['hybrid-data-source.models.product.data_source_mode' => 'dual_sync']);
        
        // These operations will work with both sources simultaneously
        $products = HybridProduct::all();           // Syncs both sources, returns most current
        $product = HybridProduct::find(1);          // Compares both sources, returns newest
        
        $newProduct = HybridProduct::create([
            'name' => 'Dual Sync Product',
            'sku' => 'DS001',
            'price' => 129.99
        ]);                                         // Creates in both sources
        
        $product->name = 'Updated Name';
        $product->save();                           // Saves to both sources
        
        $product->delete();                         // Deletes from both sources
        
        echo "Dual Sync Mode: Both sources kept in perfect sync\n";
    }

    /**
     * Example 6: Environment-Based Configuration
     */
    public function environmentBasedConfig()
    {
        // Different modes for different environments
        switch (app()->environment()) {
            case 'local':
                // Development: Use hybrid for flexibility
                config(['hybrid-data-source.global_mode' => 'hybrid']);
                break;
                
            case 'testing':
                // Testing: Use database only for consistency
                config(['hybrid-data-source.global_mode' => 'db_only']);
                break;
                
            case 'staging':
                // Staging: Use API first to test API integration
                config(['hybrid-data-source.global_mode' => 'api_first']);
                break;
                
            case 'production':
                // Production: Use dual sync for maximum reliability
                config(['hybrid-data-source.global_mode' => 'dual_sync']);
                break;
        }
        
        echo "Environment-based configuration applied\n";
    }

    /**
     * Example 7: Model-Specific Configuration
     */
    public function modelSpecificConfig()
    {
        // Different models can have different modes
        config([
            'hybrid-data-source.models.product.data_source_mode' => 'api_first',    // Products from API
            'hybrid-data-source.models.category.data_source_mode' => 'hybrid',      // Categories hybrid
            'hybrid-data-source.models.order.data_source_mode' => 'dual_sync',     // Orders dual sync
            'hybrid-data-source.models.customer.data_source_mode' => 'api_only',   // Customers API only
        ]);
        
        echo "Model-specific configurations applied\n";
    }

    /**
     * Example 8: Error Handling and Fallbacks
     */
    public function errorHandlingExample()
    {
        config(['hybrid-data-source.models.product.data_source_mode' => 'hybrid']);
        
        try {
            // If API is down, hybrid mode will gracefully fallback to database
            $products = HybridProduct::all();
            
            // If database is down, hybrid mode will use API
            $product = HybridProduct::find(1);
            
            echo "Graceful fallback handling working correctly\n";
        } catch (\Exception $e) {
            echo "Error handled: " . $e->getMessage() . "\n";
        }
    }

    /**
     * Example 9: Performance Monitoring
     */
    public function performanceMonitoring()
    {
        // Enable monitoring
        config([
            'hybrid-data-source.monitoring.log_operations' => true,
            'hybrid-data-source.monitoring.metrics_enabled' => true,
        ]);
        
        $startTime = microtime(true);
        
        $products = HybridProduct::all();
        
        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000; // Convert to milliseconds
        
        echo "Operation completed in {$executionTime}ms\n";
        echo "Retrieved {$products->count()} products\n";
    }

    /**
     * Example 10: Advanced Query Operations
     */
    public function advancedQueryOperations()
    {
        config(['hybrid-data-source.models.product.data_source_mode' => 'hybrid']);
        
        // Standard Eloquent methods work seamlessly
        $expensiveProducts = HybridProduct::where('price', '>', 100)->get();
        $featuredProducts = HybridProduct::where('featured', true)->take(10)->get();
        $productsByCategory = HybridProduct::where('category_id', 5)->paginate(20);
        
        // Relationships work as expected
        $product = HybridProduct::with('category', 'reviews')->find(1);
        
        // Bulk operations
        HybridProduct::whereIn('id', [1, 2, 3, 4, 5])->update(['featured' => true]);
        
        echo "Advanced query operations completed successfully\n";
    }

    /**
     * Run all examples
     */
    public function runAllExamples()
    {
        echo "=== Hybrid Data Source Examples ===\n\n";
        
        $this->apiOnlyMode();
        $this->databaseOnlyMode();
        $this->hybridMode();
        $this->apiFirstMode();
        $this->dualSyncMode();
        $this->environmentBasedConfig();
        $this->modelSpecificConfig();
        $this->errorHandlingExample();
        $this->performanceMonitoring();
        $this->advancedQueryOperations();
        
        echo "\n=== All examples completed successfully! ===\n";
    }
}

/**
 * Configuration Examples
 */
class ConfigurationExamples
{
    /**
     * Environment Variables for Production
     */
    public function productionEnvVariables()
    {
        /*
        Add to your .env file:
        
        # Global hybrid data source mode
        API_MODEL_DATA_SOURCE_MODE=dual_sync
        
        # Model-specific modes
        PRODUCT_DATA_SOURCE_MODE=api_first
        CATEGORY_DATA_SOURCE_MODE=hybrid
        ORDER_DATA_SOURCE_MODE=dual_sync
        CUSTOMER_DATA_SOURCE_MODE=api_only
        
        # Sync configuration
        API_MODEL_AUTO_SYNC=true
        API_MODEL_SYNC_INTERVAL=60
        API_MODEL_SYNC_BATCH_SIZE=100
        
        # Fallback configuration
        API_MODEL_FALLBACK_ENABLED=true
        API_MODEL_MAX_RETRIES=3
        API_MODEL_API_TIMEOUT=30
        
        # Performance configuration
        API_MODEL_MAX_CONCURRENT=20
        API_MODEL_BATCH_REQUESTS=true
        
        # Monitoring
        API_MODEL_METRICS_ENABLED=true
        API_MODEL_HEALTH_CHECKS=true
        */
    }
}

/**
 * Performance Comparison
 * 
 * MODE COMPARISON:
 * 
 * API_ONLY:
 * - Pros: Always fresh data, no database overhead
 * - Cons: Network dependency, slower response times
 * - Use case: Real-time data requirements
 * 
 * DB_ONLY:
 * - Pros: Fast response times, no network dependency
 * - Cons: Data may be stale, requires manual sync
 * - Use case: Offline applications, cached data scenarios
 * 
 * HYBRID:
 * - Pros: Best of both worlds, automatic fallback
 * - Cons: Complexity, potential inconsistency
 * - Use case: General purpose, development environments
 * 
 * API_FIRST:
 * - Pros: Fresh data with local caching
 * - Cons: Network dependency, sync overhead
 * - Use case: Data freshness priority with performance optimization
 * 
 * DUAL_SYNC:
 * - Pros: Maximum reliability, data consistency
 * - Cons: Higher overhead, complexity
 * - Use case: Mission-critical applications, production environments
 */
