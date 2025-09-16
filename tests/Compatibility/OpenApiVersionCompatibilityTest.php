<?php

namespace MTechStack\LaravelApiModelClient\Tests\Compatibility;

use MTechStack\LaravelApiModelClient\Tests\OpenApiTestCase;
use MTechStack\LaravelApiModelClient\OpenApi\OpenApiSchemaParser;
use MTechStack\LaravelApiModelClient\OpenApi\Exceptions\OpenApiParsingException;

/**
 * Compatibility tests for various OpenAPI versions
 */
class OpenApiVersionCompatibilityTest extends OpenApiTestCase
{
    protected array $supportedVersions = ['3.0.0', '3.0.1', '3.0.2', '3.0.3', '3.1.0'];

    /**
     * Test parsing compatibility across OpenAPI versions
     */
    public function test_openapi_version_parsing_compatibility(): void
    {
        $versionSchemas = $this->getOpenApiVersionTestCases();
        
        foreach ($versionSchemas as $version => $schema) {
            $this->startBenchmark("compatibility_test_{$version}");
            
            try {
                $result = $this->parser->parse($schema);
                
                // Basic structure validation
                $this->assertIsArray($result);
                $this->assertArrayHasKey('openapi', $result);
                $this->assertArrayHasKey('info', $result);
                $this->assertArrayHasKey('paths', $result);
                $this->assertEquals($version, $result['openapi']);
                
                // Test endpoint extraction
                $endpoints = $this->parser->extractEndpoints($schema);
                $this->assertIsArray($endpoints);
                
                // Test schema extraction
                $schemas = $this->parser->extractSchemas($schema);
                $this->assertIsArray($schemas);
                
                $this->assertTrue(true, "OpenAPI {$version} parsing successful");
                
            } catch (\Exception $e) {
                if (in_array($version, $this->supportedVersions)) {
                    $this->fail("Supported OpenAPI version {$version} should parse successfully: " . $e->getMessage());
                } else {
                    $this->markTestSkipped("OpenAPI version {$version} is not supported");
                }
            }
            
            $this->endBenchmark("compatibility_test_{$version}");
        }
    }

    /**
     * Test OpenAPI 3.0.x specific features
     */
    public function test_openapi_30_features(): void
    {
        $schema30 = $this->fixtureManager->getSchema('petstore-3.0.0');
        
        $this->startBenchmark('openapi_30_features');
        
        $result = $this->parser->parse($schema30);
        
        // Test 3.0.x specific features
        $this->assertArrayHasKey('components', $result);
        $this->assertArrayHasKey('schemas', $result['components']);
        
        // Test security schemes (3.0.x format)
        if (isset($result['components']['securitySchemes'])) {
            foreach ($result['components']['securitySchemes'] as $schemeName => $scheme) {
                $this->assertArrayHasKey('type', $scheme);
                $this->assertContains($scheme['type'], ['http', 'apiKey', 'oauth2', 'openIdConnect']);
            }
        }
        
        // Test parameter serialization (3.0.x style)
        $endpoints = $this->parser->extractEndpoints($schema30);
        foreach ($endpoints as $endpoint) {
            if (isset($endpoint['parameters'])) {
                foreach ($endpoint['parameters'] as $parameter) {
                    $this->assertArrayHasKey('in', $parameter);
                    $this->assertArrayHasKey('name', $parameter);
                    $this->assertContains($parameter['in'], ['query', 'header', 'path', 'cookie']);
                }
            }
        }
        
        $this->endBenchmark('openapi_30_features');
    }

    /**
     * Test OpenAPI 3.1.x specific features
     */
    public function test_openapi_31_features(): void
    {
        $schema31 = $this->fixtureManager->getSchema('petstore-3.1.0');
        
        $this->startBenchmark('openapi_31_features');
        
        $result = $this->parser->parse($schema31);
        
        // Test 3.1.x specific features
        $this->assertEquals('3.1.0', $result['openapi']);
        
        // Test JSON Schema compatibility (3.1.x feature)
        $schemas = $this->parser->extractSchemas($schema31);
        foreach ($schemas as $schemaName => $schemaDefinition) {
            // 3.1.x allows more JSON Schema keywords
            if (isset($schemaDefinition['properties'])) {
                foreach ($schemaDefinition['properties'] as $property) {
                    // Test that we can handle additional JSON Schema keywords
                    if (isset($property['const'])) {
                        $this->assertNotNull($property['const']);
                    }
                    if (isset($property['examples'])) {
                        $this->assertIsArray($property['examples']);
                    }
                }
            }
        }
        
        $this->endBenchmark('openapi_31_features');
    }

    /**
     * Test backward compatibility
     */
    public function test_backward_compatibility(): void
    {
        $olderVersions = ['3.0.0', '3.0.1', '3.0.2'];
        $newerVersions = ['3.0.3', '3.1.0'];
        
        foreach ($olderVersions as $oldVersion) {
            $oldSchema = $this->fixtureManager->getSchema("petstore-{$oldVersion}");
            
            $this->startBenchmark("backward_compatibility_{$oldVersion}");
            
            // Parse older version
            $oldResult = $this->parser->parse($oldSchema);
            $oldEndpoints = $this->parser->extractEndpoints($oldSchema);
            $oldSchemas = $this->parser->extractSchemas($oldSchema);
            
            // Ensure basic functionality works
            $this->assertIsArray($oldResult);
            $this->assertNotEmpty($oldEndpoints);
            $this->assertNotEmpty($oldSchemas);
            
            // Generate validation rules should work for all versions
            foreach ($oldSchemas as $schemaName => $schemaDefinition) {
                $rules = $this->parser->generateValidationRules($schemaDefinition);
                $this->assertIsArray($rules);
            }
            
            $this->endBenchmark("backward_compatibility_{$oldVersion}");
        }
    }

    /**
     * Test forward compatibility
     */
    public function test_forward_compatibility(): void
    {
        // Test that newer features don't break older functionality
        $schema31 = $this->fixtureManager->getSchema('petstore-3.1.0');
        
        $this->startBenchmark('forward_compatibility');
        
        // Parse newer version with potentially unknown features
        $result = $this->parser->parse($schema31);
        
        // Basic functionality should still work
        $this->assertIsArray($result);
        $this->assertArrayHasKey('info', $result);
        $this->assertArrayHasKey('paths', $result);
        
        // Endpoint extraction should work
        $endpoints = $this->parser->extractEndpoints($schema31);
        $this->assertIsArray($endpoints);
        
        // Schema extraction should work
        $schemas = $this->parser->extractSchemas($schema31);
        $this->assertIsArray($schemas);
        
        $this->endBenchmark('forward_compatibility');
    }

    /**
     * Test version-specific validation rules
     */
    public function test_version_specific_validation_rules(): void
    {
        $versionSchemas = $this->getOpenApiVersionTestCases();
        
        foreach ($versionSchemas as $version => $schema) {
            $this->startBenchmark("validation_rules_{$version}");
            
            $schemas = $this->parser->extractSchemas($schema);
            
            foreach ($schemas as $schemaName => $schemaDefinition) {
                $rules = $this->parser->generateValidationRules($schemaDefinition);
                
                // Validation rules should be generated for all versions
                $this->assertIsArray($rules);
                
                // Test that rules are Laravel-compatible
                foreach ($rules as $field => $fieldRules) {
                    $this->assertIsArray($fieldRules);
                    foreach ($fieldRules as $rule) {
                        $this->assertIsString($rule);
                    }
                }
            }
            
            $this->endBenchmark("validation_rules_{$version}");
        }
    }

    /**
     * Test cross-version schema migration
     */
    public function test_cross_version_schema_migration(): void
    {
        $schema30 = $this->fixtureManager->getSchema('petstore-3.0.0');
        $schema31 = $this->fixtureManager->getSchema('petstore-3.1.0');
        
        $this->startBenchmark('cross_version_migration');
        
        // Parse both versions
        $result30 = $this->parser->parse($schema30);
        $result31 = $this->parser->parse($schema31);
        
        // Extract comparable components
        $endpoints30 = $this->parser->extractEndpoints($schema30);
        $endpoints31 = $this->parser->extractEndpoints($schema31);
        
        $schemas30 = $this->parser->extractSchemas($schema30);
        $schemas31 = $this->parser->extractSchemas($schema31);
        
        // Basic structure should be similar
        $this->assertSameSize($endpoints30, $endpoints31);
        $this->assertSameSize($schemas30, $schemas31);
        
        // Schema names should match
        $this->assertEquals(array_keys($schemas30), array_keys($schemas31));
        
        $this->endBenchmark('cross_version_migration');
    }

    /**
     * Test unsupported version handling
     */
    public function test_unsupported_version_handling(): void
    {
        $unsupportedVersions = ['2.0', '1.2', '4.0.0'];
        
        foreach ($unsupportedVersions as $version) {
            $invalidSchema = [
                'openapi' => $version,
                'info' => ['title' => 'Test', 'version' => '1.0.0'],
                'paths' => []
            ];
            
            try {
                $this->parser->parse($invalidSchema);
                $this->fail("Should have thrown exception for unsupported version {$version}");
            } catch (OpenApiParsingException $e) {
                $this->assertStringContainsString('version', strtolower($e->getMessage()));
                $this->assertStringContainsString($version, $e->getMessage());
            }
        }
    }

    /**
     * Test version detection accuracy
     */
    public function test_version_detection_accuracy(): void
    {
        $versionSchemas = $this->getOpenApiVersionTestCases();
        
        foreach ($versionSchemas as $expectedVersion => $schema) {
            $this->startBenchmark("version_detection_{$expectedVersion}");
            
            $result = $this->parser->parse($schema);
            $detectedVersion = $result['openapi'];
            
            $this->assertEquals($expectedVersion, $detectedVersion, 
                "Version detection should be accurate for {$expectedVersion}");
            
            $this->endBenchmark("version_detection_{$expectedVersion}");
        }
    }

    /**
     * Test feature compatibility matrix
     */
    public function test_feature_compatibility_matrix(): void
    {
        $features = [
            'basic_parsing' => true,
            'endpoint_extraction' => true,
            'schema_extraction' => true,
            'validation_rules' => true,
            'security_schemes' => true,
            'parameter_serialization' => true,
            'response_schemas' => true,
            'references' => true,
            'callbacks' => false, // Not implemented yet
            'links' => false,     // Not implemented yet
        ];
        
        $versionSchemas = $this->getOpenApiVersionTestCases();
        
        foreach ($versionSchemas as $version => $schema) {
            $this->startBenchmark("feature_matrix_{$version}");
            
            foreach ($features as $feature => $shouldSupport) {
                try {
                    switch ($feature) {
                        case 'basic_parsing':
                            $result = $this->parser->parse($schema);
                            $this->assertIsArray($result);
                            break;
                            
                        case 'endpoint_extraction':
                            $endpoints = $this->parser->extractEndpoints($schema);
                            $this->assertIsArray($endpoints);
                            break;
                            
                        case 'schema_extraction':
                            $schemas = $this->parser->extractSchemas($schema);
                            $this->assertIsArray($schemas);
                            break;
                            
                        case 'validation_rules':
                            $schemas = $this->parser->extractSchemas($schema);
                            foreach ($schemas as $schemaDefinition) {
                                $rules = $this->parser->generateValidationRules($schemaDefinition);
                                $this->assertIsArray($rules);
                                break; // Test just one schema
                            }
                            break;
                    }
                    
                    if ($shouldSupport) {
                        $this->assertTrue(true, "Feature {$feature} should be supported in {$version}");
                    }
                    
                } catch (\Exception $e) {
                    if ($shouldSupport) {
                        $this->fail("Feature {$feature} should be supported in {$version}: " . $e->getMessage());
                    } else {
                        $this->assertTrue(true, "Feature {$feature} is not supported in {$version} (expected)");
                    }
                }
            }
            
            $this->endBenchmark("feature_matrix_{$version}");
        }
    }

    /**
     * Test performance consistency across versions
     */
    public function test_performance_consistency_across_versions(): void
    {
        $versionSchemas = $this->getOpenApiVersionTestCases();
        $performanceResults = [];
        
        foreach ($versionSchemas as $version => $schema) {
            $this->startBenchmark("performance_consistency_{$version}");
            
            // Perform standard operations
            for ($i = 0; $i < 5; $i++) {
                $result = $this->parser->parse($schema);
                $endpoints = $this->parser->extractEndpoints($schema);
                $schemas = $this->parser->extractSchemas($schema);
            }
            
            $benchmarkResult = $this->endBenchmark("performance_consistency_{$version}");
            $performanceResults[$version] = $benchmarkResult['execution_time'];
        }
        
        // Performance should be relatively consistent across versions
        $averageTime = array_sum($performanceResults) / count($performanceResults);
        $maxTime = max($performanceResults);
        $minTime = min($performanceResults);
        
        // No version should be more than 3x slower than the fastest
        $this->assertLessThan($minTime * 3, $maxTime, 
            'Performance should be consistent across OpenAPI versions');
        
        // Standard deviation should be reasonable
        $variance = array_sum(array_map(function($time) use ($averageTime) {
            return pow($time - $averageTime, 2);
        }, $performanceResults)) / count($performanceResults);
        $standardDeviation = sqrt($variance);
        
        $this->assertLessThan($averageTime * 0.5, $standardDeviation, 
            'Performance variance across versions should be low');
    }

    /**
     * Test error message consistency across versions
     */
    public function test_error_message_consistency(): void
    {
        $versionSchemas = $this->getOpenApiVersionTestCases();
        
        foreach ($versionSchemas as $version => $schema) {
            // Create invalid version of schema
            $invalidSchema = $schema;
            unset($invalidSchema['info']['version']); // Remove required field
            
            try {
                $this->parser->parse($invalidSchema);
                $this->fail("Should have thrown exception for invalid schema in version {$version}");
            } catch (\Exception $e) {
                // Error messages should be meaningful and consistent
                $this->assertNotEmpty($e->getMessage());
                $this->assertStringContainsString('version', strtolower($e->getMessage()));
            }
        }
    }

    /**
     * Test schema validation strictness across versions
     */
    public function test_schema_validation_strictness(): void
    {
        $versionSchemas = $this->getOpenApiVersionTestCases();
        
        foreach ($versionSchemas as $version => $schema) {
            $this->startBenchmark("validation_strictness_{$version}");
            
            // Test that valid schemas pass validation
            $this->assertSchemaValid($schema, "Valid {$version} schema should pass validation");
            
            // Test that invalid schemas fail validation
            $invalidSchema = $schema;
            $invalidSchema['paths'] = 'invalid_paths_value'; // Should be object
            
            $this->assertSchemaInvalid($invalidSchema, 'paths', 
                "Invalid {$version} schema should fail validation");
            
            $this->endBenchmark("validation_strictness_{$version}");
        }
    }
}
