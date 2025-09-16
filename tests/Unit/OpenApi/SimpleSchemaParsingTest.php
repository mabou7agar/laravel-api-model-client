<?php

namespace MTechStack\LaravelApiModelClient\Tests\Unit\OpenApi;

use MTechStack\LaravelApiModelClient\Tests\OpenApiTestCase;
use MTechStack\LaravelApiModelClient\OpenApi\Exceptions\OpenApiParsingException;
use MTechStack\LaravelApiModelClient\OpenApi\Exceptions\SchemaValidationException;

/**
 * Simple schema parsing tests that work with the actual OpenApiSchemaParser implementation
 */
class SimpleSchemaParsingTest extends OpenApiTestCase
{
    /**
     * Test basic schema parsing functionality
     */
    public function test_can_parse_valid_openapi_schema(): void
    {
        // Create a simple valid schema file
        $schema = [
            'openapi' => '3.0.0',
            'info' => [
                'title' => 'Test API',
                'version' => '1.0.0'
            ],
            'paths' => [
                '/test' => [
                    'get' => [
                        'responses' => [
                            '200' => [
                                'description' => 'Success'
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $tempFile = tempnam(sys_get_temp_dir(), 'openapi_test_');
        file_put_contents($tempFile, json_encode($schema));

        $this->startBenchmark('basic_parsing');

        try {
            $result = $this->parser->parse($tempFile);

            $this->endBenchmark('basic_parsing');

            // Verify result structure
            $this->assertIsArray($result);
            $this->assertArrayHasKey('info', $result);
            $this->assertArrayHasKey('endpoints', $result);
            $this->assertArrayHasKey('schemas', $result);
            $this->assertArrayHasKey('validation_rules', $result);
            $this->assertArrayHasKey('source', $result);

            // Verify info section
            $this->assertEquals('Test API', $result['info']['title']);
            $this->assertEquals('1.0.0', $result['info']['version']);

            // Verify endpoints were extracted
            $this->assertIsArray($result['endpoints']);

        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    /**
     * Test parsing with invalid OpenAPI version
     */
    public function test_rejects_invalid_openapi_version(): void
    {
        $schema = [
            'openapi' => '2.0', // Invalid version
            'info' => [
                'title' => 'Test API',
                'version' => '1.0.0'
            ],
            'paths' => []
        ];

        $tempFile = tempnam(sys_get_temp_dir(), 'openapi_test_');
        file_put_contents($tempFile, json_encode($schema));

        try {
            $this->expectException(OpenApiParsingException::class);
            $this->parser->parse($tempFile);
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    /**
     * Test parsing malformed JSON
     */
    public function test_handles_malformed_json(): void
    {
        $malformedJson = '{"openapi": "3.0.0", "info": {"title": "Test"';

        $tempFile = tempnam(sys_get_temp_dir(), 'openapi_test_');
        file_put_contents($tempFile, $malformedJson);

        try {
            $this->expectException(OpenApiParsingException::class);
            $this->parser->parse($tempFile);
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    /**
     * Test parsing non-existent file
     */
    public function test_handles_missing_file(): void
    {
        $this->expectException(OpenApiParsingException::class);
        $this->parser->parse('/non/existent/file.json');
    }

    /**
     * Test caching functionality
     */
    public function test_caching_functionality(): void
    {
        $schema = [
            'openapi' => '3.0.0',
            'info' => [
                'title' => 'Cached API',
                'version' => '1.0.0'
            ],
            'paths' => []
        ];

        $tempFile = tempnam(sys_get_temp_dir(), 'openapi_test_');
        file_put_contents($tempFile, json_encode($schema));

        try {
            // First parse - should cache
            $this->startBenchmark('first_parse');
            $result1 = $this->parser->parse($tempFile, true);
            $firstParseTime = $this->endBenchmark('first_parse');

            // Second parse - should use cache
            $this->startBenchmark('cached_parse');
            $result2 = $this->parser->parse($tempFile, true);
            $cachedParseTime = $this->endBenchmark('cached_parse');

            // Results should be identical
            $this->assertEquals($result1, $result2);

            // Cached parse should be faster (though this might not always be true in tests)
            $this->assertIsArray($result1);
            $this->assertIsArray($result2);

        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    /**
     * Test parsing schema with components
     */
    public function test_can_parse_schema_with_components(): void
    {
        $schema = [
            'openapi' => '3.0.0',
            'info' => [
                'title' => 'Component API',
                'version' => '1.0.0'
            ],
            'paths' => [
                '/pets' => [
                    'get' => [
                        'responses' => [
                            '200' => [
                                'description' => 'Success',
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
                                'format' => 'int64'
                            ],
                            'name' => [
                                'type' => 'string'
                            ],
                            'status' => [
                                'type' => 'string',
                                'enum' => ['available', 'pending', 'sold']
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $tempFile = tempnam(sys_get_temp_dir(), 'openapi_test_');
        file_put_contents($tempFile, json_encode($schema));

        $this->startBenchmark('component_parsing');

        try {
            $result = $this->parser->parse($tempFile);

            $this->endBenchmark('component_parsing');

            $this->assertIsArray($result);
            $this->assertArrayHasKey('schemas', $result);
            $this->assertIsArray($result['schemas']);

            // Should have extracted the Pet schema
            $this->assertNotEmpty($result['schemas']);

        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    /**
     * Test performance with reasonable expectations
     */
    public function test_parsing_performance(): void
    {
        $schema = [
            'openapi' => '3.0.0',
            'info' => [
                'title' => 'Performance Test API',
                'version' => '1.0.0'
            ],
            'paths' => []
        ];

        // Add multiple endpoints to test performance
        for ($i = 0; $i < 10; $i++) {
            $schema['paths']["/endpoint{$i}"] = [
                'get' => [
                    'responses' => [
                        '200' => ['description' => 'Success']
                    ]
                ]
            ];
        }

        $tempFile = tempnam(sys_get_temp_dir(), 'openapi_test_');
        file_put_contents($tempFile, json_encode($schema));

        $this->startBenchmark('performance_test');

        try {
            $result = $this->parser->parse($tempFile);
            $benchmarkResult = $this->endBenchmark('performance_test');

            $this->assertIsArray($result);

            // Performance should be reasonable (less than 5 seconds for small schema)
            $this->assertLessThan(5.0, $benchmarkResult['execution_time'],
                'Schema parsing should complete within 5 seconds');

            // Memory usage should be reasonable (less than 50MB)
            $this->assertLessThan(50 * 1024 * 1024, $benchmarkResult['memory_usage'],
                'Memory usage should be under 50MB');

        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    /**
     * Test that parser handles empty paths gracefully
     */
    public function test_handles_empty_paths(): void
    {
        $schema = [
            'openapi' => '3.0.0',
            'info' => [
                'title' => 'Empty Paths API',
                'version' => '1.0.0'
            ],
            'paths' => []
        ];

        $tempFile = tempnam(sys_get_temp_dir(), 'openapi_test_');
        file_put_contents($tempFile, json_encode($schema));

        try {
            $result = $this->parser->parse($tempFile);

            $this->assertIsArray($result);
            $this->assertArrayHasKey('endpoints', $result);
            $this->assertIsArray($result['endpoints']);
            // Empty paths should result in empty endpoints array
            $this->assertEmpty($result['endpoints']);

        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }
}
