<?php

namespace MTechStack\LaravelApiModelClient\Tests;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use MTechStack\LaravelApiModelClient\OpenApi\OpenApiSchemaParser;
use MTechStack\LaravelApiModelClient\Configuration\ConfigurationValidator;
use MTechStack\LaravelApiModelClient\Configuration\ValidationStrictnessManager;
use MTechStack\LaravelApiModelClient\Tests\Utilities\MockApiServer;
use MTechStack\LaravelApiModelClient\Tests\Utilities\SchemaFixtureManager;
use MTechStack\LaravelApiModelClient\Tests\Utilities\PerformanceBenchmark;

/**
 * Base test case for OpenAPI integration tests
 */
abstract class OpenApiTestCase extends TestCase
{
    protected MockApiServer $mockServer;
    protected SchemaFixtureManager $fixtureManager;
    protected PerformanceBenchmark $benchmark;
    protected OpenApiSchemaParser $parser;
    protected ConfigurationValidator $configValidator;

    protected function setUp(): void
    {
        parent::setUp();

        // Initialize OpenAPI testing components
        $this->setupOpenApiTestEnvironment();
        $this->setupMockServer();
        $this->setupFixtureManager();
        $this->setupBenchmark();
        $this->setupParsers();
    }

    protected function tearDown(): void
    {
        // Clean up mock server and temp files
        $this->mockServer?->stop();
        $this->cleanupTempFiles();

        parent::tearDown();
    }

    /**
     * Setup OpenAPI test environment
     */
    protected function setupOpenApiTestEnvironment(): void
    {
        // Configure test-specific OpenAPI settings
        config([
            'api-client.default_schema' => 'testing',
            'api-client.schemas.testing' => [
                'source' => $this->getTestSchemaPath(),
                'base_url' => 'http://localhost:8080',
                'authentication' => [
                    'type' => 'bearer',
                    'token' => 'test-token-123'
                ],
                'validation' => [
                    'strictness' => 'strict',
                    'fail_on_unknown_properties' => true,
                    'auto_cast_types' => true,
                    'validate_formats' => true
                ],
                'caching' => [
                    'enabled' => false, // Disable for testing
                    'ttl' => 60,
                    'store' => 'array'
                ],
                'model_generation' => [
                    'enabled' => true,
                    'namespace' => 'Tests\\Generated\\Models',
                    'output_directory' => storage_path('testing/models'),
                    'generate_factories' => true
                ]
            ],
            'api-client.performance' => [
                'connection_pooling' => false,
                'parallel_requests' => 1,
                'request_timeout' => 5
            ],
            'api-client.development' => [
                'testing_mode' => true,
                'mock_responses' => true,
                'generate_mock_data' => true
            ]
        ]);
    }

    /**
     * Setup mock API server
     */
    protected function setupMockServer(): void
    {
        $this->mockServer = new MockApiServer([
            'host' => 'localhost',
            'port' => 8080,
            'routes_file' => $this->getTestRoutesPath()
        ]);
    }

    /**
     * Setup fixture manager
     */
    protected function setupFixtureManager(): void
    {
        $this->fixtureManager = new SchemaFixtureManager(
            $this->getFixturesPath()
        );
    }

    /**
     * Setup performance benchmark
     */
    protected function setupBenchmark(): void
    {
        $this->benchmark = new PerformanceBenchmark();
    }

    /**
     * Setup parsers and validators
     */
    protected function setupParsers(): void
    {
        $this->parser = new OpenApiSchemaParser();
        $this->configValidator = new ConfigurationValidator();
    }

    /**
     * Get test schema path
     */
    protected function getTestSchemaPath(): string
    {
        return $this->getFixturesPath() . '/schemas/petstore-openapi-3.0.json';
    }

    /**
     * Get test routes path
     */
    protected function getTestRoutesPath(): string
    {
        return $this->getFixturesPath() . '/mock-routes.json';
    }

    /**
     * Get fixtures directory path
     */
    protected function getFixturesPath(): string
    {
        return __DIR__ . '/fixtures';
    }

    /**
     * Get temporary testing directory
     */
    protected function getTempPath(): string
    {
        $path = storage_path('testing/openapi');
        if (!File::exists($path)) {
            File::makeDirectory($path, 0755, true);
        }
        return $path;
    }

    /**
     * Clean up temporary files
     */
    protected function cleanupTempFiles(): void
    {
        $tempPath = storage_path('testing');
        if (File::exists($tempPath)) {
            File::deleteDirectory($tempPath);
        }
    }

    /**
     * Create a test OpenAPI schema
     */
    protected function createTestSchema(array $overrides = []): array
    {
        return array_merge([
            'openapi' => '3.0.0',
            'info' => [
                'title' => 'Test API',
                'version' => '1.0.0',
                'description' => 'Test OpenAPI schema for unit testing'
            ],
            'servers' => [
                ['url' => 'http://localhost:8080/api/v1']
            ],
            'paths' => [
                '/pets' => [
                    'get' => [
                        'summary' => 'List pets',
                        'operationId' => 'listPets',
                        'parameters' => [
                            [
                                'name' => 'limit',
                                'in' => 'query',
                                'schema' => ['type' => 'integer', 'maximum' => 100]
                            ]
                        ],
                        'responses' => [
                            '200' => [
                                'description' => 'Successful response',
                                'content' => [
                                    'application/json' => [
                                        'schema' => [
                                            'type' => 'array',
                                            'items' => ['$ref' => '#/components/schemas/Pet']
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ],
                    'post' => [
                        'summary' => 'Create pet',
                        'operationId' => 'createPet',
                        'requestBody' => [
                            'required' => true,
                            'content' => [
                                'application/json' => [
                                    'schema' => ['$ref' => '#/components/schemas/Pet']
                                ]
                            ]
                        ],
                        'responses' => [
                            '201' => [
                                'description' => 'Pet created',
                                'content' => [
                                    'application/json' => [
                                        'schema' => ['$ref' => '#/components/schemas/Pet']
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            'components' => [
                'schemas' => [
                    'Pet' => [
                        'type' => 'object',
                        'required' => ['name'],
                        'properties' => [
                            'id' => [
                                'type' => 'integer',
                                'format' => 'int64',
                                'readOnly' => true
                            ],
                            'name' => [
                                'type' => 'string',
                                'maxLength' => 100
                            ],
                            'status' => [
                                'type' => 'string',
                                'enum' => ['available', 'pending', 'sold']
                            ],
                            'category' => [
                                '$ref' => '#/components/schemas/Category'
                            ],
                            'tags' => [
                                'type' => 'array',
                                'items' => ['$ref' => '#/components/schemas/Tag']
                            ]
                        ]
                    ],
                    'Category' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'integer'],
                            'name' => ['type' => 'string']
                        ]
                    ],
                    'Tag' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'integer'],
                            'name' => ['type' => 'string']
                        ]
                    ]
                ],
                'securitySchemes' => [
                    'bearerAuth' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                        'bearerFormat' => 'JWT'
                    ]
                ]
            ],
            'security' => [
                ['bearerAuth' => []]
            ]
        ], $overrides);
    }

    /**
     * Create test HTTP responses for mocking
     */
    protected function mockHttpResponses(array $responses): void
    {
        foreach ($responses as $url => $response) {
            Http::fake([
                $url => Http::response($response['body'], $response['status'] ?? 200, $response['headers'] ?? [])
            ]);
        }
    }

    /**
     * Assert schema validation passes
     */
    protected function assertSchemaValid(array $schema, string $message = ''): void
    {
        try {
            $this->parser->validateSchema($schema);
            $this->assertTrue(true, $message ?: 'Schema validation should pass');
        } catch (\Exception $e) {
            $this->fail($message ?: "Schema validation failed: {$e->getMessage()}");
        }
    }

    /**
     * Assert schema validation fails
     */
    protected function assertSchemaInvalid(array $schema, string $expectedError = '', string $message = ''): void
    {
        try {
            $this->parser->validateSchema($schema);
            $this->fail($message ?: 'Schema validation should fail');
        } catch (\Exception $e) {
            if ($expectedError) {
                $this->assertStringContainsString($expectedError, $e->getMessage());
            }
            $this->assertTrue(true, $message ?: 'Schema validation correctly failed');
        }
    }

    /**
     * Assert performance benchmark meets criteria
     */
    protected function assertPerformanceMeetsCriteria(string $operation, float $maxTime, int $maxMemory = null): void
    {
        $result = $this->benchmark->getResult($operation);
        
        $this->assertLessThan($maxTime, $result['execution_time'], 
            "Operation '{$operation}' took {$result['execution_time']}s, expected < {$maxTime}s");
        
        if ($maxMemory !== null) {
            $this->assertLessThan($maxMemory, $result['memory_usage'], 
                "Operation '{$operation}' used {$result['memory_usage']} bytes, expected < {$maxMemory} bytes");
        }
    }

    /**
     * Start performance benchmark
     */
    protected function startBenchmark(string $operation): void
    {
        $this->benchmark->start($operation);
    }

    /**
     * End performance benchmark
     */
    protected function endBenchmark(string $operation): array
    {
        return $this->benchmark->end($operation);
    }

    /**
     * Create validation test data
     */
    protected function createValidationTestData(): array
    {
        return [
            'valid_pet' => [
                'name' => 'Fluffy',
                'status' => 'available',
                'category' => ['id' => 1, 'name' => 'Dogs'],
                'tags' => [['id' => 1, 'name' => 'friendly']]
            ],
            'invalid_pet_missing_name' => [
                'status' => 'available'
            ],
            'invalid_pet_wrong_status' => [
                'name' => 'Fluffy',
                'status' => 'invalid_status'
            ],
            'invalid_pet_wrong_type' => [
                'name' => 123, // Should be string
                'status' => 'available'
            ]
        ];
    }

    /**
     * Get OpenAPI version test cases
     */
    protected function getOpenApiVersionTestCases(): array
    {
        return [
            '3.0.0' => $this->fixtureManager->getSchema('petstore-3.0.0'),
            '3.0.1' => $this->fixtureManager->getSchema('petstore-3.0.1'),
            '3.0.2' => $this->fixtureManager->getSchema('petstore-3.0.2'),
            '3.0.3' => $this->fixtureManager->getSchema('petstore-3.0.3'),
            '3.1.0' => $this->fixtureManager->getSchema('petstore-3.1.0'),
        ];
    }

    /**
     * Create cache test scenarios
     */
    protected function createCacheTestScenarios(): array
    {
        return [
            'no_cache' => ['enabled' => false],
            'file_cache' => ['enabled' => true, 'store' => 'file', 'ttl' => 60],
            'redis_cache' => ['enabled' => true, 'store' => 'redis', 'ttl' => 300],
            'array_cache' => ['enabled' => true, 'store' => 'array', 'ttl' => 30],
        ];
    }
}
