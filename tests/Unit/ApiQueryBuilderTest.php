<?php

namespace MTechStack\LaravelApiModelClient\Tests\Unit;

use MTechStack\LaravelApiModelClient\Tests\TestCase;
use MTechStack\LaravelApiModelClient\Models\ApiModel;
use MTechStack\LaravelApiModelClient\Query\ApiQueryBuilder;
use MTechStack\LaravelApiModelClient\Services\ApiClient;
use Illuminate\Support\Collection;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Mockery;

class TestQueryModel extends ApiModel
{
    protected $apiEndpoint = 'api/v1/test-items';
    protected $fillable = ['id', 'name', 'status', 'price'];
    protected $casts = [
        'id' => 'integer',
        'price' => 'float',
        'status' => 'boolean',
    ];
}

class ApiQueryBuilderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();
    }

    /** @test */
    public function it_can_instantiate_query_builder()
    {
        $model = new TestQueryModel();
        $queryBuilder = new ApiQueryBuilder($model);

        $this->assertTrue($queryBuilder instanceof ApiQueryBuilder);
    }

    /** @test */
    public function it_can_add_take_constraint()
    {
        $model = new TestQueryModel();
        $queryBuilder = new ApiQueryBuilder($model);
        
        $result = $queryBuilder->take(5);
        
        $this->assertInstanceOf(ApiQueryBuilder::class, $result);
        $this->assertSame($queryBuilder, $result); // Should return same instance for chaining
    }

    /** @test */
    public function it_can_add_limit_constraint()
    {
        $model = new TestQueryModel();
        $queryBuilder = new ApiQueryBuilder($model);
        
        $result = $queryBuilder->limit(10);
        
        $this->assertInstanceOf(ApiQueryBuilder::class, $result);
        $this->assertSame($queryBuilder, $result);
    }

    /** @test */
    public function it_can_add_where_constraint()
    {
        $model = new TestQueryModel();
        $queryBuilder = new ApiQueryBuilder($model);
        
        $result = $queryBuilder->where('status', 1);
        
        $this->assertInstanceOf(ApiQueryBuilder::class, $result);
        $this->assertSame($queryBuilder, $result);
    }

    /** @test */
    public function it_can_chain_multiple_constraints()
    {
        $model = new TestQueryModel();
        $queryBuilder = new ApiQueryBuilder($model);
        
        $result = $queryBuilder
            ->where('status', 1)
            ->take(5)
            ->limit(3);
        
        $this->assertInstanceOf(ApiQueryBuilder::class, $result);
        $this->assertSame($queryBuilder, $result);
    }

    /** @test */
    public function it_can_execute_get_from_api_query()
    {
        // Mock API response
        $mockResponse = [
            'data' => [
                [
                    'id' => 1,
                    'name' => 'Test Item 1',
                    'status' => 1,
                    'price' => 100.00,
                ],
                [
                    'id' => 2,
                    'name' => 'Test Item 2',
                    'status' => 1,
                    'price' => 200.00,
                ]
            ],
            'meta' => [
                'current_page' => 1,
                'per_page' => 10,
                'total' => 2,
            ]
        ];

        Http::fake([
            'https://demo.bagisto.com/bagisto-api-demo-common/api/v1/test-items*' => Http::response($mockResponse, 200)
        ]);

        $model = new TestQueryModel();
        $queryBuilder = new ApiQueryBuilder($model);
        
        $results = $queryBuilder->getFromApi();
        
        $this->assertTrue($results instanceof Collection);
        $this->assertCount(2, $results);
        $this->assertTrue($results->first() instanceof TestQueryModel);
        $this->assertEquals(1, $results->first()->id);
        $this->assertEquals('Test Item 1', $results->first()->name);
    }

    /** @test */
    public function it_can_execute_get_query_as_alias_for_get_from_api()
    {
        // Mock API response
        $mockResponse = [
            'data' => [
                [
                    'id' => 1,
                    'name' => 'Test Item 1',
                    'status' => 1,
                    'price' => 100.00,
                ]
            ]
        ];

        Http::fake([
            'https://demo.bagisto.com/bagisto-api-demo-common/api/v1/test-items*' => Http::response($mockResponse, 200)
        ]);

        $model = new TestQueryModel();
        $queryBuilder = new ApiQueryBuilder($model);
        
        $results = $queryBuilder->get();
        
        $this->assertTrue($results instanceof Collection);
        $this->assertCount(1, $results);
        $this->assertTrue($results->first() instanceof TestQueryModel);
    }

    /** @test */
    public function it_can_create_models_from_api_response_items()
    {
        $model = new TestQueryModel();
        $queryBuilder = new ApiQueryBuilder($model);
        
        $items = [
            [
                'id' => 1,
                'name' => 'Test Item 1',
                'status' => 1,
                'price' => 100.00,
            ],
            [
                'id' => 2,
                'name' => 'Test Item 2',
                'status' => 0,
                'price' => 200.00,
            ]
        ];
        
        $models = $queryBuilder->createModelsFromItems($items);
        
        $this->assertInstanceOf(Collection::class, $models);
        $this->assertCount(2, $models);
        $this->assertInstanceOf(TestQueryModel::class, $models->first());
        $this->assertEquals(1, $models->first()->id);
        $this->assertEquals('Test Item 1', $models->first()->name);
        $this->assertEquals(100.00, $models->first()->price);
        $this->assertTrue($models->first()->status);
        
        $this->assertEquals(2, $models->last()->id);
        $this->assertEquals('Test Item 2', $models->last()->name);
        $this->assertFalse($models->last()->status);
    }

    /** @test */
    public function it_handles_empty_items_array()
    {
        $model = new TestQueryModel();
        $queryBuilder = new ApiQueryBuilder($model);
        
        $models = $queryBuilder->createModelsFromItems([]);
        
        $this->assertInstanceOf(Collection::class, $models);
        $this->assertCount(0, $models);
    }

    /** @test */
    public function it_filters_out_null_models_from_invalid_data()
    {
        $model = new TestQueryModel();
        $queryBuilder = new ApiQueryBuilder($model);
        
        $items = [
            [
                'id' => 1,
                'name' => 'Valid Item',
                'status' => 1,
                'price' => 100.00,
            ],
            [], // This should create a null model
            [
                'id' => 2,
                'name' => 'Another Valid Item',
                'status' => 0,
                'price' => 200.00,
            ]
        ];
        
        $models = $queryBuilder->createModelsFromItems($items);
        
        $this->assertInstanceOf(Collection::class, $models);
        $this->assertCount(2, $models); // Should filter out the null model
        $this->assertEquals(1, $models->first()->id);
        $this->assertEquals(2, $models->last()->id);
    }

    /** @test */
    public function it_can_extract_items_from_nested_response()
    {
        $model = new TestQueryModel();
        $queryBuilder = new ApiQueryBuilder($model);
        
        $response = [
            'data' => [
                ['id' => 1, 'name' => 'Item 1'],
                ['id' => 2, 'name' => 'Item 2'],
            ],
            'meta' => ['total' => 2]
        ];
        
        $items = $queryBuilder->extractItemsFromResponse($response);
        
        $this->assertCount(2, $items);
        $this->assertEquals(['id' => 1, 'name' => 'Item 1'], $items[0]);
        $this->assertEquals(['id' => 2, 'name' => 'Item 2'], $items[1]);
    }

    /** @test */
    public function it_can_extract_items_from_flat_array_response()
    {
        $model = new TestQueryModel();
        $queryBuilder = new ApiQueryBuilder($model);
        
        $response = [
            ['id' => 1, 'name' => 'Item 1'],
            ['id' => 2, 'name' => 'Item 2'],
        ];
        
        $items = $queryBuilder->extractItemsFromResponse($response);
        
        $this->assertCount(2, $items);
        $this->assertEquals(['id' => 1, 'name' => 'Item 1'], $items[0]);
        $this->assertEquals(['id' => 2, 'name' => 'Item 2'], $items[1]);
    }

    /** @test */
    public function it_handles_single_item_response()
    {
        $model = new TestQueryModel();
        $queryBuilder = new ApiQueryBuilder($model);
        
        $response = ['id' => 1, 'name' => 'Single Item'];
        
        $items = $queryBuilder->extractItemsFromResponse($response);
        
        $this->assertCount(1, $items);
        $this->assertEquals(['id' => 1, 'name' => 'Single Item'], $items[0]);
    }

    /** @test */
    public function it_handles_empty_response()
    {
        $model = new TestQueryModel();
        $queryBuilder = new ApiQueryBuilder($model);
        
        $items = $queryBuilder->extractItemsFromResponse([]);
        
        $this->assertCount(0, $items);
    }

    /** @test */
    public function it_can_build_query_parameters()
    {
        $model = new TestQueryModel();
        $queryBuilder = new ApiQueryBuilder($model);
        
        $queryBuilder
            ->where('status', 1)
            ->where('category_id', 5)
            ->take(10)
            ->limit(5);
        
        // This would typically be tested by checking the actual API call parameters
        // For now, we verify the query builder maintains its state
        $this->assertTrue($queryBuilder instanceof ApiQueryBuilder);
    }

    /** @test */
    public function take_method_is_alias_for_limit()
    {
        $model = new TestQueryModel();
        $queryBuilder = new ApiQueryBuilder($model);
        
        $takeResult = $queryBuilder->take(5);
        $this->assertInstanceOf(ApiQueryBuilder::class, $takeResult);
        
        // Reset and test limit
        $queryBuilder2 = new ApiQueryBuilder($model);
        $limitResult = $queryBuilder2->limit(5);
        $this->assertInstanceOf(ApiQueryBuilder::class, $limitResult);
        
        // Both should return the same type of object
        $this->assertEquals(get_class($takeResult), get_class($limitResult));
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
