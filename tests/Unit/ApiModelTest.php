<?php

namespace MTechStack\LaravelApiModelClient\Tests\Unit;

use MTechStack\LaravelApiModelClient\Tests\TestCase;
use MTechStack\LaravelApiModelClient\Models\ApiModel;
use MTechStack\LaravelApiModelClient\Query\ApiQueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use Mockery;

class TestApiModel extends ApiModel
{
    protected $apiEndpoint = 'api/v1/test-products';
    protected $fillable = ['id', 'name', 'sku', 'price', 'type', 'status', 'in_stock'];
    protected $casts = [
        'id' => 'integer',
        'price' => 'float',
        'status' => 'boolean',
        'in_stock' => 'boolean',
    ];
}

class ApiModelTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock HTTP client for testing
        Http::fake();
    }

    /** @test */
    public function it_can_instantiate_api_model()
    {
        $model = new TestApiModel();
        
        $this->assertTrue($model instanceof ApiModel);
        $this->assertEquals('api/v1/test-products', $model->getApiEndpoint());
    }

    /** @test */
    public function it_can_create_model_with_attributes()
    {
        $attributes = [
            'id' => 566,
            'name' => 'Test Product',
            'sku' => 'test-sku',
            'price' => 150.00,
            'type' => 'simple',
            'status' => true,
            'in_stock' => true,
        ];

        $model = new TestApiModel($attributes);

        $this->assertEquals(566, $model->id);
        $this->assertEquals('Test Product', $model->name);
        $this->assertEquals('test-sku', $model->sku);
        $this->assertEquals(150.00, $model->price);
        $this->assertTrue($model->status);
        $this->assertTrue($model->in_stock);
    }

    /** @test */
    public function it_can_create_new_instance_from_api_response()
    {
        $responseData = [
            'id' => 566,
            'name' => 'Test Product',
            'sku' => 'test-sku',
            'price' => 150.00,
            'type' => 'simple',
            'status' => 1,
            'in_stock' => true,
        ];

        $model = new TestApiModel();
        $newModel = $model->newFromApiResponse($responseData);

        $this->assertTrue($newModel instanceof TestApiModel);
        $this->assertEquals(566, $newModel->id);
        $this->assertEquals('Test Product', $newModel->name);
        $this->assertEquals(150.00, $newModel->price);
        $this->assertTrue($newModel->exists);
    }

    /** @test */
    public function it_returns_null_for_empty_api_response()
    {
        $model = new TestApiModel();
        $newModel = $model->newFromApiResponse([]);

        $this->assertNull($newModel);
    }

    /** @test */
    public function it_can_handle_new_from_api_response_with_default_parameter()
    {
        $model = new TestApiModel();
        $newModel = $model->newFromApiResponse(); // Test default parameter

        $this->assertNull($newModel);
    }

    /** @test */
    public function it_can_get_api_endpoint()
    {
        $model = new TestApiModel();
        
        $this->assertEquals('api/v1/test-products', $model->getApiEndpoint());
    }

    /** @test */
    public function it_can_create_query_builder()
    {
        $queryBuilder = TestApiModel::query();
        
        $this->assertTrue($queryBuilder instanceof ApiQueryBuilder);
    }

    /** @test */
    public function it_can_use_static_take_method()
    {
        $queryBuilder = TestApiModel::take(5);
        
        $this->assertTrue($queryBuilder instanceof ApiQueryBuilder);
    }

    /** @test */
    public function it_can_use_static_limit_method()
    {
        $queryBuilder = TestApiModel::limit(10);
        
        $this->assertTrue($queryBuilder instanceof ApiQueryBuilder);
    }

    /** @test */
    public function it_can_use_static_where_method()
    {
        $queryBuilder = TestApiModel::where('status', 1);
        
        $this->assertTrue($queryBuilder instanceof ApiQueryBuilder);
    }

    /** @test */
    public function it_can_chain_query_methods()
    {
        $queryBuilder = TestApiModel::where('status', 1)
            ->take(5)
            ->limit(3);
        
        $this->assertTrue($queryBuilder instanceof ApiQueryBuilder);
    }

    /** @test */
    public function it_can_handle_all_from_api_with_nested_data_structure()
    {
        // Mock API response with nested data structure
        $mockResponse = [
            'data' => [
                [
                    'id' => 566,
                    'name' => 'Product 1',
                    'sku' => 'prod-1',
                    'price' => 100.00,
                    'status' => 1,
                    'in_stock' => true,
                ],
                [
                    'id' => 567,
                    'name' => 'Product 2',
                    'sku' => 'prod-2',
                    'price' => 200.00,
                    'status' => 1,
                    'in_stock' => false,
                ]
            ],
            'meta' => [
                'current_page' => 1,
                'per_page' => 10,
                'total' => 2,
            ]
        ];

        Http::fake([
            'https://demo.bagisto.com/bagisto-api-demo-common/api/v1/test-products' => Http::response($mockResponse, 200)
        ]);

        $products = TestApiModel::allFromApi();

        $this->assertTrue($products instanceof Collection);
        $this->assertCount(2, $products);
        $this->assertEquals(566, $products->first()->id);
        $this->assertEquals('Product 1', $products->first()->name);
        $this->assertEquals(567, $products->last()->id);
        $this->assertEquals('Product 2', $products->last()->name);
    }

    /** @test */
    public function it_can_handle_find_from_api_with_single_item()
    {
        // Mock API response for single item
        $mockResponse = [
            'data' => [
                'id' => 566,
                'name' => 'Single Product',
                'sku' => 'single-prod',
                'price' => 150.00,
                'status' => 1,
                'in_stock' => true,
            ]
        ];

        Http::fake([
            'https://demo.bagisto.com/bagisto-api-demo-common/api/v1/test-products/566' => Http::response($mockResponse, 200)
        ]);

        $product = TestApiModel::findFromApi(566);

        $this->assertTrue($product instanceof TestApiModel);
        $this->assertEquals(566, $product->id);
        $this->assertEquals('Single Product', $product->name);
        $this->assertEquals(150.00, $product->price);
    }

    /** @test */
    public function it_returns_null_when_find_from_api_fails()
    {
        Http::fake([
            'https://demo.bagisto.com/bagisto-api-demo-common/api/v1/test-products/999' => Http::response([], 404)
        ]);

        $product = TestApiModel::findFromApi(999);

        $this->assertNull($product);
    }

    /** @test */
    public function it_can_extract_items_from_nested_response()
    {
        $model = new TestApiModel();
        $response = [
            'data' => [
                ['id' => 1, 'name' => 'Product 1'],
                ['id' => 2, 'name' => 'Product 2'],
            ],
            'meta' => ['total' => 2]
        ];

        $items = $model->extractItemsFromResponse($response);

        $this->assertCount(2, $items);
        $this->assertEquals(['id' => 1, 'name' => 'Product 1'], $items[0]);
        $this->assertEquals(['id' => 2, 'name' => 'Product 2'], $items[1]);
    }

    /** @test */
    public function it_can_extract_items_from_flat_response()
    {
        $model = new TestApiModel();
        $response = [
            ['id' => 1, 'name' => 'Product 1'],
            ['id' => 2, 'name' => 'Product 2'],
        ];

        $items = $model->extractItemsFromResponse($response);

        $this->assertCount(2, $items);
        $this->assertEquals(['id' => 1, 'name' => 'Product 1'], $items[0]);
        $this->assertEquals(['id' => 2, 'name' => 'Product 2'], $items[1]);
    }

    /** @test */
    public function it_can_handle_single_item_response()
    {
        $model = new TestApiModel();
        $response = ['id' => 1, 'name' => 'Single Product'];

        $items = $model->extractItemsFromResponse($response);

        $this->assertCount(1, $items);
        $this->assertEquals(['id' => 1, 'name' => 'Single Product'], $items[0]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
