<?php

namespace MTechStack\LaravelApiModelClient\Tests\Unit;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use MTechStack\LaravelApiModelClient\Models\ApiModel;
use MTechStack\LaravelApiModelClient\Query\OpenApiQueryBuilder;
use MTechStack\LaravelApiModelClient\Tests\TestCase;
use MTechStack\LaravelApiModelClient\Traits\HasOpenApiSchema;

class OpenApiQueryBuilderTest extends TestCase
{
    protected $testModel;
    protected $sampleSchema;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create a test model with OpenAPI schema
        $this->testModel = new class extends ApiModel {
            use HasOpenApiSchema;
            
            protected $table = 'test_pets';
            protected ?string $openApiSchemaSource = null;
            protected ?array $testSchema = null;
            
            public function setSchemaSource($source)
            {
                $this->testSchema = $source;
                $this->openApiSchema = null;
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
            
            public function newEloquentBuilder($query)
            {
                return new OpenApiQueryBuilder($query);
            }
        };

        // Sample schema for testing
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
                                'name' => 'name',
                                'in' => 'query',
                                'schema' => ['type' => 'string', 'maxLength' => 50, 'minLength' => 2]
                            ],
                            [
                                'name' => 'tags',
                                'in' => 'query',
                                'schema' => ['type' => 'array', 'items' => ['type' => 'string']],
                                'style' => 'simple'
                            ],
                            [
                                'name' => 'sort',
                                'in' => 'query',
                                'schema' => ['type' => 'string']
                            ],
                            [
                                'name' => 'page',
                                'in' => 'query',
                                'schema' => ['type' => 'integer', 'minimum' => 1]
                            ],
                            [
                                'name' => 'per_page',
                                'in' => 'query',
                                'schema' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100]
                            ],
                            [
                                'name' => 'search',
                                'in' => 'query',
                                'schema' => ['type' => 'string']
                            ],
                            [
                                'name' => 'created_at',
                                'in' => 'query',
                                'schema' => ['type' => 'string', 'format' => 'date']
                            ],
                            [
                                'name' => 'price_min',
                                'in' => 'query',
                                'schema' => ['type' => 'number', 'minimum' => 0]
                            ],
                            [
                                'name' => 'price_max',
                                'in' => 'query',
                                'schema' => ['type' => 'number', 'minimum' => 0]
                            ]
                        ]
                    ]
                ]
            ],
            'components' => [
                'schemas' => [
                    'Pet' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'integer'],
                            'name' => ['type' => 'string', 'maxLength' => 50],
                            'status' => ['type' => 'string', 'enum' => ['available', 'pending', 'sold']],
                            'age' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 30]
                        ]
                    ]
                ]
            ]
        ];

        $this->testModel->setSchemaSource($this->sampleSchema);
    }

    /** @test */
    public function it_creates_openapi_query_builder_instance()
    {
        $query = $this->testModel->newQuery();
        
        $this->assertTrue($query instanceof OpenApiQueryBuilder);
    }

    /** @test */
    public function it_can_add_openapi_validated_where_clause()
    {
        $query = $this->testModel->newQuery();
        
        // Valid parameter
        $result = $query->whereOpenApi('status', '=', 'available');
        
        $this->assertTrue($result instanceof OpenApiQueryBuilder);
        $this->assertEquals(['status' => 'available'], $result->getApiParameters());
    }

    /** @test */
    public function it_throws_exception_for_invalid_openapi_attribute()
    {
        $query = $this->testModel->newQuery();
        
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Attribute 'invalid_attribute' not found in OpenAPI schema");
        
        $query->whereOpenApi('invalid_attribute', '=', 'value');
    }

    /** @test */
    public function it_validates_parameter_values_against_openapi_schema()
    {
        $query = $this->testModel->newQuery();
        
        // Valid enum value
        $query->whereOpenApi('status', '=', 'available');
        $this->assertEquals(['status' => 'available'], $query->getApiParameters());
        
        // Invalid enum value should throw exception
        $this->expectException(\InvalidArgumentException::class);
        $query->whereOpenApi('status', '=', 'invalid_status');
    }

    /** @test */
    public function it_can_add_multiple_openapi_where_clauses()
    {
        $query = $this->testModel->newQuery();
        
        $parameters = [
            'status' => 'available',
            'name' => 'Fluffy'
        ];
        
        $result = $query->whereOpenApiMultiple($parameters);
        
        $this->assertTrue($result instanceof OpenApiQueryBuilder);
        $this->assertEquals($parameters, $result->getApiParameters());
    }

    /** @test */
    public function it_can_apply_openapi_filters()
    {
        $query = $this->testModel->newQuery();
        
        $filters = [
            'status' => 'available',
            'name' => 'Fluffy',
            'invalid_param' => 'should_be_ignored' // Should be filtered out
        ];
        
        $result = $query->applyOpenApiFilters($filters);
        
        $this->assertTrue($result instanceof OpenApiQueryBuilder);
        
        $apiParams = $result->getApiParameters();
        $this->assertArrayHasKey('status', $apiParams);
        $this->assertArrayHasKey('name', $apiParams);
        $this->assertArrayNotHasKey('invalid_param', $apiParams);
    }

    /** @test */
    public function it_can_order_by_openapi_attribute()
    {
        $query = $this->testModel->newQuery();
        
        $result = $query->orderByOpenApi('name', 'desc');
        
        $this->assertTrue($result instanceof OpenApiQueryBuilder);
        
        $apiParams = $result->getApiParameters();
        $this->assertEquals('name', $apiParams['sort']);
        $this->assertEquals('desc', $apiParams['order']);
    }

    /** @test */
    public function it_throws_exception_for_invalid_order_by_attribute()
    {
        $query = $this->testModel->newQuery();
        
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Attribute 'invalid_attribute' not found in OpenAPI schema for ordering");
        
        $query->orderByOpenApi('invalid_attribute');
    }

    /** @test */
    public function it_can_limit_with_openapi_validation()
    {
        $query = $this->testModel->newQuery();
        
        // Valid limit
        $result = $query->limitOpenApi(50);
        
        $this->assertTrue($result instanceof OpenApiQueryBuilder);
        $this->assertEquals(50, $result->getApiParameters()['limit']);
    }

    /** @test */
    public function it_throws_exception_for_limit_exceeding_maximum()
    {
        $query = $this->testModel->newQuery();
        
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Limit 150 exceeds maximum allowed limit of 100");
        
        $query->limitOpenApi(150);
    }

    /** @test */
    public function it_can_set_offset_with_validation()
    {
        $query = $this->testModel->newQuery();
        
        $result = $query->offsetOpenApi(10);
        
        $this->assertTrue($result instanceof OpenApiQueryBuilder);
        $this->assertEquals(10, $result->getApiParameters()['offset']);
    }

    /** @test */
    public function it_throws_exception_for_negative_offset()
    {
        $query = $this->testModel->newQuery();
        
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Offset cannot be negative");
        
        $query->offsetOpenApi(-5);
    }

    /** @test */
    public function it_handles_dynamic_where_methods()
    {
        $query = $this->testModel->newQuery();
        
        // Test whereByStatus method
        $result = $query->whereByStatus('available');
        
        $this->assertTrue($result instanceof OpenApiQueryBuilder);
        $this->assertEquals(['status' => 'available'], $result->getApiParameters());
    }

    /** @test */
    public function it_handles_dynamic_order_by_methods()
    {
        $query = $this->testModel->newQuery();
        
        // Test orderByName method
        $result = $query->orderByName('desc');
        
        $this->assertTrue($result instanceof OpenApiQueryBuilder);
        
        $apiParams = $result->getApiParameters();
        $this->assertEquals('name', $apiParams['sort']);
        $this->assertEquals('desc', $apiParams['order']);
    }

    /** @test */
    public function it_falls_back_to_regular_methods_for_non_openapi_models()
    {
        // Create a model without OpenAPI schema
        $regularModel = new class extends ApiModel {
            protected $table = 'regular_pets';
            
            public function newEloquentBuilder($query)
            {
                return new OpenApiQueryBuilder($query);
            }
        };
        
        $query = $regularModel->newQuery();
        
        // Should work like regular query builder
        $result = $query->where('name', '=', 'Fluffy');
        $this->assertTrue($result instanceof OpenApiQueryBuilder);
    }

    /** @test */
    public function it_validates_string_length_constraints()
    {
        $query = $this->testModel->newQuery();
        
        // Valid string length
        $query->whereOpenApi('name', '=', 'Fluffy');
        $this->assertEquals(['name' => 'Fluffy'], $query->getApiParameters());
        
        // String too long should throw exception
        $this->expectException(\InvalidArgumentException::class);
        $longName = str_repeat('a', 60); // Exceeds maxLength of 50
        $query->whereOpenApi('name', '=', $longName);
    }

    /** @test */
    public function it_validates_integer_constraints()
    {
        $query = $this->testModel->newQuery();
        
        // Valid integer within range
        $query->whereOpenApi('age', '=', 5);
        $this->assertEquals(['age' => 5], $query->getApiParameters());
        
        // Integer below minimum should throw exception
        $this->expectException(\InvalidArgumentException::class);
        $query->whereOpenApi('age', '=', -1);
    }

    /** @test */
    public function it_gets_default_per_page_from_openapi_schema()
    {
        $query = $this->testModel->newQuery();
        
        // Use reflection to test protected method
        $reflection = new \ReflectionClass($query);
        $method = $reflection->getMethod('getDefaultPerPage');
        $method->setAccessible(true);
        
        $defaultPerPage = $method->invoke($query);
        
        // Should get default from schema (20) or fallback (15)
        $this->assertTrue(is_int($defaultPerPage));
        $this->assertGreaterThan(0, $defaultPerPage);
    }

    /** @test */
    public function it_builds_validation_rules_from_parameter_definition()
    {
        $query = $this->testModel->newQuery();
        
        // Use reflection to test protected method
        $reflection = new \ReflectionClass($query);
        $method = $reflection->getMethod('buildValidationRulesFromDefinition');
        $method->setAccessible(true);
        
        $definition = [
            'type' => 'string',
            'maxLength' => 50,
            'enum' => ['available', 'pending', 'sold']
        ];
        
        $rules = $method->invoke($query, $definition);
        
        $this->assertContains('string', $rules);
        $this->assertContains('max:50', $rules);
        $this->assertContains('in:available,pending,sold', $rules);
    }

    /** @test */
    public function it_handles_complex_validation_rules()
    {
        $query = $this->testModel->newQuery();
        
        // Use reflection to test protected method
        $reflection = new \ReflectionClass($query);
        $method = $reflection->getMethod('buildValidationRulesFromDefinition');
        $method->setAccessible(true);
        
        $definition = [
            'type' => 'string',
            'format' => 'email',
            'pattern' => '^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$'
        ];
        
        $rules = $method->invoke($query, $definition);
        
        $this->assertContains('string', $rules);
        $this->assertContains('email', $rules);
        $this->assertTrue(collect($rules)->contains(function ($rule) {
            return str_starts_with($rule, 'regex:');
        }));
    }

    /** @test */
    public function it_returns_api_parameters()
    {
        $query = $this->testModel->newQuery();
        
        $query->whereOpenApi('status', '=', 'available')
              ->orderByOpenApi('name')
              ->limitOpenApi(25);
        
        $apiParams = $query->getApiParameters();
        
        $this->assertEquals('available', $apiParams['status']);
        $this->assertEquals('name', $apiParams['sort']);
        $this->assertEquals('asc', $apiParams['order']);
        $this->assertEquals(25, $apiParams['limit']);
    }

    // ==================== NEW OPENAPI QUERY BUILDER EXTENSION TESTS ====================

    /** @test */
    public function it_can_use_with_openapi_params_method()
    {
        $query = $this->testModel->newQuery();
        
        $params = [
            'status' => 'available',
            'limit' => 25,
            'name' => 'Fluffy'
        ];

        $result = $query->withOpenApiParams($params);
        
        $this->assertTrue($result instanceof OpenApiQueryBuilder);
        $apiParams = $result->getApiParameters();
        $this->assertEquals('available', $apiParams['status']);
        $this->assertEquals(25, $apiParams['limit']);
        $this->assertEquals('Fluffy', $apiParams['name']);
    }

    /** @test */
    public function it_validates_parameters_in_with_openapi_params()
    {
        $query = $this->testModel->newQuery();
        
        $params = [
            'status' => 'invalid_status', // Should fail enum validation
            'limit' => 25
        ];

        $this->expectException(\InvalidArgumentException::class);
        $query->withOpenApiParams($params);
    }

    /** @test */
    public function it_can_handle_openapi_filtering()
    {
        $query = $this->testModel->newQuery();
        
        $filters = [
            'status' => 'available',
            'name' => 'Fluffy'
        ];

        $result = $query->withOpenApiFiltering($filters);
        
        $this->assertTrue($result instanceof OpenApiQueryBuilder);
        $apiParams = $result->getApiParameters();
        $this->assertEquals('available', $apiParams['status']);
        $this->assertEquals('Fluffy', $apiParams['name']);
    }

    /** @test */
    public function it_can_handle_openapi_sorting()
    {
        $query = $this->testModel->newQuery();
        
        $result = $query->withOpenApiSorting('name', 'desc');
        
        $this->assertTrue($result instanceof OpenApiQueryBuilder);
        $apiParams = $result->getApiParameters();
        $this->assertEquals('name', $apiParams['sort']);
        $this->assertEquals('desc', $apiParams['order']);
    }

    /** @test */
    public function it_can_handle_openapi_pagination()
    {
        $query = $this->testModel->newQuery();
        
        $result = $query->withOpenApiPagination(2, 25);
        
        $this->assertTrue($result instanceof OpenApiQueryBuilder);
        $apiParams = $result->getApiParameters();
        $this->assertEquals(2, $apiParams['page']);
        $this->assertEquals(25, $apiParams['per_page']);
    }

    /** @test */
    public function it_can_handle_search_parameters()
    {
        $query = $this->testModel->newQuery();
        
        $result = $query->withOpenApiSearch('fluffy cat');
        
        $this->assertTrue($result instanceof OpenApiQueryBuilder);
        $this->assertEquals('fluffy cat', $result->getApiParameters()['search']);
    }

    /** @test */
    public function it_can_handle_range_filters()
    {
        $query = $this->testModel->newQuery();
        
        $result = $query->whereRange('price', 10.0, 50.0);
        
        $this->assertTrue($result instanceof OpenApiQueryBuilder);
        $apiParams = $result->getApiParameters();
        $this->assertEquals(10.0, $apiParams['price_min']);
        $this->assertEquals(50.0, $apiParams['price_max']);
    }

    /** @test */
    public function it_can_handle_in_filters()
    {
        $query = $this->testModel->newQuery();
        
        $result = $query->whereIn('status', ['available', 'pending']);
        
        $this->assertTrue($result instanceof OpenApiQueryBuilder);
        $apiParams = $result->getApiParameters();
        $this->assertIsArray($apiParams['status']);
        $this->assertContains('available', $apiParams['status']);
        $this->assertContains('pending', $apiParams['status']);
    }

    /** @test */
    public function it_can_handle_not_in_filters()
    {
        $query = $this->testModel->newQuery();
        
        $result = $query->whereNotIn('status', ['sold']);
        
        $this->assertTrue($result instanceof OpenApiQueryBuilder);
        $apiParams = $result->getApiParameters();
        $this->assertArrayHasKey('status', $apiParams);
    }

    /** @test */
    public function it_can_handle_operator_based_filters()
    {
        $query = $this->testModel->newQuery();
        
        $result = $query->whereOperator('limit', '>=', 10);
        
        $this->assertTrue($result instanceof OpenApiQueryBuilder);
        $apiParams = $result->getApiParameters();
        $this->assertArrayHasKey('limit', $apiParams);
    }

    /** @test */
    public function it_can_detect_parameter_purposes()
    {
        $query = $this->testModel->newQuery();
        
        // Test filter parameter
        $purpose = $query->detectParameterPurpose('status');
        $this->assertEquals('filter', $purpose);

        // Test sort parameter
        $purpose = $query->detectParameterPurpose('sort');
        $this->assertEquals('sort', $purpose);

        // Test pagination parameter
        $purpose = $query->detectParameterPurpose('page');
        $this->assertEquals('pagination', $purpose);

        // Test search parameter
        $purpose = $query->detectParameterPurpose('search');
        $this->assertEquals('search', $purpose);
    }

    /** @test */
    public function it_can_serialize_parameters()
    {
        $query = $this->testModel->newQuery();
        
        $query->whereOpenApi('tags', '=', ['red', 'blue']);
        
        $serialized = $query->serializeParameters();
        
        // Tags should be serialized as comma-separated string for simple style
        $this->assertEquals('red,blue', $serialized['tags']);
    }

    /** @test */
    public function it_can_chain_multiple_methods()
    {
        $query = $this->testModel->newQuery();
        
        $result = $query
            ->whereOpenApi('status', '=', 'available')
            ->withOpenApiSorting('name', 'asc')
            ->withOpenApiPagination(1, 10)
            ->withOpenApiSearch('fluffy');
        
        $this->assertTrue($result instanceof OpenApiQueryBuilder);
        
        $apiParams = $result->getApiParameters();
        $this->assertEquals('available', $apiParams['status']);
        $this->assertEquals(1, $apiParams['page']);
        $this->assertEquals(10, $apiParams['per_page']);
        $this->assertEquals('fluffy', $apiParams['search']);
    }

    /** @test */
    public function it_handles_type_conversion_in_parameters()
    {
        $query = $this->testModel->newQuery();
        
        // String to integer conversion
        $query->whereOpenApi('limit', '=', '25');
        $this->assertEquals(25, $query->getApiParameters()['limit']);

        // String to array conversion for tags
        $query->whereOpenApi('tags', '=', 'red,blue,green');
        $tags = $query->getApiParameters()['tags'];
        $this->assertIsArray($tags);
        $this->assertContains('red', $tags);
        $this->assertContains('blue', $tags);
        $this->assertContains('green', $tags);
    }

    /** @test */
    public function it_caches_parameter_definitions()
    {
        $query = $this->testModel->newQuery();
        
        // First call should load and cache
        $def1 = $query->getParameterDefinition('status');
        
        // Second call should use cache
        $def2 = $query->getParameterDefinition('status');
        
        $this->assertEquals($def1, $def2);
        $this->assertArrayHasKey('type', $def1);
        $this->assertEquals('string', $def1['type']);
    }

    /** @test */
    public function it_handles_date_parameter_validation()
    {
        $query = $this->testModel->newQuery();
        
        // Valid date
        $query->whereOpenApi('created_at', '=', '2023-12-25');
        $this->assertEquals('2023-12-25', $query->getApiParameters()['created_at']);

        // Invalid date format should throw exception
        $this->expectException(\InvalidArgumentException::class);
        $query->whereOpenApi('created_at', '=', 'invalid-date');
    }

    /** @test */
    public function it_can_clear_api_parameters()
    {
        $query = $this->testModel->newQuery();
        
        $query->whereOpenApi('status', '=', 'available');
        $query->whereOpenApi('limit', '=', 25);
        
        $this->assertNotEmpty($query->getApiParameters());
        
        // Clear parameters
        $query->clearApiParameters();
        
        $this->assertEmpty($query->getApiParameters());
    }

    /** @test */
    public function it_handles_minimum_length_validation()
    {
        $query = $this->testModel->newQuery();
        
        // Valid name (meets minLength requirement)
        $query->whereOpenApi('name', '=', 'Fluffy');
        $this->assertEquals('Fluffy', $query->getApiParameters()['name']);

        // Invalid name (too short)
        $this->expectException(\InvalidArgumentException::class);
        $query->whereOpenApi('name', '=', 'A'); // Less than minLength of 2
    }

    /** @test */
    public function it_handles_dynamic_scope_methods_via_call()
    {
        $query = $this->testModel->newQuery();
        
        // Test scopeWithStatus dynamic method
        $result = $query->scopeWithStatus('available');
        $this->assertTrue($result instanceof OpenApiQueryBuilder);
        $this->assertEquals('available', $result->getApiParameters()['status']);

        // Test scopeWithLimit dynamic method
        $result = $query->scopeWithLimit(50);
        $this->assertTrue($result instanceof OpenApiQueryBuilder);
        $this->assertEquals(50, $result->getApiParameters()['limit']);
    }

    /** @test */
    public function it_handles_array_parameter_serialization_styles()
    {
        $query = $this->testModel->newQuery();
        
        // Simple style (default for tags parameter)
        $query->whereOpenApi('tags', '=', ['red', 'blue']);
        $serialized = $query->serializeParameters();
        $this->assertEquals('red,blue', $serialized['tags']);
    }

    /** @test */
    public function it_validates_array_parameters()
    {
        $query = $this->testModel->newQuery();
        
        // Valid array
        $query->whereOpenApi('tags', '=', ['red', 'blue']);
        $this->assertIsArray($query->getApiParameters()['tags']);
        
        // Array from string should be converted
        $query->whereOpenApi('tags', '=', 'green,yellow');
        $tags = $query->getApiParameters()['tags'];
        $this->assertIsArray($tags);
        $this->assertContains('green', $tags);
        $this->assertContains('yellow', $tags);
    }

    /** @test */
    public function it_handles_pagination_parameter_validation()
    {
        $query = $this->testModel->newQuery();
        
        // Valid pagination
        $query->withOpenApiPagination(1, 50);
        $apiParams = $query->getApiParameters();
        $this->assertEquals(1, $apiParams['page']);
        $this->assertEquals(50, $apiParams['per_page']);

        // Invalid page (below minimum)
        $this->expectException(\InvalidArgumentException::class);
        $query->withOpenApiPagination(0, 50);
    }

    /** @test */
    public function it_handles_number_parameter_validation()
    {
        $query = $this->testModel->newQuery();
        
        // Valid number
        $query->whereOpenApi('price_min', '=', 25.50);
        $this->assertEquals(25.50, $query->getApiParameters()['price_min']);

        // Invalid number (below minimum)
        $this->expectException(\InvalidArgumentException::class);
        $query->whereOpenApi('price_min', '=', -10);
    }
}
