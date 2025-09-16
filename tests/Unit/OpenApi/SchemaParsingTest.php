<?php

namespace MTechStack\LaravelApiModelClient\Tests\Unit\OpenApi;

use MTechStack\LaravelApiModelClient\Tests\OpenApiTestCase;
use MTechStack\LaravelApiModelClient\OpenApi\OpenApiSchemaParser;
use MTechStack\LaravelApiModelClient\OpenApi\Exceptions\OpenApiParsingException;
use MTechStack\LaravelApiModelClient\OpenApi\Exceptions\SchemaValidationException;

/**
 * Unit tests for OpenAPI schema parsing functionality
 */
class SchemaParsingTest extends OpenApiTestCase
{
    protected OpenApiSchemaParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new OpenApiSchemaParser();
    }

    /**
     * Test parsing valid OpenAPI 3.0 schema
     */
    public function test_can_parse_valid_openapi_30_schema(): void
    {
        $schema = $this->fixtureManager->getSchema('petstore-3.0.0');
        
        $this->startBenchmark('parse_valid_schema');
        $result = $this->parser->parse($schema);
        $this->endBenchmark('parse_valid_schema');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('info', $result);
        $this->assertArrayHasKey('paths', $result);
        $this->assertArrayHasKey('components', $result);
        $this->assertEquals('3.0.0', $result['openapi']);
        
        // Assert performance
        $this->assertPerformanceMeetsCriteria('parse_valid_schema', 0.1, 10 * 1024 * 1024);
    }

    /**
     * Test parsing valid OpenAPI 3.1 schema
     */
    public function test_can_parse_valid_openapi_31_schema(): void
    {
        $schema = $this->fixtureManager->getSchema('petstore-3.1.0');
        
        $result = $this->parser->parse($schema);

        $this->assertIsArray($result);
        $this->assertEquals('3.1.0', $result['openapi']);
    }

    /**
     * Test parsing e-commerce API schema
     */
    public function test_can_parse_ecommerce_schema(): void
    {
        $schema = $this->fixtureManager->getSchema('ecommerce');
        
        $result = $this->parser->parse($schema);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('paths', $result);
        $this->assertArrayHasKey('/products', $result['paths']);
        $this->assertArrayHasKey('/orders', $result['paths']);
    }

    /**
     * Test parsing microservices API schema
     */
    public function test_can_parse_microservices_schema(): void
    {
        $schema = $this->fixtureManager->getSchema('microservices');
        
        $result = $this->parser->parse($schema);

        $this->assertIsArray($result);
        $this->assertEquals('3.1.0', $result['openapi']);
        $this->assertArrayHasKey('components', $result);
        $this->assertArrayHasKey('schemas', $result['components']);
        $this->assertArrayHasKey('User', $result['components']['schemas']);
    }

    /**
     * Test parsing invalid schema throws exception
     */
    public function test_parsing_invalid_schema_throws_exception(): void
    {
        $invalidSchema = $this->fixtureManager->createInvalidSchema();

        $this->expectException(OpenApiParsingException::class);
        $this->parser->parse($invalidSchema);
    }

    /**
     * Test parsing empty schema throws exception
     */
    public function test_parsing_empty_schema_throws_exception(): void
    {
        $this->expectException(SchemaValidationException::class);
        $this->parser->parse([]);
    }

    /**
     * Test extracting endpoints from schema
     */
    public function test_can_extract_endpoints_from_schema(): void
    {
        $schema = $this->fixtureManager->getSchema('petstore-3.0.0');
        
        $this->startBenchmark('extract_endpoints');
        $endpoints = $this->parser->extractEndpoints($schema);
        $this->endBenchmark('extract_endpoints');

        $this->assertIsArray($endpoints);
        $this->assertNotEmpty($endpoints);
        
        // Check for expected endpoints
        $this->assertArrayHasKey('listPets', $endpoints);
        $this->assertArrayHasKey('createPets', $endpoints);
        $this->assertArrayHasKey('showPetById', $endpoints);

        // Validate endpoint structure
        $listPetsEndpoint = $endpoints['listPets'];
        $this->assertArrayHasKey('method', $listPetsEndpoint);
        $this->assertArrayHasKey('path', $listPetsEndpoint);
        $this->assertArrayHasKey('parameters', $listPetsEndpoint);
        $this->assertEquals('GET', $listPetsEndpoint['method']);
        $this->assertEquals('/pets', $listPetsEndpoint['path']);

        // Assert performance
        $this->assertPerformanceMeetsCriteria('extract_endpoints', 0.05, 5 * 1024 * 1024);
    }

    /**
     * Test extracting schemas from OpenAPI specification
     */
    public function test_can_extract_schemas_from_specification(): void
    {
        $schema = $this->fixtureManager->getSchema('petstore-3.0.0');
        
        $this->startBenchmark('extract_schemas');
        $schemas = $this->parser->extractSchemas($schema);
        $this->endBenchmark('extract_schemas');

        $this->assertIsArray($schemas);
        $this->assertNotEmpty($schemas);
        
        // Check for expected schemas
        $this->assertArrayHasKey('Pet', $schemas);
        $this->assertArrayHasKey('Error', $schemas);

        // Validate schema structure
        $petSchema = $schemas['Pet'];
        $this->assertArrayHasKey('type', $petSchema);
        $this->assertArrayHasKey('properties', $petSchema);
        $this->assertArrayHasKey('required', $petSchema);
        $this->assertEquals('object', $petSchema['type']);

        // Assert performance
        $this->assertPerformanceMeetsCriteria('extract_schemas', 0.05, 5 * 1024 * 1024);
    }

    /**
     * Test generating validation rules from schema
     */
    public function test_can_generate_validation_rules_from_schema(): void
    {
        $schema = $this->fixtureManager->getSchema('petstore-3.0.0');
        $petSchema = $schema['components']['schemas']['Pet'];
        
        $this->startBenchmark('generate_validation_rules');
        $rules = $this->parser->generateValidationRules($petSchema);
        $this->endBenchmark('generate_validation_rules');

        $this->assertIsArray($rules);
        $this->assertNotEmpty($rules);
        
        // Check for expected validation rules
        $this->assertArrayHasKey('id', $rules);
        $this->assertArrayHasKey('name', $rules);
        
        // Validate rule structure
        $nameRules = $rules['name'];
        $this->assertContains('required', $nameRules);
        $this->assertContains('string', $nameRules);

        // Assert performance
        $this->assertPerformanceMeetsCriteria('generate_validation_rules', 0.02, 2 * 1024 * 1024);
    }

    /**
     * Test schema validation with different OpenAPI versions
     */
    public function test_validates_different_openapi_versions(): void
    {
        $versions = $this->getOpenApiVersionTestCases();

        foreach ($versions as $version => $schema) {
            $this->startBenchmark("validate_version_{$version}");
            
            try {
                $result = $this->parser->validateSchema($schema);
                $this->assertTrue($result, "Schema validation should pass for version {$version}");
            } catch (\Exception $e) {
                $this->fail("Schema validation failed for version {$version}: " . $e->getMessage());
            }
            
            $this->endBenchmark("validate_version_{$version}");
        }
    }

    /**
     * Test parsing schema with references
     */
    public function test_can_parse_schema_with_references(): void
    {
        $schema = $this->fixtureManager->getSchema('ecommerce');
        
        $result = $this->parser->parse($schema);
        $schemas = $this->parser->extractSchemas($schema);

        // Verify references are resolved
        $this->assertArrayHasKey('Product', $schemas);
        $this->assertArrayHasKey('Category', $schemas);
        $this->assertArrayHasKey('Tag', $schemas);

        $productSchema = $schemas['Product'];
        $this->assertArrayHasKey('category', $productSchema['properties']);
        $this->assertArrayHasKey('tags', $productSchema['properties']);
    }

    /**
     * Test parsing schema with nested objects
     */
    public function test_can_parse_schema_with_nested_objects(): void
    {
        $complexSchema = $this->fixtureManager->getEdgeCaseFixtures()['complex_nested'];
        
        $result = $this->parser->parse($complexSchema);
        $schemas = $this->parser->extractSchemas($complexSchema);

        $this->assertArrayHasKey('ComplexObject', $schemas);
        
        $complexObject = $schemas['ComplexObject'];
        $this->assertArrayHasKey('nested', $complexObject['properties']);
        
        $nestedProperty = $complexObject['properties']['nested'];
        $this->assertEquals('object', $nestedProperty['type']);
        $this->assertArrayHasKey('deep', $nestedProperty['properties']);
    }

    /**
     * Test parsing large schema for performance
     */
    public function test_can_parse_large_schema_efficiently(): void
    {
        $largeSchema = $this->fixtureManager->getEdgeCaseFixtures()['large_schema'];
        
        $this->startBenchmark('parse_large_schema');
        $result = $this->parser->parse($largeSchema);
        $this->endBenchmark('parse_large_schema');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('paths', $result);
        $this->assertGreaterThan(50, count($result['paths']));

        // Assert performance - large schema should still parse reasonably fast
        $this->assertPerformanceMeetsCriteria('parse_large_schema', 1.0, 50 * 1024 * 1024);
    }

    /**
     * Test parsing schema with security schemes
     */
    public function test_can_parse_schema_with_security_schemes(): void
    {
        $schema = $this->fixtureManager->getSchema('ecommerce');
        
        $result = $this->parser->parse($schema);

        $this->assertArrayHasKey('components', $result);
        $this->assertArrayHasKey('securitySchemes', $result['components']);
        $this->assertArrayHasKey('bearerAuth', $result['components']['securitySchemes']);
        
        $bearerAuth = $result['components']['securitySchemes']['bearerAuth'];
        $this->assertEquals('http', $bearerAuth['type']);
        $this->assertEquals('bearer', $bearerAuth['scheme']);
    }

    /**
     * Test error handling for malformed JSON
     */
    public function test_handles_malformed_json_gracefully(): void
    {
        $this->expectException(OpenApiParsingException::class);
        $this->expectExceptionMessage('Invalid JSON');
        
        // This would normally come from a file, but we're simulating malformed JSON
        $this->parser->parseFromString('{"invalid": json}');
    }

    /**
     * Test caching functionality
     */
    public function test_schema_caching_improves_performance(): void
    {
        $schema = $this->fixtureManager->getSchema('petstore-3.0.0');
        
        // First parse (no cache)
        $this->startBenchmark('first_parse');
        $result1 = $this->parser->parse($schema);
        $firstParseTime = $this->endBenchmark('first_parse')['execution_time'];

        // Second parse (with cache)
        $this->startBenchmark('cached_parse');
        $result2 = $this->parser->parse($schema);
        $cachedParseTime = $this->endBenchmark('cached_parse')['execution_time'];

        $this->assertEquals($result1, $result2);
        
        // Cached parse should be significantly faster (at least 50% improvement)
        // Note: This test might be flaky in very fast environments
        if ($firstParseTime > 0.001) { // Only assert if first parse took measurable time
            $this->assertLessThan($firstParseTime * 0.5, $cachedParseTime, 
                'Cached parsing should be at least 50% faster');
        }
    }

    /**
     * Test memory usage during parsing
     */
    public function test_memory_usage_stays_reasonable(): void
    {
        $schema = $this->fixtureManager->getSchema('petstore-3.0.0');
        
        $memoryBefore = memory_get_usage(true);
        
        for ($i = 0; $i < 10; $i++) {
            $this->parser->parse($schema);
        }
        
        $memoryAfter = memory_get_usage(true);
        $memoryIncrease = $memoryAfter - $memoryBefore;
        
        // Memory increase should be reasonable (less than 10MB for 10 parses)
        $this->assertLessThan(10 * 1024 * 1024, $memoryIncrease, 
            'Memory usage should stay reasonable during multiple parses');
    }

    /**
     * Test concurrent parsing (if applicable)
     */
    public function test_concurrent_parsing_safety(): void
    {
        $schema1 = $this->fixtureManager->getSchema('petstore-3.0.0');
        $schema2 = $this->fixtureManager->getSchema('ecommerce');
        
        // Simulate concurrent parsing
        $parser1 = new OpenApiSchemaParser();
        $parser2 = new OpenApiSchemaParser();
        
        $result1 = $parser1->parse($schema1);
        $result2 = $parser2->parse($schema2);
        
        // Results should be independent
        $this->assertNotEquals($result1['info']['title'], $result2['info']['title']);
        $this->assertEquals('Swagger Petstore', $result1['info']['title']);
        $this->assertEquals('E-commerce API', $result2['info']['title']);
    }

    /**
     * Test edge cases and boundary conditions
     */
    public function test_handles_edge_cases(): void
    {
        $edgeCases = $this->fixtureManager->getEdgeCaseFixtures();
        
        // Test minimal schema
        $minimalResult = $this->parser->parse($edgeCases['minimal_schema']);
        $this->assertIsArray($minimalResult);
        $this->assertEquals('3.0.0', $minimalResult['openapi']);
        
        // Test empty paths
        $this->assertArrayHasKey('paths', $minimalResult);
        $this->assertEmpty($minimalResult['paths']);
    }

    /**
     * Test schema validation error messages
     */
    public function test_provides_meaningful_validation_error_messages(): void
    {
        $invalidSchema = [
            'openapi' => '3.0.0',
            'info' => [
                'title' => 'Test API'
                // Missing required 'version' field
            ],
            'paths' => []
        ];

        try {
            $this->parser->validateSchema($invalidSchema);
            $this->fail('Should have thrown validation exception');
        } catch (SchemaValidationException $e) {
            $this->assertStringContainsString('version', $e->getMessage());
            $this->assertStringContainsString('required', strtolower($e->getMessage()));
        }
    }
}
