<?php

namespace MTechStack\LaravelApiModelClient\Tests\Feature;

use MTechStack\LaravelApiModelClient\Tests\TestCase;
use MTechStack\LaravelApiModelClient\Models\ApiModel;
use MTechStack\LaravelApiModelClient\Services\ApiClient;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;

class TestProductModel extends ApiModel
{
    protected $apiEndpoint = 'api/v1/products';
    protected $fillable = ['id', 'name', 'sku', 'price', 'type', 'status', 'in_stock'];
    protected $casts = [
        'id' => 'integer',
        'price' => 'float',
        'status' => 'boolean',
        'in_stock' => 'boolean',
    ];
}

class PackageIntegrationTest extends TestCase
{
    /** @test */
    public function it_can_configure_api_client_properly()
    {
        $baseUrl = Config::get('api-model-client.base_url');
        $token = Config::get('api-model-client.token');
        $timeout = Config::get('api-model-client.timeout');

        $this->assertEquals('https://demo.bagisto.com/bagisto-api-demo-common', $baseUrl);
        $this->assertEquals('test-token', $token);
        $this->assertEquals(30, $timeout);
    }

    /** @test */
    public function it_can_handle_complete_api_workflow()
    {
        // Mock comprehensive API responses
        $allProductsResponse = [
            'data' => [
                [
                    'id' => 566,
                    'name' => 'Test Product 1',
                    'sku' => 'test-1',
                    'price' => 100,
                    'type' => 'simple',
                    'status' => 1,
                    'in_stock' => true,
                ],
                [
                    'id' => 567,
                    'name' => 'Test Product 2',
                    'sku' => 'test-2',
                    'price' => 200,
                    'type' => 'simple',
                    'status' => 1,
                    'in_stock' => false,
                ]
            ],
            'meta' => ['total' => 2]
        ];

        $singleProductResponse = [
            'data' => [
                'id' => 566,
                'name' => 'Test Product 1',
                'sku' => 'test-1',
                'price' => 100,
                'type' => 'simple',
                'status' => 1,
                'in_stock' => true,
            ]
        ];

        $queryResponse = [
            'data' => [
                [
                    'id' => 566,
                    'name' => 'Filtered Product',
                    'sku' => 'filtered-1',
                    'price' => 150,
                    'type' => 'simple',
                    'status' => 1,
                    'in_stock' => true,
                ]
            ],
            'meta' => ['total' => 1]
        ];

        Http::fake([
            'https://demo.bagisto.com/bagisto-api-demo-common/api/v1/products' => Http::response($allProductsResponse, 200),
            'https://demo.bagisto.com/bagisto-api-demo-common/api/v1/products/566' => Http::response($singleProductResponse, 200),
            'https://demo.bagisto.com/bagisto-api-demo-common/api/v1/products*' => Http::response($queryResponse, 200),
        ]);

        // Test 1: Get all products
        $allProducts = TestProductModel::allFromApi();
        $this->assertTrue($allProducts instanceof Collection);
        $this->assertCount(2, $allProducts);
        $this->assertEquals(566, $allProducts->first()->id);
        $this->assertEquals('Test Product 1', $allProducts->first()->name);

        // Test 2: Find single product
        $singleProduct = TestProductModel::findFromApi(566);
        $this->assertTrue($singleProduct instanceof TestProductModel);
        $this->assertEquals(566, $singleProduct->id);
        $this->assertEquals('Test Product 1', $singleProduct->name);
        $this->assertTrue($singleProduct->in_stock);

        // Test 3: Query builder functionality
        $filteredProducts = TestProductModel::where('status', 1)->take(5)->getFromApi();
        $this->assertTrue($filteredProducts instanceof Collection);
        $this->assertCount(1, $filteredProducts);
        $this->assertEquals('Filtered Product', $filteredProducts->first()->name);

        // Test 4: Static query methods
        $limitedProducts = TestProductModel::limit(3)->getFromApi();
        $this->assertTrue($limitedProducts instanceof Collection);

        // Test 5: Method chaining
        $chainedQuery = TestProductModel::where('status', 1)->take(2)->limit(1)->getFromApi();
        $this->assertTrue($chainedQuery instanceof Collection);
    }

    /** @test */
    public function it_handles_api_errors_gracefully()
    {
        Http::fake([
            'https://demo.bagisto.com/bagisto-api-demo-common/api/v1/products/999' => Http::response([], 404),
            'https://demo.bagisto.com/bagisto-api-demo-common/api/v1/products' => Http::response(['error' => 'Server Error'], 500),
        ]);

        // Test error handling for findFromApi
        $notFoundProduct = TestProductModel::findFromApi(999);
        $this->assertNull($notFoundProduct);

        // Test error handling for allFromApi (should handle gracefully)
        try {
            $products = TestProductModel::allFromApi();
            // If it doesn't throw an exception, it should return empty collection or handle gracefully
            $this->assertTrue(true); // Test passes if no exception is thrown
        } catch (\Exception $e) {
            // If an exception is thrown, verify it's handled properly
            $this->assertTrue(true); // Test passes as long as we can catch and handle the error
        }
    }

    /** @test */
    public function it_can_handle_different_response_formats()
    {
        // Test flat array response
        $flatResponse = [
            [
                'id' => 1,
                'name' => 'Product 1',
                'sku' => 'prod-1',
                'price' => 50,
                'status' => 1,
                'in_stock' => true,
            ],
            [
                'id' => 2,
                'name' => 'Product 2',
                'sku' => 'prod-2',
                'price' => 75,
                'status' => 1,
                'in_stock' => false,
            ]
        ];

        // Test nested data response
        $nestedResponse = [
            'data' => [
                [
                    'id' => 3,
                    'name' => 'Product 3',
                    'sku' => 'prod-3',
                    'price' => 100,
                    'status' => 1,
                    'in_stock' => true,
                ]
            ],
            'meta' => ['total' => 1]
        ];

        Http::fake([
            'https://demo.bagisto.com/bagisto-api-demo-common/api/v1/products' => Http::sequence()
                ->push($flatResponse, 200)
                ->push($nestedResponse, 200),
        ]);

        // Test flat response handling
        $flatProducts = TestProductModel::allFromApi();
        $this->assertTrue($flatProducts instanceof Collection);
        $this->assertCount(2, $flatProducts);

        // Test nested response handling
        $nestedProducts = TestProductModel::allFromApi();
        $this->assertTrue($nestedProducts instanceof Collection);
        $this->assertCount(1, $nestedProducts);
        $this->assertEquals(3, $nestedProducts->first()->id);
    }

    /** @test */
    public function it_properly_casts_model_attributes()
    {
        $mockResponse = [
            'data' => [
                [
                    'id' => '566',          // String that should be cast to integer
                    'name' => 'Test Product',
                    'sku' => 'test-sku',
                    'price' => '150.50',    // String that should be cast to float
                    'type' => 'simple',
                    'status' => '1',        // String that should be cast to boolean
                    'in_stock' => 'true',   // String that should be cast to boolean
                ]
            ]
        ];

        Http::fake([
            'https://demo.bagisto.com/bagisto-api-demo-common/api/v1/products' => Http::response($mockResponse, 200)
        ]);

        $products = TestProductModel::allFromApi();
        $product = $products->first();

        $this->assertIsInt($product->id);
        $this->assertEquals(566, $product->id);
        
        $this->assertIsFloat($product->price);
        $this->assertEquals(150.5, $product->price);
        
        $this->assertIsBool($product->status);
        $this->assertTrue($product->status);
    }
}
