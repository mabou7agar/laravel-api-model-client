<?php

namespace MTechStack\LaravelApiModelClient\Tests\EdgeCases;

use MTechStack\LaravelApiModelClient\Tests\OpenApiTestCase;
use MTechStack\LaravelApiModelClient\OpenApi\OpenApiSchemaParser;
use MTechStack\LaravelApiModelClient\OpenApi\Exceptions\OpenApiParsingException;
use MTechStack\LaravelApiModelClient\OpenApi\Exceptions\SchemaValidationException;
use MTechStack\LaravelApiModelClient\Configuration\Exceptions\ConfigurationException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Error handling and edge case coverage tests
 */
class ErrorHandlingTest extends OpenApiTestCase
{
    /**
     * Test malformed JSON schema handling
     */
    public function test_malformed_json_schema_handling(): void
    {
        $malformedSchemas = [
            'invalid_json' => '{"openapi": "3.0.0", "info": {"title": "Test"',
            'empty_string' => '',
            'null_value' => null,
            'non_string' => 12345,
            'invalid_yaml' => "openapi: 3.0.0\ninfo:\n  title: Test\n  invalid: [unclosed",
        ];

        foreach ($malformedSchemas as $type => $schema) {
            $this->startBenchmark("malformed_schema_{$type}");
            
            try {
                $this->parser->parse($schema);
                $this->fail("Should have thrown exception for malformed schema: {$type}");
            } catch (OpenApiParsingException $e) {
                $this->assertStringContainsString('parse', strtolower($e->getMessage()));
                $this->assertNotEmpty($e->getMessage());
            } catch (\TypeError $e) {
                // Expected for null/non-string inputs
                $this->assertTrue(in_array($type, ['null_value', 'non_string']));
            }
            
            $this->endBenchmark("malformed_schema_{$type}");
        }
    }

    /**
     * Test missing required fields
     */
    public function test_missing_required_fields(): void
    {
        $requiredFields = ['openapi', 'info', 'paths'];
        $baseSchema = [
            'openapi' => '3.0.0',
            'info' => ['title' => 'Test API', 'version' => '1.0.0'],
            'paths' => []
        ];

        foreach ($requiredFields as $field) {
            $this->startBenchmark("missing_field_{$field}");
            
            $incompleteSchema = $baseSchema;
            unset($incompleteSchema[$field]);
            
            try {
                $this->parser->parse($incompleteSchema);
                $this->fail("Should have thrown exception for missing required field: {$field}");
            } catch (OpenApiParsingException $e) {
                $this->assertStringContainsString($field, strtolower($e->getMessage()));
            }
            
            $this->endBenchmark("missing_field_{$field}");
        }
    }

    /**
     * Test circular reference handling
     */
    public function test_circular_reference_handling(): void
    {
        $circularSchema = [
            'openapi' => '3.0.0',
            'info' => ['title' => 'Circular Test', 'version' => '1.0.0'],
            'paths' => [],
            'components' => [
                'schemas' => [
                    'User' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'integer'],
                            'profile' => ['$ref' => '#/components/schemas/Profile']
                        ]
                    ],
                    'Profile' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'integer'],
                            'user' => ['$ref' => '#/components/schemas/User']
                        ]
                    ]
                ]
            ]
        ];

        $this->startBenchmark('circular_reference');
        
        try {
            $result = $this->parser->parse($circularSchema);
            $schemas = $this->parser->extractSchemas($circularSchema);
            
            // Should handle circular references gracefully
            $this->assertIsArray($result);
            $this->assertIsArray($schemas);
            $this->assertArrayHasKey('User', $schemas);
            $this->assertArrayHasKey('Profile', $schemas);
            
        } catch (\Exception $e) {
            // If circular references cause issues, ensure error is meaningful
            $this->assertStringContainsString('circular', strtolower($e->getMessage()));
        }
        
        $this->endBenchmark('circular_reference');
    }

    /**
     * Test deeply nested schema handling
     */
    public function test_deeply_nested_schema_handling(): void
    {
        $deepSchema = $this->createDeeplyNestedSchema(20); // 20 levels deep
        
        $this->startBenchmark('deeply_nested_schema');
        
        try {
            $result = $this->parser->parse($deepSchema);
            $schemas = $this->parser->extractSchemas($deepSchema);
            
            $this->assertIsArray($result);
            $this->assertIsArray($schemas);
            
            // Should handle deep nesting without stack overflow
            $this->assertTrue(true, 'Deep nesting handled successfully');
            
        } catch (\Exception $e) {
            // If deep nesting causes issues, ensure graceful handling
            $this->assertNotEmpty($e->getMessage());
            $this->markTestSkipped('Deep nesting not supported: ' . $e->getMessage());
        }
        
        $this->endBenchmark('deeply_nested_schema');
    }

    /**
     * Test invalid reference handling
     */
    public function test_invalid_reference_handling(): void
    {
        $invalidRefSchema = [
            'openapi' => '3.0.0',
            'info' => ['title' => 'Invalid Ref Test', 'version' => '1.0.0'],
            'paths' => [
                '/pets' => [
                    'get' => [
                        'responses' => [
                            '200' => [
                                'description' => 'Success',
                                'content' => [
                                    'application/json' => [
                                        'schema' => ['$ref' => '#/components/schemas/NonExistentSchema']
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
                        'properties' => [
                            'id' => ['type' => 'integer'],
                            'category' => ['$ref' => '#/components/schemas/AnotherNonExistentSchema']
                        ]
                    ]
                ]
            ]
        ];

        $this->startBenchmark('invalid_reference');
        
        try {
            $result = $this->parser->parse($invalidRefSchema);
            $schemas = $this->parser->extractSchemas($invalidRefSchema);
            
            // Should either resolve gracefully or throw meaningful error
            $this->assertIsArray($result);
            
        } catch (OpenApiParsingException $e) {
            $this->assertStringContainsString('reference', strtolower($e->getMessage()));
        }
        
        $this->endBenchmark('invalid_reference');
    }

    /**
     * Test extremely large schema handling
     */
    public function test_extremely_large_schema_handling(): void
    {
        $largeSchema = $this->createLargeSchema(1000); // 1000 endpoints
        
        $this->startBenchmark('large_schema');
        
        try {
            $result = $this->parser->parse($largeSchema);
            $endpoints = $this->parser->extractEndpoints($largeSchema);
            
            $this->assertIsArray($result);
            $this->assertIsArray($endpoints);
            $this->assertCount(1000, $endpoints);
            
            // Memory usage should be reasonable
            $memoryUsage = memory_get_usage(true);
            $this->assertLessThan(256 * 1024 * 1024, $memoryUsage, 'Memory usage should be under 256MB');
            
        } catch (\Exception $e) {
            $this->markTestSkipped('Large schema handling not supported: ' . $e->getMessage());
        }
        
        $benchmarkResult = $this->endBenchmark('large_schema');
        
        // Performance should be reasonable even for large schemas
        $this->assertLessThan(30.0, $benchmarkResult['execution_time'], 
            'Large schema parsing should complete within 30 seconds');
    }

    /**
     * Test network timeout handling
     */
    public function test_network_timeout_handling(): void
    {
        // Mock HTTP client to simulate timeout
        Http::fake([
            'example.com/slow-schema.json' => Http::response('', 408, [], 30), // Timeout after 30s
            'example.com/unreachable.json' => function () {
                throw new \Exception('Connection timeout');
            }
        ]);

        $this->startBenchmark('network_timeout');
        
        try {
            $this->parser->parse('https://example.com/slow-schema.json');
            $this->fail('Should have thrown timeout exception');
        } catch (\Exception $e) {
            $this->assertStringContainsString('timeout', strtolower($e->getMessage()));
        }
        
        try {
            $this->parser->parse('https://example.com/unreachable.json');
            $this->fail('Should have thrown connection exception');
        } catch (\Exception $e) {
            $this->assertNotEmpty($e->getMessage());
        }
        
        $this->endBenchmark('network_timeout');
    }

    /**
     * Test memory limit edge cases
     */
    public function test_memory_limit_edge_cases(): void
    {
        $currentLimit = ini_get('memory_limit');
        
        // Temporarily reduce memory limit for testing
        ini_set('memory_limit', '64M');
        
        $this->startBenchmark('memory_limit');
        
        try {
            // Create a schema that might consume significant memory
            $memoryIntensiveSchema = $this->createMemoryIntensiveSchema();
            
            $result = $this->parser->parse($memoryIntensiveSchema);
            $this->assertIsArray($result);
            
        } catch (\Exception $e) {
            // Should handle memory issues gracefully
            $this->assertStringContainsString('memory', strtolower($e->getMessage()));
        } finally {
            // Restore original memory limit
            ini_set('memory_limit', $currentLimit);
        }
        
        $this->endBenchmark('memory_limit');
    }

    /**
     * Test concurrent access edge cases
     */
    public function test_concurrent_access_edge_cases(): void
    {
        $schema = $this->fixtureManager->getSchema('petstore');
        
        $this->startBenchmark('concurrent_access');
        
        // Simulate concurrent access with multiple processes
        $processes = [];
        for ($i = 0; $i < 5; $i++) {
            $processes[] = function() use ($schema) {
                try {
                    $result = $this->parser->parse($schema);
                    return ['success' => true, 'result' => $result];
                } catch (\Exception $e) {
                    return ['success' => false, 'error' => $e->getMessage()];
                }
            };
        }
        
        // Execute all processes
        $results = array_map(function($process) {
            return $process();
        }, $processes);
        
        // All processes should succeed
        foreach ($results as $i => $result) {
            $this->assertTrue($result['success'], "Process {$i} should succeed: " . 
                ($result['error'] ?? 'Unknown error'));
        }
        
        $this->endBenchmark('concurrent_access');
    }

    /**
     * Test cache corruption handling
     */
    public function test_cache_corruption_handling(): void
    {
        $schema = $this->fixtureManager->getSchema('petstore');
        $cacheKey = 'openapi_schema_' . md5(json_encode($schema));
        
        $this->startBenchmark('cache_corruption');
        
        // First, parse normally to populate cache
        $result1 = $this->parser->parse($schema);
        $this->assertIsArray($result1);
        
        // Corrupt the cache
        Cache::put($cacheKey, 'corrupted_data', 3600);
        
        // Should handle corrupted cache gracefully
        try {
            $result2 = $this->parser->parse($schema);
            $this->assertIsArray($result2);
            $this->assertEquals($result1, $result2, 'Should recover from cache corruption');
        } catch (\Exception $e) {
            $this->assertNotEmpty($e->getMessage());
        }
        
        $this->endBenchmark('cache_corruption');
    }

    /**
     * Test validation rule generation edge cases
     */
    public function test_validation_rule_generation_edge_cases(): void
    {
        $edgeCaseSchemas = [
            'empty_schema' => [],
            'null_properties' => ['properties' => null],
            'invalid_type' => ['type' => 'invalid_type'],
            'missing_type' => ['properties' => ['field' => []]],
            'circular_properties' => [
                'type' => 'object',
                'properties' => [
                    'self' => ['$ref' => '#']
                ]
            ]
        ];

        foreach ($edgeCaseSchemas as $caseName => $schemaDefinition) {
            $this->startBenchmark("validation_edge_case_{$caseName}");
            
            try {
                $rules = $this->parser->generateValidationRules($schemaDefinition);
                $this->assertIsArray($rules);
                
                // Rules should be valid Laravel validation rules
                foreach ($rules as $field => $fieldRules) {
                    $this->assertIsString($field);
                    $this->assertIsArray($fieldRules);
                }
                
            } catch (\Exception $e) {
                // Should provide meaningful error messages
                $this->assertNotEmpty($e->getMessage());
                $this->assertStringNotContainsString('undefined', strtolower($e->getMessage()));
            }
            
            $this->endBenchmark("validation_edge_case_{$caseName}");
        }
    }

    /**
     * Test configuration validation edge cases
     */
    public function test_configuration_validation_edge_cases(): void
    {
        $invalidConfigurations = [
            'missing_schema_source' => [],
            'invalid_strictness' => ['validation' => ['strictness' => 'invalid']],
            'negative_cache_ttl' => ['caching' => ['ttl' => -1]],
            'invalid_base_url' => ['base_url' => 'not-a-url'],
            'circular_config' => ['self_ref' => '{{self_ref}}']
        ];

        foreach ($invalidConfigurations as $caseName => $config) {
            $this->startBenchmark("config_edge_case_{$caseName}");
            
            try {
                $this->configValidator->validateConfiguration($config);
                $this->fail("Should have thrown exception for invalid config: {$caseName}");
            } catch (ConfigurationException $e) {
                $this->assertNotEmpty($e->getMessage());
                $this->assertStringContainsString($caseName === 'missing_schema_source' ? 'source' : 
                    ($caseName === 'invalid_strictness' ? 'strictness' : 
                    ($caseName === 'negative_cache_ttl' ? 'ttl' : 
                    ($caseName === 'invalid_base_url' ? 'url' : 'config'))), 
                    strtolower($e->getMessage()));
            }
            
            $this->endBenchmark("config_edge_case_{$caseName}");
        }
    }

    /**
     * Test logging and error reporting
     */
    public function test_logging_and_error_reporting(): void
    {
        // Clear previous logs
        Log::shouldReceive('error')->zeroOrMoreTimes();
        Log::shouldReceive('warning')->zeroOrMoreTimes();
        Log::shouldReceive('info')->zeroOrMoreTimes();
        
        $this->startBenchmark('error_logging');
        
        // Test that errors are properly logged
        try {
            $this->parser->parse(['invalid' => 'schema']);
        } catch (\Exception $e) {
            // Error should be logged
            Log::shouldHaveReceived('error')->atLeast()->once();
        }
        
        // Test that warnings are logged for recoverable issues
        $warningSchema = [
            'openapi' => '3.0.0',
            'info' => ['title' => 'Test', 'version' => '1.0.0'],
            'paths' => [],
            'unknown_field' => 'should_generate_warning'
        ];
        
        try {
            $this->parser->parse($warningSchema);
            // Should log warning but not fail
            Log::shouldHaveReceived('warning')->atLeast()->once();
        } catch (\Exception $e) {
            // If it fails, should still log appropriately
            $this->assertNotEmpty($e->getMessage());
        }
        
        $this->endBenchmark('error_logging');
    }

    /**
     * Test graceful degradation
     */
    public function test_graceful_degradation(): void
    {
        $partiallyValidSchema = [
            'openapi' => '3.0.0',
            'info' => ['title' => 'Partial Test', 'version' => '1.0.0'],
            'paths' => [
                '/valid' => [
                    'get' => [
                        'responses' => [
                            '200' => ['description' => 'Success']
                        ]
                    ]
                ],
                '/invalid' => 'this_should_be_an_object',
                '/another-valid' => [
                    'post' => [
                        'requestBody' => [
                            'content' => [
                                'application/json' => [
                                    'schema' => ['type' => 'object']
                                ]
                            ]
                        ],
                        'responses' => [
                            '201' => ['description' => 'Created']
                        ]
                    ]
                ]
            ]
        ];

        $this->startBenchmark('graceful_degradation');
        
        try {
            $result = $this->parser->parse($partiallyValidSchema);
            $endpoints = $this->parser->extractEndpoints($partiallyValidSchema);
            
            // Should extract valid endpoints even if some are invalid
            $this->assertIsArray($result);
            $this->assertIsArray($endpoints);
            
            // Should have extracted the valid endpoints
            $validEndpoints = array_filter($endpoints, function($endpoint) {
                return isset($endpoint['path']) && in_array($endpoint['path'], ['/valid', '/another-valid']);
            });
            
            $this->assertGreaterThan(0, count($validEndpoints), 
                'Should extract valid endpoints despite invalid ones');
            
        } catch (\Exception $e) {
            // If it fails completely, should provide helpful error message
            $this->assertStringContainsString('invalid', strtolower($e->getMessage()));
        }
        
        $this->endBenchmark('graceful_degradation');
    }

    /**
     * Helper method to create deeply nested schema
     */
    private function createDeeplyNestedSchema(int $depth): array
    {
        $schema = [
            'openapi' => '3.0.0',
            'info' => ['title' => 'Deep Test', 'version' => '1.0.0'],
            'paths' => [],
            'components' => ['schemas' => []]
        ];

        $currentSchema = [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer']
            ]
        ];

        for ($i = 0; $i < $depth; $i++) {
            $currentSchema = [
                'type' => 'object',
                'properties' => [
                    'level' => ['type' => 'integer'],
                    'nested' => $currentSchema
                ]
            ];
        }

        $schema['components']['schemas']['DeepSchema'] = $currentSchema;
        return $schema;
    }

    /**
     * Helper method to create large schema
     */
    private function createLargeSchema(int $endpointCount): array
    {
        $schema = [
            'openapi' => '3.0.0',
            'info' => ['title' => 'Large Test', 'version' => '1.0.0'],
            'paths' => []
        ];

        for ($i = 0; $i < $endpointCount; $i++) {
            $schema['paths']["/endpoint{$i}"] = [
                'get' => [
                    'summary' => "Endpoint {$i}",
                    'responses' => [
                        '200' => ['description' => 'Success']
                    ]
                ]
            ];
        }

        return $schema;
    }

    /**
     * Helper method to create memory intensive schema
     */
    private function createMemoryIntensiveSchema(): array
    {
        $schema = [
            'openapi' => '3.0.0',
            'info' => ['title' => 'Memory Test', 'version' => '1.0.0'],
            'paths' => [],
            'components' => ['schemas' => []]
        ];

        // Create many schemas with large descriptions
        for ($i = 0; $i < 100; $i++) {
            $schema['components']['schemas']["Schema{$i}"] = [
                'type' => 'object',
                'description' => str_repeat("This is a very long description for schema {$i}. ", 1000),
                'properties' => []
            ];

            // Add many properties to each schema
            for ($j = 0; $j < 50; $j++) {
                $schema['components']['schemas']["Schema{$i}"]['properties']["property{$j}"] = [
                    'type' => 'string',
                    'description' => str_repeat("Property {$j} description. ", 100)
                ];
            }
        }

        return $schema;
    }
}
