<?php

namespace MTechStack\LaravelApiModelClient\Tests;

use PHPUnit\Framework\TestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use MTechStack\LaravelApiModelClient\OpenApi\OpenApiSchemaParser;
use MTechStack\LaravelApiModelClient\OpenApi\Exceptions\OpenApiParsingException;
use MTechStack\LaravelApiModelClient\OpenApi\Exceptions\SchemaValidationException;

class OpenApiSchemaParserTest extends TestCase
{
    protected OpenApiSchemaParser $parser;
    protected string $sampleSchemaPath;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->parser = new OpenApiSchemaParser([
            'cache_enabled' => false, // Disable cache for testing
            'remote_timeout' => 10,
            'max_file_size' => 1048576, // 1MB for testing
        ]);

        // Create sample schema file for testing
        $this->sampleSchemaPath = __DIR__ . '/fixtures/sample-openapi.json';
        $this->createSampleSchema();
    }

    protected function tearDown(): void
    {
        // Clean up test files
        if (file_exists($this->sampleSchemaPath)) {
            unlink($this->sampleSchemaPath);
        }
        
        parent::tearDown();
    }

    /** @test */
    public function it_can_parse_local_json_schema()
    {
        $result = $this->parser->parse($this->sampleSchemaPath);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('info', $result);
        $this->assertArrayHasKey('endpoints', $result);
        $this->assertArrayHasKey('schemas', $result);
        $this->assertArrayHasKey('model_mappings', $result);
        $this->assertArrayHasKey('validation_rules', $result);
        
        // Verify info section
        $this->assertEquals('Pet Store API', $result['info']['title']);
        $this->assertEquals('1.0.0', $result['info']['version']);
        
        // Verify endpoints were extracted
        $this->assertNotEmpty($result['endpoints']);
        $this->assertArrayHasKey('get_pets', $result['endpoints']);
        $this->assertArrayHasKey('post_pets', $result['endpoints']);
        $this->assertArrayHasKey('get_pets_id', $result['endpoints']);
        
        // Verify schemas were extracted
        $this->assertNotEmpty($result['schemas']);
        $this->assertArrayHasKey('Pet', $result['schemas']);
        $this->assertArrayHasKey('Error', $result['schemas']);
        
        // Verify model mappings were generated
        $this->assertNotEmpty($result['model_mappings']);
        $this->assertArrayHasKey('Pet', $result['model_mappings']);
    }

    /** @test */
    public function it_extracts_endpoint_details_correctly()
    {
        $result = $this->parser->parse($this->sampleSchemaPath);
        $endpoints = $result['endpoints'];

        // Test GET /pets endpoint
        $getPetsEndpoint = $endpoints['get_pets'];
        $this->assertEquals('GET', $getPetsEndpoint['method']);
        $this->assertEquals('/pets', $getPetsEndpoint['path']);
        $this->assertEquals('List all pets', $getPetsEndpoint['summary']);
        $this->assertCount(2, $getPetsEndpoint['parameters']); // limit and offset
        
        // Test POST /pets endpoint
        $postPetsEndpoint = $endpoints['post_pets'];
        $this->assertEquals('POST', $postPetsEndpoint['method']);
        $this->assertEquals('/pets', $postPetsEndpoint['path']);
        $this->assertNotNull($postPetsEndpoint['request_body']);
        
        // Test GET /pets/{id} endpoint
        $getPetEndpoint = $endpoints['get_pets_id'];
        $this->assertEquals('GET', $getPetEndpoint['method']);
        $this->assertEquals('/pets/{id}', $getPetEndpoint['path']);
        $this->assertCount(1, $getPetEndpoint['parameters']); // id parameter
    }

    /** @test */
    public function it_extracts_schema_information_correctly()
    {
        $result = $this->parser->parse($this->sampleSchemaPath);
        $schemas = $result['schemas'];

        // Test Pet schema
        $petSchema = $schemas['Pet'];
        $this->assertEquals('object', $petSchema['type']);
        $this->assertArrayHasKey('properties', $petSchema);
        $this->assertArrayHasKey('id', $petSchema['properties']);
        $this->assertArrayHasKey('name', $petSchema['properties']);
        $this->assertArrayHasKey('tag', $petSchema['properties']);
        
        // Test required fields
        $this->assertContains('id', $petSchema['required']);
        $this->assertContains('name', $petSchema['required']);
        
        // Test property types
        $this->assertEquals('integer', $petSchema['properties']['id']['type']);
        $this->assertEquals('string', $petSchema['properties']['name']['type']);
        $this->assertEquals('string', $petSchema['properties']['tag']['type']);
    }

    /** @test */
    public function it_generates_validation_rules_correctly()
    {
        $result = $this->parser->parse($this->sampleSchemaPath);
        $validationRules = $result['validation_rules'];

        // Test schema validation rules
        $this->assertArrayHasKey('schemas', $validationRules);
        $petRules = $validationRules['schemas']['Pet'];
        
        $this->assertArrayHasKey('id', $petRules);
        $this->assertContains('required', $petRules['id']);
        $this->assertContains('integer', $petRules['id']);
        
        $this->assertArrayHasKey('name', $petRules);
        $this->assertContains('required', $petRules['name']);
        $this->assertContains('string', $petRules['name']);
        
        // Test endpoint validation rules
        $this->assertArrayHasKey('endpoints', $validationRules);
        $getPetsRules = $validationRules['endpoints']['get_pets'];
        
        $this->assertArrayHasKey('parameters', $getPetsRules);
        $this->assertArrayHasKey('limit', $getPetsRules['parameters']);
        $this->assertContains('integer', $getPetsRules['parameters']['limit']);
    }

    /** @test */
    public function it_generates_model_mappings_correctly()
    {
        $result = $this->parser->parse($this->sampleSchemaPath);
        $modelMappings = $result['model_mappings'];

        $this->assertArrayHasKey('Pet', $modelMappings);
        
        $petMapping = $modelMappings['Pet'];
        $this->assertEquals('Pet', $petMapping['model_name']);
        $this->assertEquals('/pets', $petMapping['base_endpoint']);
        $this->assertCount(3, $petMapping['operations']); // GET, POST, GET by ID
        $this->assertNotEmpty($petMapping['attributes']);
        
        // Test operations
        $operations = $petMapping['operations'];
        $operationTypes = array_column($operations, 'type');
        $this->assertContains('index', $operationTypes);
        $this->assertContains('store', $operationTypes);
        $this->assertContains('show', $operationTypes);
    }

    /** @test */
    public function it_handles_invalid_openapi_version()
    {
        $invalidSchema = [
            'openapi' => '2.0.0', // Invalid version
            'info' => ['title' => 'Test', 'version' => '1.0.0'],
            'paths' => []
        ];

        $invalidSchemaPath = __DIR__ . '/fixtures/invalid-schema.json';
        file_put_contents($invalidSchemaPath, json_encode($invalidSchema));

        try {
            $this->expectException(OpenApiParsingException::class);
            $this->expectExceptionMessage('Unsupported OpenAPI version: 2.0');
            
            $this->parser->parse($invalidSchemaPath);
        } finally {
            unlink($invalidSchemaPath);
        }
    }

    /** @test */
    public function it_handles_missing_file()
    {
        $this->expectException(OpenApiParsingException::class);
        $this->expectExceptionMessage('OpenAPI schema file not found');
        
        $this->parser->parse('/nonexistent/file.json');
    }

    /** @test */
    public function it_can_get_validation_rules_for_endpoint()
    {
        $this->parser->parse($this->sampleSchemaPath);
        
        $rules = $this->parser->getValidationRulesForEndpoint('get_pets');
        
        $this->assertArrayHasKey('limit', $rules);
        $this->assertArrayHasKey('offset', $rules);
        $this->assertContains('integer', $rules['limit']);
        $this->assertContains('integer', $rules['offset']);
    }

    /** @test */
    public function it_can_get_validation_rules_for_schema()
    {
        $this->parser->parse($this->sampleSchemaPath);
        
        $rules = $this->parser->getValidationRulesForSchema('Pet');
        
        $this->assertArrayHasKey('id', $rules);
        $this->assertArrayHasKey('name', $rules);
        $this->assertContains('required', $rules['id']);
        $this->assertContains('integer', $rules['id']);
        $this->assertContains('required', $rules['name']);
        $this->assertContains('string', $rules['name']);
    }

    /** @test */
    public function it_can_get_model_information()
    {
        $this->parser->parse($this->sampleSchemaPath);
        
        // Test model names
        $modelNames = $this->parser->getModelNames();
        $this->assertContains('Pet', $modelNames);
        
        // Test model mapping
        $petMapping = $this->parser->getModelMapping('Pet');
        $this->assertNotNull($petMapping);
        $this->assertEquals('Pet', $petMapping['model_name']);
        
        // Test model operations
        $operations = $this->parser->getModelOperations('Pet');
        $this->assertCount(3, $operations);
        
        // Test model attributes
        $attributes = $this->parser->getModelAttributes('Pet');
        $this->assertNotEmpty($attributes);
        
        $attributeNames = array_column($attributes, 'name');
        $this->assertContains('id', $attributeNames);
        $this->assertContains('name', $attributeNames);
    }

    /** @test */
    public function it_can_generate_model_class_code()
    {
        $this->parser->parse($this->sampleSchemaPath);
        
        $modelCode = $this->parser->generateModelClass('Pet');
        
        $this->assertStringContains('class Pet extends ApiModel', $modelCode);
        $this->assertStringContains('protected $baseEndpoint = \'/pets\';', $modelCode);
        $this->assertStringContains('protected $fillable = [', $modelCode);
        $this->assertStringContains('protected $casts = [', $modelCode);
        $this->assertStringContains('\'id\' => \'integer\'', $modelCode);
    }

    /**
     * Create a sample OpenAPI schema for testing
     */
    protected function createSampleSchema(): void
    {
        $schema = [
            'openapi' => '3.0.0',
            'info' => [
                'title' => 'Pet Store API',
                'description' => 'A simple pet store API',
                'version' => '1.0.0',
                'contact' => [
                    'name' => 'API Support',
                    'email' => 'support@petstore.com'
                ]
            ],
            'servers' => [
                [
                    'url' => 'https://api.petstore.com/v1',
                    'description' => 'Production server'
                ]
            ],
            'paths' => [
                '/pets' => [
                    'get' => [
                        'operationId' => 'get_pets',
                        'summary' => 'List all pets',
                        'description' => 'Returns a list of pets',
                        'tags' => ['pets'],
                        'parameters' => [
                            [
                                'name' => 'limit',
                                'in' => 'query',
                                'description' => 'Maximum number of pets to return',
                                'required' => false,
                                'schema' => [
                                    'type' => 'integer',
                                    'minimum' => 1,
                                    'maximum' => 100,
                                    'default' => 20
                                ]
                            ],
                            [
                                'name' => 'offset',
                                'in' => 'query',
                                'description' => 'Number of pets to skip',
                                'required' => false,
                                'schema' => [
                                    'type' => 'integer',
                                    'minimum' => 0,
                                    'default' => 0
                                ]
                            ]
                        ],
                        'responses' => [
                            '200' => [
                                'description' => 'A list of pets',
                                'content' => [
                                    'application/json' => [
                                        'schema' => [
                                            'type' => 'array',
                                            'items' => [
                                                '$ref' => '#/components/schemas/Pet'
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ],
                    'post' => [
                        'operationId' => 'post_pets',
                        'summary' => 'Create a pet',
                        'description' => 'Creates a new pet',
                        'tags' => ['pets'],
                        'requestBody' => [
                            'required' => true,
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        '$ref' => '#/components/schemas/Pet'
                                    ]
                                ]
                            ]
                        ],
                        'responses' => [
                            '201' => [
                                'description' => 'Pet created successfully',
                                'content' => [
                                    'application/json' => [
                                        'schema' => [
                                            '$ref' => '#/components/schemas/Pet'
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ],
                '/pets/{id}' => [
                    'get' => [
                        'operationId' => 'get_pets_id',
                        'summary' => 'Get a pet by ID',
                        'description' => 'Returns a single pet',
                        'tags' => ['pets'],
                        'parameters' => [
                            [
                                'name' => 'id',
                                'in' => 'path',
                                'description' => 'Pet ID',
                                'required' => true,
                                'schema' => [
                                    'type' => 'integer',
                                    'minimum' => 1
                                ]
                            ]
                        ],
                        'responses' => [
                            '200' => [
                                'description' => 'Pet details',
                                'content' => [
                                    'application/json' => [
                                        'schema' => [
                                            '$ref' => '#/components/schemas/Pet'
                                        ]
                                    ]
                                ]
                            ],
                            '404' => [
                                'description' => 'Pet not found',
                                'content' => [
                                    'application/json' => [
                                        'schema' => [
                                            '$ref' => '#/components/schemas/Error'
                                        ]
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
                        'required' => ['id', 'name'],
                        'properties' => [
                            'id' => [
                                'type' => 'integer',
                                'description' => 'Unique identifier for the pet',
                                'example' => 1
                            ],
                            'name' => [
                                'type' => 'string',
                                'description' => 'Name of the pet',
                                'minLength' => 1,
                                'maxLength' => 100,
                                'example' => 'Fluffy'
                            ],
                            'tag' => [
                                'type' => 'string',
                                'description' => 'Tag to categorize the pet',
                                'nullable' => true,
                                'example' => 'cat'
                            ],
                            'status' => [
                                'type' => 'string',
                                'description' => 'Pet status',
                                'enum' => ['available', 'pending', 'sold'],
                                'default' => 'available'
                            ]
                        ]
                    ],
                    'Error' => [
                        'type' => 'object',
                        'required' => ['code', 'message'],
                        'properties' => [
                            'code' => [
                                'type' => 'integer',
                                'description' => 'Error code'
                            ],
                            'message' => [
                                'type' => 'string',
                                'description' => 'Error message'
                            ]
                        ]
                    ]
                ],
                'securitySchemes' => [
                    'ApiKeyAuth' => [
                        'type' => 'apiKey',
                        'in' => 'header',
                        'name' => 'X-API-KEY'
                    ]
                ]
            ]
        ];

        // Ensure fixtures directory exists
        $fixturesDir = dirname($this->sampleSchemaPath);
        if (!is_dir($fixturesDir)) {
            mkdir($fixturesDir, 0755, true);
        }

        file_put_contents($this->sampleSchemaPath, json_encode($schema, JSON_PRETTY_PRINT));
    }
}
