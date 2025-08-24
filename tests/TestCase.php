<?php

namespace MTechStack\LaravelApiModelClient\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use MTechStack\LaravelApiModelClient\ApiModelRelationsServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Set up test environment
        $this->setUpEnvironment();
    }

    protected function getPackageProviders($app)
    {
        return [
            ApiModelRelationsServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        // Configure test environment
        $app['config']->set('api-model-client.base_url', 'https://demo.bagisto.com/bagisto-api-demo-common');
        $app['config']->set('api-model-client.token', 'test-token');
        $app['config']->set('api-model-client.timeout', 30);
        $app['config']->set('api-model-client.cache.enabled', false);
        $app['config']->set('api-model-client.cache.ttl', 3600);
        $app['config']->set('api-model-client.error_handling.log_errors', true);
        $app['config']->set('api-model-client.error_handling.throw_exceptions', true);
        
        // Set up database for testing
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    protected function setUpEnvironment()
    {
        // Additional test setup if needed
    }

    /**
     * Create a mock API response for testing
     */
    protected function createMockApiResponse(array $data, array $meta = [])
    {
        return [
            'data' => $data,
            'meta' => array_merge([
                'current_page' => 1,
                'per_page' => 10,
                'total' => count($data),
            ], $meta)
        ];
    }

    /**
     * Create mock product data for testing
     */
    protected function createMockProductData(int $count = 1)
    {
        $products = [];
        for ($i = 1; $i <= $count; $i++) {
            $products[] = [
                'id' => 565 + $i,
                'name' => "Test Product {$i}",
                'sku' => "test-product-{$i}",
                'price' => 100 + ($i * 10),
                'type' => 'simple',
                'status' => 1,
                'in_stock' => true,
                'created_at' => now()->toISOString(),
                'updated_at' => now()->toISOString(),
            ];
        }
        return $count === 1 ? $products[0] : $products;
    }
}
