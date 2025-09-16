<?php

namespace MTechStack\LaravelApiModelClient\Tests\Integration;

use MTechStack\LaravelApiModelClient\Tests\OpenApiTestCase;
use MTechStack\LaravelApiModelClient\Query\OpenApiQueryBuilder;
use MTechStack\LaravelApiModelClient\Models\ApiModel;
use Illuminate\Support\Facades\Http;

/**
 * Integration tests for OpenAPI Query Builder functionality
 */
class QueryBuilderIntegrationTest extends OpenApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup mock API responses for query builder testing
        $this->setupQueryBuilderMockResponses();
    }

    /**
     * Test basic query builder functionality with OpenAPI validation
     */
    public function test_query_builder_basic_functionality(): void
    {
        $schema = $this->fixtureManager->getSchema('petstore-3.0.0');
        
        $this->startBenchmark('query_builder_basic');
        
        // Create a test model with OpenAPI schema
        $testModel = $this->createTestModel($schema);
        
        // Test basic where clause with OpenAPI validation
        $query = $testModel->newQuery()
            ->whereOpenApi('status', 'available')
            ->whereOpenApi('name', 'like', '%fluffy%');
        
        $this->assertInstanceOf(OpenApiQueryBuilder::class, $query);
        
        // Test query execution
        $results = $query->get();
        $this->assertIsArray($results);
        
        $basicResult = $this->endBenchmark('query_builder_basic');
        $this->assertLessThan(0.1, $basicResult['execution_time']);
    }

    /**
     * Test OpenAPI parameter validation in query builder
     */
    public function test_query_builder_parameter_validation(): void
    {
        $schema = $this->fixtureManager->getSchema('ecommerce');
        $testModel = $this->createTestModel($schema);
        
        $this->startBenchmark('query_builder_validation');
        
        // Valid parameters should work
        $validQuery = $testModel->newQuery()
            ->whereOpenApi('price', '>=', 10.0)
            ->whereOpenApi('in_stock', true)
            ->whereOpenApi('category', 'Electronics');
        
        $this->assertInstanceOf(OpenApiQueryBuilder::class, $validQuery);
        
        // Test parameter type validation
        try {
            $testModel->newQuery()->whereOpenApi('price', '>=', 'invalid_price');
            $this->fail('Should have thrown validation exception for invalid price type');
        } catch (\Exception $e) {
            $this->assertStringContainsString('validation', strtolower($e->getMessage()));
        }
        
        $validationResult = $this->endBenchmark('query_builder_validation');
        $this->assertLessThan(0.05, $validationResult['execution_time']);
    }

    /**
     * Test dynamic query methods based on OpenAPI schema
     */
    public function test_dynamic_query_methods(): void
    {
        $schema = $this->fixtureManager->getSchema('ecommerce');
        $testModel = $this->createTestModel($schema);
        
        $this->startBenchmark('dynamic_query_methods');
        
        // Test dynamic where methods
        $query = $testModel->newQuery()
            ->whereName('Test Product')
            ->wherePrice(99.99)
            ->whereInStock(true);
        
        $this->assertInstanceOf(OpenApiQueryBuilder::class, $query);
        
        // Test dynamic scope methods
        $scopedQuery = $testModel->newQuery()
            ->available()
            ->inPriceRange(10, 100)
            ->inCategory('Electronics');
        
        $this->assertInstanceOf(OpenApiQueryBuilder::class, $scopedQuery);
        
        $dynamicResult = $this->endBenchmark('dynamic_query_methods');
        $this->assertLessThan(0.05, $dynamicResult['execution_time']);
    }

    /**
     * Test query builder with relationships
     */
    public function test_query_builder_with_relationships(): void
    {
        $schema = $this->fixtureManager->getSchema('ecommerce');
        $testModel = $this->createTestModel($schema);
        
        $this->startBenchmark('query_builder_relationships');
        
        // Test eager loading with OpenAPI relationships
        $query = $testModel->newQuery()
            ->with(['category', 'tags'])
            ->whereHas('category', function ($q) {
                $q->where('name', 'Electronics');
            });
        
        $results = $query->get();
        $this->assertIsArray($results);
        
        // Test nested relationship queries
        $nestedQuery = $testModel->newQuery()
            ->whereHas('category.parent', function ($q) {
                $q->where('active', true);
            });
        
        $this->assertInstanceOf(OpenApiQueryBuilder::class, $nestedQuery);
        
        $relationshipResult = $this->endBenchmark('query_builder_relationships');
        $this->assertLessThan(0.1, $relationshipResult['execution_time']);
    }

    /**
     * Test query builder pagination with OpenAPI
     */
    public function test_query_builder_pagination(): void
    {
        $schema = $this->fixtureManager->getSchema('petstore-3.0.0');
        $testModel = $this->createTestModel($schema);
        
        $this->startBenchmark('query_builder_pagination');
        
        // Test limit and offset
        $limitQuery = $testModel->newQuery()
            ->limitOpenApi(10)
            ->offsetOpenApi(20);
        
        $this->assertInstanceOf(OpenApiQueryBuilder::class, $limitQuery);
        
        // Test pagination
        $paginatedResults = $testModel->newQuery()
            ->whereOpenApi('status', 'available')
            ->paginate(15);
        
        $this->assertIsArray($paginatedResults);
        $this->assertArrayHasKey('data', $paginatedResults);
        $this->assertArrayHasKey('meta', $paginatedResults);
        
        $paginationResult = $this->endBenchmark('query_builder_pagination');
        $this->assertLessThan(0.1, $paginationResult['execution_time']);
    }

    /**
     * Test query builder ordering with OpenAPI validation
     */
    public function test_query_builder_ordering(): void
    {
        $schema = $this->fixtureManager->getSchema('ecommerce');
        $testModel = $this->createTestModel($schema);
        
        $this->startBenchmark('query_builder_ordering');
        
        // Test valid ordering
        $orderedQuery = $testModel->newQuery()
            ->orderByOpenApi('price', 'desc')
            ->orderByOpenApi('name', 'asc');
        
        $results = $orderedQuery->get();
        $this->assertIsArray($results);
        
        // Test invalid ordering field
        try {
            $testModel->newQuery()->orderByOpenApi('invalid_field', 'asc');
            $this->fail('Should have thrown exception for invalid ordering field');
        } catch (\Exception $e) {
            $this->assertStringContainsString('invalid', strtolower($e->getMessage()));
        }
        
        $orderingResult = $this->endBenchmark('query_builder_ordering');
        $this->assertLessThan(0.05, $orderingResult['execution_time']);
    }

    /**
     * Test query builder with complex filtering
     */
    public function test_complex_filtering(): void
    {
        $schema = $this->fixtureManager->getSchema('ecommerce');
        $testModel = $this->createTestModel($schema);
        
        $this->startBenchmark('complex_filtering');
        
        // Test complex where conditions
        $complexQuery = $testModel->newQuery()
            ->where(function ($query) {
                $query->whereOpenApi('price', '>=', 50)
                      ->orWhereOpenApi('category.name', 'Premium');
            })
            ->whereOpenApi('in_stock', true)
            ->whereIn('status', ['active', 'featured']);
        
        $results = $complexQuery->get();
        $this->assertIsArray($results);
        
        // Test date range filtering
        $dateQuery = $testModel->newQuery()
            ->whereBetween('created_at', ['2023-01-01', '2023-12-31'])
            ->whereOpenApi('updated_at', '>=', '2023-06-01');
        
        $this->assertInstanceOf(OpenApiQueryBuilder::class, $dateQuery);
        
        $complexResult = $this->endBenchmark('complex_filtering');
        $this->assertLessThan(0.1, $complexResult['execution_time']);
    }

    /**
     * Test query builder caching integration
     */
    public function test_query_builder_caching(): void
    {
        config(['api-client.schemas.testing.caching.enabled' => true]);
        
        $schema = $this->fixtureManager->getSchema('petstore-3.0.0');
        $testModel = $this->createTestModel($schema);
        
        $this->startBenchmark('query_builder_caching');
        
        // First query (should cache)
        $query = $testModel->newQuery()->whereOpenApi('status', 'available');
        $firstResults = $query->get();
        
        // Second identical query (should use cache)
        $secondResults = $query->get();
        
        $this->assertEquals($firstResults, $secondResults);
        
        $cachingResult = $this->endBenchmark('query_builder_caching');
        $this->assertLessThan(0.2, $cachingResult['execution_time']);
    }

    /**
     * Test query builder performance with large datasets
     */
    public function test_query_builder_performance(): void
    {
        $schema = $this->fixtureManager->getSchema('ecommerce');
        $testModel = $this->createTestModel($schema);
        
        // Mock large dataset response
        $largeDataset = [];
        for ($i = 0; $i < 1000; $i++) {
            $largeDataset[] = [
                'id' => $i + 1,
                'name' => "Product {$i}",
                'price' => rand(10, 1000),
                'in_stock' => rand(0, 1) === 1
            ];
        }
        
        $this->mockHttpResponses([
            'http://localhost:8080/api/v1/products*' => [
                'body' => ['data' => $largeDataset],
                'status' => 200
            ]
        ]);
        
        $this->startBenchmark('query_builder_large_dataset');
        
        // Test query with large dataset
        $results = $testModel->newQuery()
            ->whereOpenApi('in_stock', true)
            ->whereOpenApi('price', '>=', 50)
            ->limitOpenApi(100)
            ->get();
        
        $this->assertIsArray($results);
        $this->assertLessThanOrEqual(100, count($results));
        
        $performanceResult = $this->endBenchmark('query_builder_large_dataset');
        $this->assertLessThan(1.0, $performanceResult['execution_time']);
    }

    /**
     * Test query builder error handling
     */
    public function test_query_builder_error_handling(): void
    {
        $schema = $this->fixtureManager->getSchema('petstore-3.0.0');
        $testModel = $this->createTestModel($schema);
        
        // Test API error handling
        $this->mockHttpResponses([
            'http://localhost:8080/api/v1/pets*' => [
                'body' => ['error' => 'Internal Server Error'],
                'status' => 500
            ]
        ]);
        
        try {
            $testModel->newQuery()->whereOpenApi('status', 'available')->get();
            $this->fail('Should have thrown exception for API error');
        } catch (\Exception $e) {
            $this->assertNotEmpty($e->getMessage());
        }
        
        // Test validation error handling
        try {
            $testModel->newQuery()->whereOpenApi('invalid_field', 'value');
            $this->fail('Should have thrown exception for invalid field');
        } catch (\Exception $e) {
            $this->assertStringContainsString('invalid', strtolower($e->getMessage()));
        }
    }

    /**
     * Test query builder with different strictness levels
     */
    public function test_query_builder_strictness_levels(): void
    {
        $schema = $this->fixtureManager->getSchema('ecommerce');
        
        $strictnessLevels = ['strict', 'moderate', 'lenient'];
        
        foreach ($strictnessLevels as $level) {
            config(["api-client.schemas.testing.validation.strictness" => $level]);
            
            $testModel = $this->createTestModel($schema);
            
            $this->startBenchmark("query_builder_strictness_{$level}");
            
            // Test query with potentially problematic parameters
            $query = $testModel->newQuery()
                ->whereOpenApi('price', '99.99') // String that should be cast to number
                ->whereOpenApi('in_stock', 'true'); // String that should be cast to boolean
            
            if ($level === 'strict') {
                // Strict mode might throw exceptions for type mismatches
                try {
                    $results = $query->get();
                    $this->assertIsArray($results);
                } catch (\Exception $e) {
                    // Expected in strict mode
                    $this->assertNotEmpty($e->getMessage());
                }
            } else {
                // Moderate and lenient should handle type casting
                $results = $query->get();
                $this->assertIsArray($results);
            }
            
            $this->endBenchmark("query_builder_strictness_{$level}");
        }
    }

    /**
     * Test query builder with custom scopes
     */
    public function test_query_builder_custom_scopes(): void
    {
        $schema = $this->fixtureManager->getSchema('ecommerce');
        $testModel = $this->createTestModel($schema);
        
        $this->startBenchmark('query_builder_custom_scopes');
        
        // Test predefined scopes based on OpenAPI schema
        $scopedQuery = $testModel->newQuery()
            ->available()
            ->inPriceRange(10, 100)
            ->featured();
        
        $this->assertInstanceOf(OpenApiQueryBuilder::class, $scopedQuery);
        
        // Test chaining multiple scopes
        $chainedQuery = $testModel->newQuery()
            ->available()
            ->inCategory('Electronics')
            ->onSale()
            ->orderByPopularity();
        
        $results = $chainedQuery->get();
        $this->assertIsArray($results);
        
        $customScopesResult = $this->endBenchmark('query_builder_custom_scopes');
        $this->assertLessThan(0.1, $customScopesResult['execution_time']);
    }

    /**
     * Setup mock responses for query builder testing
     */
    protected function setupQueryBuilderMockResponses(): void
    {
        $this->mockHttpResponses([
            'http://localhost:8080/api/v1/pets*' => [
                'body' => [
                    'data' => [
                        ['id' => 1, 'name' => 'Fluffy', 'status' => 'available'],
                        ['id' => 2, 'name' => 'Whiskers', 'status' => 'pending'],
                        ['id' => 3, 'name' => 'Buddy', 'status' => 'available']
                    ],
                    'meta' => [
                        'current_page' => 1,
                        'per_page' => 10,
                        'total' => 3
                    ]
                ],
                'status' => 200
            ],
            'http://localhost:8080/api/v1/products*' => [
                'body' => [
                    'data' => [
                        [
                            'id' => 1,
                            'name' => 'Premium Widget',
                            'price' => 99.99,
                            'in_stock' => true,
                            'category' => ['id' => 1, 'name' => 'Electronics']
                        ],
                        [
                            'id' => 2,
                            'name' => 'Basic Widget',
                            'price' => 29.99,
                            'in_stock' => false,
                            'category' => ['id' => 2, 'name' => 'Home']
                        ]
                    ]
                ],
                'status' => 200
            ]
        ]);
    }

    /**
     * Create a test model with OpenAPI schema
     */
    protected function createTestModel(array $schema): ApiModel
    {
        return new class($schema) extends ApiModel {
            protected ?array $openApiSchema;
            
            public function __construct(array $schema)
            {
                parent::__construct();
                $this->openApiSchema = $schema;
            }
            
            public function newQuery()
            {
                return new OpenApiQueryBuilder($this->newBaseQueryBuilder(), $this);
            }
            
            // Mock scope methods
            public function scopeAvailable($query)
            {
                return $query->whereOpenApi('status', 'available');
            }
            
            public function scopeInPriceRange($query, $min, $max)
            {
                return $query->whereOpenApi('price', '>=', $min)
                            ->whereOpenApi('price', '<=', $max);
            }
            
            public function scopeInCategory($query, $category)
            {
                return $query->whereOpenApi('category.name', $category);
            }
            
            public function scopeFeatured($query)
            {
                return $query->whereOpenApi('featured', true);
            }
            
            public function scopeOnSale($query)
            {
                return $query->whereOpenApi('on_sale', true);
            }
            
            public function scopeOrderByPopularity($query)
            {
                return $query->orderByOpenApi('popularity_score', 'desc');
            }
        };
    }
}
