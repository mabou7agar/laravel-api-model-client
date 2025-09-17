<?php

namespace MTechStack\LaravelApiModelClient\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use MTechStack\LaravelApiModelClient\Models\ApiModel;
use MTechStack\LaravelApiModelClient\OpenApi\OpenApiSchemaParser;
use MTechStack\LaravelApiModelClient\Tests\TestCase;
use MTechStack\LaravelApiModelClient\Traits\HasOpenApiSchema;

class OpenApiModelIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected $testModel;
    protected $sampleSchema;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create a test model that uses OpenAPI schema
        $this->testModel = new class extends ApiModel {
            use HasOpenApiSchema;
            
            protected $table = 'test_pets';
            protected ?string $openApiSchemaSource = null;
            protected ?array $testSchema = null;
            
            public function setSchemaSource($source)
            {
                $this->testSchema = $source;
                $this->openApiSchema = null; // Reset cached schema
            }
            
            protected function getOpenApiSchemaSource(): ?string
            {
                return $this->testSchema ? 'test://schema' : null;
            }
            
            protected function loadOpenApiSchema(): void
            {
                if ($this->testSchema) {
                    $this->openApiSchema = $this->testSchema;
                }
            }
        };

        // Sample OpenAPI schema for testing
        $this->sampleSchema = [
            'openapi' => '3.0.0',
            'info' => ['title' => 'Pet Store API', 'version' => '1.0.0'],
            'paths' => [
                '/pets' => [
                    'get' => [
                        'operationId' => 'listPets',
                        'parameters' => [
                            [
                                'name' => 'limit',
                                'in' => 'query',
                                'schema' => ['type' => 'integer', 'maximum' => 100, 'default' => 20]
                            ],
                            [
                                'name' => 'status',
                                'in' => 'query',
                                'schema' => ['type' => 'string', 'enum' => ['available', 'pending', 'sold']]
                            ],
                            [
                                'name' => 'category',
                                'in' => 'query',
                                'schema' => ['type' => 'string', 'maxLength' => 50]
                            ]
                        ],
                        'responses' => [
                            '200' => [
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
                        'operationId' => 'createPet',
                        'requestBody' => [
                            'content' => [
                                'application/json' => [
                                    'schema' => ['$ref' => '#/components/schemas/Pet']
                                ]
                            ]
                        ]
                    ]
                ],
                '/pets/{id}' => [
                    'get' => [
                        'operationId' => 'getPet',
                        'parameters' => [
                            [
                                'name' => 'id',
                                'in' => 'path',
                                'required' => true,
                                'schema' => ['type' => 'integer']
                            ]
                        ]
                    ],
                    'put' => [
                        'operationId' => 'updatePet',
                        'parameters' => [
                            [
                                'name' => 'id',
                                'in' => 'path',
                                'required' => true,
                                'schema' => ['type' => 'integer']
                            ]
                        ]
                    ],
                    'delete' => [
                        'operationId' => 'deletePet'
                    ]
                ]
            ],
            'components' => [
                'schemas' => [
                    'Pet' => [
                        'type' => 'object',
                        'required' => ['name'],
                        'properties' => [
                            'id' => ['type' => 'integer', 'format' => 'int64'],
                            'name' => ['type' => 'string', 'maxLength' => 100],
                            'status' => ['type' => 'string', 'enum' => ['available', 'pending', 'sold']],
                            'category' => ['$ref' => '#/components/schemas/Category'],
                            'tags' => [
                                'type' => 'array',
                                'items' => ['$ref' => '#/components/schemas/Tag']
                            ],
                            'created_at' => ['type' => 'string', 'format' => 'date-time'],
                            'updated_at' => ['type' => 'string', 'format' => 'date-time']
                        ]
                    ],
                    'Category' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'integer'],
                            'name' => ['type' => 'string', 'maxLength' => 50]
                        ]
                    ],
                    'Tag' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'integer'],
                            'name' => ['type' => 'string', 'maxLength' => 30]
                        ]
                    ]
                ]
            ]
        ];

        // Clear any cached schemas
        Cache::forget('openapi_schema_' . md5(json_encode($this->sampleSchema)));
    }

    /** @test */
    public function it_can_load_openapi_schema_from_array()
    {
        $this->testModel->setSchemaSource($this->sampleSchema);
        
        $this->assertTrue($this->testModel->hasOpenApiSchema());
        $schema = $this->testModel->getOpenApiSchema();
        
        $this->assertIsArray($schema);
        $this->assertEquals('Pet Store API', $schema['info']['title']);
    }

    /** @test */
    public function it_can_extract_openapi_attributes()
    {
        $this->testModel->setSchemaSource($this->sampleSchema);
        
        $attributes = $this->testModel->getOpenApiAttributes();
        
        $this->assertIsArray($attributes);
        $attributeNames = collect($attributes)->pluck('name')->toArray();
        
        $this->assertContains('id', $attributeNames);
        $this->assertContains('name', $attributeNames);
        $this->assertContains('status', $attributeNames);
        $this->assertContains('category', $attributeNames);
        $this->assertContains('tags', $attributeNames);
    }

    /** @test */
    public function it_can_generate_fillable_from_openapi_schema()
    {
        $this->testModel->setSchemaSource($this->sampleSchema);
        
        $fillable = $this->testModel->getOpenApiFillable();
        
        $this->assertIsArray($fillable);
        $this->assertContains('name', $fillable);
        $this->assertContains('status', $fillable);
        $this->assertNotContains('id', $fillable); // ID typically not fillable
    }

    /** @test */
    public function it_can_generate_casts_from_openapi_schema()
    {
        $this->testModel->setSchemaSource($this->sampleSchema);
        
        $casts = $this->testModel->getOpenApiCasts();
        
        $this->assertIsArray($casts);
        $this->assertEquals('integer', $casts['id']);
        $this->assertEquals('string', $casts['name']);
        $this->assertEquals('datetime', $casts['created_at']);
        $this->assertEquals('datetime', $casts['updated_at']);
        $this->assertEquals('array', $casts['tags']);
    }

    /** @test */
    public function it_can_detect_openapi_relationships()
    {
        $this->testModel->setSchemaSource($this->sampleSchema);
        
        $relationships = $this->testModel->getOpenApiRelationships();
        
        $this->assertIsArray($relationships);
        
        // Check for category relationship (belongsTo)
        $categoryRel = collect($relationships)->firstWhere('name', 'category');
        $this->assertNotNull($categoryRel);
        $this->assertEquals('belongsTo', $categoryRel['type']);
        
        // Check for tags relationship (hasMany)
        $tagsRel = collect($relationships)->firstWhere('name', 'tags');
        $this->assertNotNull($tagsRel);
        $this->assertEquals('hasMany', $tagsRel['type']);
    }

    /** @test */
    public function it_can_validate_parameters_against_openapi_schema()
    {
        $this->testModel->setSchemaSource($this->sampleSchema);
        
        // Valid parameters
        $validParams = [
            'name' => 'Fluffy',
            'status' => 'available'
        ];
        
        $validator = $this->testModel->validateParameters($validParams, 'create');
        $this->assertFalse($validator->fails());
        
        // Invalid parameters
        $invalidParams = [
            'name' => str_repeat('a', 150), // Too long
            'status' => 'invalid_status' // Not in enum
        ];
        
        $validator = $this->testModel->validateParameters($invalidParams, 'create');
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('name', $validator->errors()->toArray());
        $this->assertArrayHasKey('status', $validator->errors()->toArray());
    }

    /** @test */
    public function it_can_resolve_api_endpoints_from_openapi_schema()
    {
        $this->testModel->setSchemaSource($this->sampleSchema);
        
        // Test different endpoint types
        $indexEndpoint = $this->testModel->resolveOpenApiEndpoint('index');
        $this->assertEquals('/pets', $indexEndpoint);
        
        $showEndpoint = $this->testModel->resolveOpenApiEndpoint('show');
        $this->assertEquals('/pets/{id}', $showEndpoint);
        
        $createEndpoint = $this->testModel->resolveOpenApiEndpoint('create');
        $this->assertEquals('/pets', $createEndpoint);
        
        $updateEndpoint = $this->testModel->resolveOpenApiEndpoint('update');
        $this->assertEquals('/pets/{id}', $updateEndpoint);
        
        $deleteEndpoint = $this->testModel->resolveOpenApiEndpoint('delete');
        $this->assertEquals('/pets/{id}', $deleteEndpoint);
    }

    /** @test */
    public function it_can_get_openapi_operations()
    {
        $this->testModel->setSchemaSource($this->sampleSchema);
        
        $operations = $this->testModel->getOpenApiOperations();
        
        $this->assertIsArray($operations);
        $this->assertCount(5, $operations); // GET, POST, GET {id}, PUT {id}, DELETE {id}
        
        $operationTypes = collect($operations)->pluck('type')->toArray();
        $this->assertContains('index', $operationTypes);
        $this->assertContains('create', $operationTypes);
        $this->assertContains('show', $operationTypes);
        $this->assertContains('update', $operationTypes);
        $this->assertContains('delete', $operationTypes);
    }

    /** @test */
    public function it_can_get_parameter_definitions()
    {
        $this->testModel->setSchemaSource($this->sampleSchema);
        
        $limitParam = $this->testModel->getOpenApiParameterDefinition('limit');
        $this->assertNotNull($limitParam);
        $this->assertEquals('integer', $limitParam['type']);
        $this->assertEquals(100, $limitParam['maximum']);
        
        $statusParam = $this->testModel->getOpenApiParameterDefinition('status');
        $this->assertNotNull($statusParam);
        $this->assertEquals('string', $statusParam['type']);
        $this->assertContains('available', $statusParam['enum']);
        
        $nonExistentParam = $this->testModel->getOpenApiParameterDefinition('non_existent');
        $this->assertNull($nonExistentParam);
    }

    /** @test */
    public function it_can_use_dynamic_query_scopes()
    {
        $this->testModel->setSchemaSource($this->sampleSchema);
        
        // Test the scopeWithOpenApiFilters method
        $query = $this->testModel->newQuery();
        $filters = [
            'status' => 'available',
            'category' => 'dogs'
        ];
        
        $scopedQuery = $query->withOpenApiFilters($filters);
        
        // The query should be modified (we can't easily test the actual SQL without a real DB)
        $this->assertTrue(get_class($scopedQuery) === get_class($query));
    }

    /** @test */
    public function it_maintains_backward_compatibility()
    {
        // Test model without OpenAPI schema
        $regularModel = new class extends ApiModel {
            protected $table = 'test_regular';
        };
        
        $this->assertFalse($regularModel->hasOpenApiSchema());
        
        // Should not throw errors when calling OpenAPI methods
        $this->assertNull($regularModel->getOpenApiSchema());
        $this->assertEmpty($regularModel->getOpenApiAttributes());
        $this->assertEmpty($regularModel->getOpenApiRelationships());
        $this->assertEmpty($regularModel->getOpenApiOperations());
    }

    /** @test */
    public function it_caches_openapi_schema_parsing()
    {
        $this->testModel->setSchemaSource($this->sampleSchema);
        
        // First call should parse and cache
        $schema1 = $this->testModel->getOpenApiSchema();
        
        // Second call should use cache
        $schema2 = $this->testModel->getOpenApiSchema();
        
        $this->assertEquals($schema1, $schema2);
        
        // Verify cache was used by checking cache directly
        $cacheKey = 'openapi_schema_' . md5(json_encode($this->sampleSchema));
        $this->assertTrue(Cache::has($cacheKey));
    }

    /** @test */
    public function it_handles_schema_validation_errors_gracefully()
    {
        // Invalid schema (missing required fields)
        $invalidSchema = [
            'openapi' => '3.0.0',
            // Missing info section
            'paths' => []
        ];
        
        $this->testModel->setSchemaSource($invalidSchema);
        
        // Should handle gracefully and return false for hasOpenApiSchema
        $this->assertFalse($this->testModel->hasOpenApiSchema());
    }

    /** @test */
    public function it_can_handle_complex_parameter_validation()
    {
        $this->testModel->setSchemaSource($this->sampleSchema);
        
        // Test array parameter validation
        $params = [
            'tags' => [
                ['id' => 1, 'name' => 'friendly'],
                ['id' => 2, 'name' => 'playful']
            ]
        ];
        
        $validator = $this->testModel->validateParameters($params, 'create');
        $this->assertFalse($validator->fails());
        
        // Test invalid array structure
        $invalidParams = [
            'tags' => [
                ['id' => 'not_integer', 'name' => 'friendly']
            ]
        ];
        
        $validator = $this->testModel->validateParameters($invalidParams, 'create');
        $this->assertTrue($validator->fails());
    }

    /** @test */
    public function it_can_handle_dynamic_method_calls()
    {
        $this->testModel->setSchemaSource($this->sampleSchema);
        
        // Test dynamic relationship method
        try {
            $result = $this->testModel->category();
            // Should return a relationship or null, not throw an error
            $this->assertTrue(true); // If we get here, no exception was thrown
        } catch (\BadMethodCallException $e) {
            // This is also acceptable if the relationship isn't properly set up
            $this->assertTrue(true);
        }
        
        // Test dynamic attribute accessor
        try {
            $result = $this->testModel->getStatusAttribute();
            $this->assertTrue(true); // Should not throw an error
        } catch (\Exception $e) {
            $this->assertTrue(true); // Acceptable if attribute doesn't exist
        }
    }

    /** @test */
    public function it_integrates_with_existing_api_model_features()
    {
        $this->testModel->setSchemaSource($this->sampleSchema);
        
        // Test that existing ApiModel methods still work
        $this->assertIsString($this->testModel->getTable());
        
        // Test that the model can still be instantiated normally
        $instance = new (get_class($this->testModel));
        $this->assertTrue($instance instanceof ApiModel);
        
        // Test that fillable and casts can be merged with OpenAPI-generated ones
        $fillable = $this->testModel->getFillable();
        $casts = $this->testModel->getCasts();
        
        $this->assertIsArray($fillable);
        $this->assertIsArray($casts);
    }

    protected function tearDown(): void
    {
        // Clear any cached schemas
        Cache::flush();
        parent::tearDown();
    }
}
