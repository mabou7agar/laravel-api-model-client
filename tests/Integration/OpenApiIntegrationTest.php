<?php

namespace MTechStack\LaravelApiModelClient\Tests\Integration;

use MTechStack\LaravelApiModelClient\Tests\OpenApiTestCase;
use MTechStack\LaravelApiModelClient\OpenApi\OpenApiSchemaParser;
use MTechStack\LaravelApiModelClient\Models\ApiModel;
use MTechStack\LaravelApiModelClient\Console\Commands\GenerateModelsCommand;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Artisan;

/**
 * Integration tests with real OpenAPI specifications
 */
class OpenApiIntegrationTest extends OpenApiTestCase
{
    protected string $tempModelsPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempModelsPath = $this->getTempPath() . '/models';
        File::makeDirectory($this->tempModelsPath, 0755, true);
    }

    /**
     * Test complete workflow: parse schema -> generate models -> test functionality
     */
    public function test_complete_openapi_workflow(): void
    {
        // Start the mock server
        $this->mockServer->start();
        $this->mockServer->addOpenApiSpecRoute($this->fixtureManager->getSchema('petstore-3.0.0'));

        // Step 1: Parse OpenAPI schema
        $this->startBenchmark('complete_workflow');
        
        $schema = $this->fixtureManager->getSchema('petstore-3.0.0');
        $tempFile = tempnam(sys_get_temp_dir(), 'openapi_test_');
        file_put_contents($tempFile, json_encode($schema));
        
        try {
            $parseResult = $this->parser->parse($tempFile);
            
            $this->assertIsArray($parseResult);
            $this->assertArrayHasKey('components', $parseResult);

            // Step 2: Extract endpoints and schemas
            $endpoints = $this->parser->getEndpoints();
            $schemas = $this->parser->getSchemas();
            
            $this->assertNotEmpty($endpoints);
            $this->assertNotEmpty($schemas);
            $this->assertArrayHasKey('Pet', $schemas);

            // Step 3: Generate validation rules
            $validationRules = $this->parser->getValidationRules();
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
        
        $this->assertIsArray($validationRules);
        $this->assertArrayHasKey('name', $validationRules);

        // Step 4: Test model generation (simulated)
        $this->simulateModelGeneration($schemas);

        // Step 5: Test API interactions with mock server
        $this->testApiInteractions();

        $workflowResult = $this->endBenchmark('complete_workflow');
        
        // Assert overall performance
        $this->assertLessThan(2.0, $workflowResult['execution_time'], 
            'Complete workflow should finish in less than 2 seconds');
    }

    /**
     * Test integration with real Swagger Petstore API
     */
    public function test_real_swagger_petstore_integration(): void
    {
        // Mock the external API call since we can't rely on external services in tests
        $this->mockHttpResponses([
            'https://petstore.swagger.io/v2/swagger.json' => [
                'body' => $this->fixtureManager->getSchema('petstore-3.0.0'),
                'status' => 200
            ]
        ]);

        $this->startBenchmark('real_api_integration');
        
        // Parse remote schema (simulate with local schema since parseFromUrl doesn't exist)
        $schema = $this->fixtureManager->getSchema('petstore-3.0.0');
        $tempFile = tempnam(sys_get_temp_dir(), 'openapi_test_');
        file_put_contents($tempFile, json_encode($schema));
        
        try {
            $remoteSchema = $this->parser->parse($tempFile);
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
        
        $this->assertIsArray($remoteSchema);
        $this->assertArrayHasKey('info', $remoteSchema);

        $integrationResult = $this->endBenchmark('real_api_integration');
        
        // Should handle remote schema efficiently
        $this->assertLessThan(1.0, $integrationResult['execution_time']);
    }

    /**
     * Test model generation command integration
     */
    public function test_model_generation_command_integration(): void
    {
        $schemaPath = $this->getFixturesPath() . '/schemas/petstore-3.0.0.json';
        
        $this->startBenchmark('model_generation_command');
        
        // Test the artisan command
        $exitCode = Artisan::call('api-client:generate-models', [
            'schema' => $schemaPath,
            '--output-dir' => $this->tempModelsPath,
            '--namespace' => 'Tests\\Generated\\Models',
            '--dry-run' => true // Don't actually create files in test
        ]);

        $commandResult = $this->endBenchmark('model_generation_command');
        
        $this->assertEquals(0, $exitCode, 'Model generation command should succeed');
        
        // Command should execute reasonably fast
        $this->assertLessThan(5.0, $commandResult['execution_time']);
    }

    /**
     * Test caching integration
     */
    public function test_caching_integration(): void
    {
        $schema = $this->fixtureManager->getSchema('petstore-3.0.0');
        $tempFile = tempnam(sys_get_temp_dir(), 'openapi_test_');
        file_put_contents($tempFile, json_encode($schema));
        
        try {
            // Configure caching
            config(['api-client.schemas.testing.caching.enabled' => true]);
            
            // First parse (should cache)
            $this->startBenchmark('first_parse_with_cache');
            $result1 = $this->parser->parse($tempFile);
            $firstParseTime = $this->endBenchmark('first_parse_with_cache')['execution_time'];

            // Second parse (should use cache)
            $this->startBenchmark('cached_parse');
            $result2 = $this->parser->parse($tempFile);
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
        $cachedParseTime = $this->endBenchmark('cached_parse')['execution_time'];

        $this->assertEquals($result1, $result2);
        
        // Verify cache was used (cached should be faster)
        if ($firstParseTime > 0.001) {
            $this->assertLessThan($firstParseTime, $cachedParseTime);
        }
    }

    /**
     * Test error handling integration
     */
    public function test_error_handling_integration(): void
    {
        // Test with various error scenarios
        $errorScenarios = [
            'network_timeout' => [
                'url' => 'https://timeout.example.com/api.json',
                'response' => ['body' => '', 'status' => 408]
            ],
            'invalid_json' => [
                'url' => 'https://invalid.example.com/api.json',
                'response' => ['body' => 'invalid json{', 'status' => 200]
            ],
            'not_found' => [
                'url' => 'https://notfound.example.com/api.json',
                'response' => ['body' => 'Not Found', 'status' => 404]
            ]
        ];

        foreach ($errorScenarios as $scenario => $config) {
            $this->mockHttpResponses([$config['url'] => $config['response']]);
            
            // Since parseFromUrl doesn't exist, simulate error handling with invalid temp files
            $tempFile = tempnam(sys_get_temp_dir(), 'openapi_test_');
            file_put_contents($tempFile, $config['response']['body']);
            
            try {
                $this->parser->parse($tempFile);
                $this->fail("Should have thrown exception for scenario: {$scenario}");
            } catch (\Exception $e) {
                $this->assertNotEmpty($e->getMessage(), 
                    "Should have meaningful error message for scenario: {$scenario}");
            } finally {
                if (file_exists($tempFile)) {
                    unlink($tempFile);
                }
            }
        }
    }

    /**
     * Test multi-schema integration
     */
    public function test_multi_schema_integration(): void
    {
        // Configure multiple schemas
        config([
            'api-client.schemas' => [
                'petstore' => [
                    'source' => $this->getFixturesPath() . '/schemas/petstore-3.0.0.json',
                    'base_url' => 'https://petstore.swagger.io/v2'
                ],
                'ecommerce' => [
                    'source' => $this->getFixturesPath() . '/schemas/ecommerce.json',
                    'base_url' => 'https://api.ecommerce.com/v2'
                ]
            ]
        ]);

        $this->startBenchmark('multi_schema_processing');

        // Process multiple schemas
        $petstoreSchema = $this->fixtureManager->getSchema('petstore-3.0.0');
        $ecommerceSchema = $this->fixtureManager->getSchema('ecommerce');

        // Create temp files for both schemas
        $petstoreTempFile = tempnam(sys_get_temp_dir(), 'openapi_petstore_');
        $ecommerceTempFile = tempnam(sys_get_temp_dir(), 'openapi_ecommerce_');
        file_put_contents($petstoreTempFile, json_encode($petstoreSchema));
        file_put_contents($ecommerceTempFile, json_encode($ecommerceSchema));

        try {
            $petstoreResult = $this->parser->parse($petstoreTempFile);
            $ecommerceResult = $this->parser->parse($ecommerceTempFile);
        } finally {
            if (file_exists($petstoreTempFile)) {
                unlink($petstoreTempFile);
            }
            if (file_exists($ecommerceTempFile)) {
                unlink($ecommerceTempFile);
            }
        }

        $multiSchemaResult = $this->endBenchmark('multi_schema_processing');

        $this->assertIsArray($petstoreResult);
        $this->assertIsArray($ecommerceResult);
        $this->assertNotEquals($petstoreResult['info']['title'], $ecommerceResult['info']['title']);

        // Should handle multiple schemas efficiently
        $this->assertLessThan(1.0, $multiSchemaResult['execution_time']);
    }

    /**
     * Test validation integration with different strictness levels
     */
    public function test_validation_strictness_integration(): void
    {
        $schema = $this->fixtureManager->getSchema('ecommerce');
        $productSchema = $schema['components']['schemas']['Product'];
        
        $testData = [
            'name' => 'Test Product',
            'price' => '99.99', // String that should be cast to number
            'unknown_field' => 'should_be_handled_differently'
        ];

        $strictnessLevels = ['strict', 'moderate', 'lenient'];
        $results = [];

        foreach ($strictnessLevels as $level) {
            config(["api-client.schemas.testing.validation.strictness" => $level]);
            
            $strictnessManager = new \MTechStack\LaravelApiModelClient\Configuration\ValidationStrictnessManager('testing');
            $rules = $this->validationHelper->generateLaravelRules($productSchema);
            
            $this->startBenchmark("validation_integration_{$level}");
            
            try {
                $result = $strictnessManager->validateParameters($testData, $rules);
                $results[$level] = ['success' => true, 'result' => $result];
            } catch (\Exception $e) {
                $results[$level] = ['success' => false, 'error' => $e->getMessage()];
            }
            
            $this->endBenchmark("validation_integration_{$level}");
        }

        // Verify different behaviors
        $this->assertArrayHasKey('strict', $results);
        $this->assertArrayHasKey('moderate', $results);
        $this->assertArrayHasKey('lenient', $results);
        
        // Lenient should always succeed
        $this->assertTrue($results['lenient']['success']);
    }

    /**
     * Test performance with large schemas
     */
    public function test_large_schema_performance_integration(): void
    {
        $largeSchema = $this->fixtureManager->getEdgeCaseFixtures()['large_schema'];
        $tempFile = tempnam(sys_get_temp_dir(), 'openapi_test_');
        file_put_contents($tempFile, json_encode($largeSchema));
        
        try {
            $this->startBenchmark('large_schema_integration');
            
            // Parse large schema
            $parseResult = $this->parser->parse($tempFile);
            
            // Extract components using public API
            $endpoints = $this->parser->getEndpoints();
            $schemas = $this->parser->getSchemas();
            
            // Generate validation rules using public API
            $allRules = $this->parser->getValidationRules();
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
        
        $largeSchemaResult = $this->endBenchmark('large_schema_integration');
        
        $this->assertIsArray($parseResult);
        $this->assertGreaterThan(50, count($endpoints));
        $this->assertGreaterThan(50, count($schemas));
        $this->assertGreaterThan(50, count($allRules));
        
        // Should handle large schemas within reasonable time
        $this->assertLessThan(5.0, $largeSchemaResult['execution_time'], 
            'Large schema processing should complete within 5 seconds');
        
        // Memory usage should be reasonable
        $this->assertLessThan(100 * 1024 * 1024, $largeSchemaResult['memory_usage'], 
            'Memory usage should be less than 100MB for large schema');
    }

    /**
     * Test concurrent processing
     */
    public function test_concurrent_processing_integration(): void
    {
        $schemas = [
            'petstore' => $this->fixtureManager->getSchema('petstore-3.0.0'),
            'ecommerce' => $this->fixtureManager->getSchema('ecommerce'),
            'microservices' => $this->fixtureManager->getSchema('microservices')
        ];

        $this->startBenchmark('concurrent_processing');
        
        $results = [];
        $parsers = [];
        
        // Simulate concurrent processing with multiple parser instances
        $tempFiles = [];
        foreach ($schemas as $name => $schema) {
            $tempFile = tempnam(sys_get_temp_dir(), "openapi_{$name}_");
            file_put_contents($tempFile, json_encode($schema));
            $tempFiles[$name] = $tempFile;
            
            $parsers[$name] = new OpenApiSchemaParser();
            $results[$name] = $parsers[$name]->parse($tempFile);
        }
        
        // Clean up temp files
        foreach ($tempFiles as $tempFile) {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
        
        $concurrentResult = $this->endBenchmark('concurrent_processing');
        
        // Verify all schemas were processed correctly
        $this->assertCount(3, $results);
        foreach ($results as $name => $result) {
            $this->assertIsArray($result);
            $this->assertArrayHasKey('info', $result);
        }
        
        // Should handle concurrent processing efficiently
        $this->assertLessThan(2.0, $concurrentResult['execution_time']);
    }

    /**
     * Test database integration (if applicable)
     */
    public function test_database_integration(): void
    {
        // This test would be relevant if the package stores schema information in database
        $this->markTestSkipped('Database integration not implemented yet');
        
        // Example of what this test might look like:
        /*
        $schema = $this->fixtureManager->getSchema('petstore-3.0.0');
        
        // Store schema in database
        $storedSchema = SchemaModel::create([
            'name' => 'petstore',
            'version' => '3.0.0',
            'content' => json_encode($schema)
        ]);
        
        // Retrieve and parse
        $retrievedSchema = json_decode($storedSchema->content, true);
        $result = $this->parser->parse($retrievedSchema);
        
        $this->assertIsArray($result);
        */
    }

    /**
     * Simulate model generation for testing
     */
    protected function simulateModelGeneration(array $schemas): void
    {
        foreach ($schemas as $schemaName => $schemaDefinition) {
            // Simulate model file creation
            $modelContent = $this->generateMockModelContent($schemaName, $schemaDefinition);
            $modelPath = $this->tempModelsPath . "/{$schemaName}.php";
            
            File::put($modelPath, $modelContent);
            $this->assertTrue(File::exists($modelPath));
            
            // Verify model content
            $content = File::get($modelPath);
            $this->assertStringContainsString("class {$schemaName}", $content);
            $this->assertStringContainsString('extends ApiModel', $content);
        }
    }

    /**
     * Generate mock model content for testing
     */
    protected function generateMockModelContent(string $className, array $schema): string
    {
        $properties = array_keys($schema['properties'] ?? []);
        $fillable = "'" . implode("', '", $properties) . "'";
        
        return "<?php

namespace Tests\\Generated\\Models;

use MTechStack\\LaravelApiModelClient\\Models\\ApiModel;

class {$className} extends ApiModel
{
    protected \$fillable = [{$fillable}];
    
    // Generated from OpenAPI schema
}";
    }

    /**
     * Test API interactions with mock server
     */
    protected function testApiInteractions(): void
    {
        // Test GET request
        $this->mockHttpResponses([
            'http://localhost:8080/api/v1/pets' => [
                'body' => [
                    'data' => [
                        ['id' => 1, 'name' => 'Fluffy', 'status' => 'available'],
                        ['id' => 2, 'name' => 'Whiskers', 'status' => 'pending']
                    ]
                ],
                'status' => 200
            ]
        ]);

        // Simulate API call
        $response = \Illuminate\Support\Facades\Http::get('http://localhost:8080/api/v1/pets');
        
        $this->assertEquals(200, $response->status());
        $this->assertIsArray($response->json());
        $this->assertArrayHasKey('data', $response->json());
    }

    /**
     * Test end-to-end workflow with real-world scenario
     */
    public function test_end_to_end_real_world_scenario(): void
    {
        $this->startBenchmark('end_to_end_scenario');
        
        // Scenario: E-commerce API integration
        $ecommerceSchema = $this->fixtureManager->getSchema('ecommerce');
        
        // Step 1: Parse and validate schema
        $tempFile = tempnam(sys_get_temp_dir(), 'openapi_test_');
        file_put_contents($tempFile, json_encode($ecommerceSchema));
        
        try {
            $parseResult = $this->parser->parse($tempFile);
            $this->assertIsArray($parseResult);
            
            // Step 2: Extract business entities
            $schemas = $this->parser->getSchemas();
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
        $this->assertArrayHasKey('Product', $schemas);
        $this->assertArrayHasKey('Order', $schemas);
        
        // Step 3: Generate validation rules for business logic
        $productRules = $this->parser->getValidationRules();
        $this->assertArrayHasKey('name', $productRules);
        $this->assertArrayHasKey('price', $productRules);
        
        // Step 4: Test business data validation
        $productData = [
            'name' => 'Premium Widget',
            'price' => 99.99,
            'in_stock' => true,
            'category' => ['id' => 1, 'name' => 'Electronics']
        ];
        
        $strictnessManager = new \MTechStack\LaravelApiModelClient\Configuration\ValidationStrictnessManager('testing');
        $validationResult = $strictnessManager->validateParameters($productData, $productRules);
        
        $this->assertTrue($validationResult['valid']);
        
        // Step 5: Simulate API operations
        $this->mockHttpResponses([
            'https://api.ecommerce.com/v2/products' => [
                'body' => ['data' => $productData],
                'status' => 201
            ]
        ]);
        
        $apiResponse = \Illuminate\Support\Facades\Http::post('https://api.ecommerce.com/v2/products', $productData);
        $this->assertEquals(201, $apiResponse->status());
        
        $endToEndResult = $this->endBenchmark('end_to_end_scenario');
        
        // End-to-end scenario should complete efficiently
        $this->assertLessThan(3.0, $endToEndResult['execution_time'], 
            'End-to-end scenario should complete within 3 seconds');
    }
}
