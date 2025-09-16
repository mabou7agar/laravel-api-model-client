<?php

namespace MTechStack\LaravelApiModelClient\Tests\Performance;

use MTechStack\LaravelApiModelClient\Tests\OpenApiTestCase;
use MTechStack\LaravelApiModelClient\OpenApi\OpenApiSchemaParser;
use MTechStack\LaravelApiModelClient\Configuration\ValidationStrictnessManager;

/**
 * Performance tests for OpenAPI integration components
 */
class OpenApiPerformanceTest extends OpenApiTestCase
{
    protected array $performanceThresholds = [
        'schema_parsing' => ['time' => 0.5, 'memory' => 20 * 1024 * 1024], // 500ms, 20MB
        'validation' => ['time' => 0.01, 'memory' => 1 * 1024 * 1024],     // 10ms, 1MB
        'caching' => ['time' => 0.1, 'memory' => 5 * 1024 * 1024],         // 100ms, 5MB
        'large_schema' => ['time' => 2.0, 'memory' => 100 * 1024 * 1024],  // 2s, 100MB
    ];

    /**
     * Test schema parsing performance
     */
    public function test_schema_parsing_performance(): void
    {
        $schemas = [
            'small' => $this->fixtureManager->getSchema('petstore-3.0.0'),
            'medium' => $this->fixtureManager->getSchema('ecommerce'),
            'large' => $this->fixtureManager->getEdgeCaseFixtures()['large_schema']
        ];

        foreach ($schemas as $size => $schema) {
            $this->startBenchmark("parse_schema_{$size}");
            
            for ($i = 0; $i < 10; $i++) {
                $result = $this->parser->parse($schema);
                $this->assertIsArray($result);
            }
            
            $benchmarkResult = $this->endBenchmark("parse_schema_{$size}");
            
            // Performance assertions based on schema size
            $timeThreshold = $size === 'large' ? 2.0 : ($size === 'medium' ? 1.0 : 0.5);
            $memoryThreshold = $size === 'large' ? 50 * 1024 * 1024 : 20 * 1024 * 1024;
            
            $this->assertLessThan($timeThreshold, $benchmarkResult['execution_time'], 
                "Schema parsing for {$size} schema should complete within {$timeThreshold}s");
            
            $this->assertLessThan($memoryThreshold, $benchmarkResult['memory_usage'], 
                "Memory usage for {$size} schema should be less than " . ($memoryThreshold / 1024 / 1024) . "MB");
        }
    }

    /**
     * Test validation performance across strictness levels
     */
    public function test_validation_performance(): void
    {
        $schema = $this->fixtureManager->getSchema('ecommerce');
        $productSchema = $schema['components']['schemas']['Product'];
        $rules = $this->validationHelper->generateLaravelRules($productSchema);
        
        $testData = [
            'name' => 'Performance Test Product',
            'price' => 99.99,
            'in_stock' => true,
            'category' => ['id' => 1, 'name' => 'Electronics']
        ];

        $strictnessLevels = ['strict', 'moderate', 'lenient'];
        
        foreach ($strictnessLevels as $level) {
            $strictnessManager = new ValidationStrictnessManager('testing');
            $strictnessManager->setStrictnessLevel($level);
            
            $this->startBenchmark("validation_performance_{$level}");
            
            // Validate same data 1000 times
            for ($i = 0; $i < 1000; $i++) {
                $result = $strictnessManager->validateParameters($testData, $rules);
                $this->assertTrue($result['valid']);
            }
            
            $benchmarkResult = $this->endBenchmark("validation_performance_{$level}");
            
            // Should validate 1000 items quickly
            $this->assertLessThan(1.0, $benchmarkResult['execution_time'], 
                "1000 validations in {$level} mode should complete within 1 second");
            
            $this->assertLessThan(10 * 1024 * 1024, $benchmarkResult['memory_usage'], 
                "Memory usage for 1000 validations should be less than 10MB");
        }
    }

    /**
     * Test caching performance improvements
     */
    public function test_caching_performance(): void
    {
        $schema = $this->fixtureManager->getSchema('petstore-3.0.0');
        
        // Test without caching
        config(['api-client.schemas.testing.caching.enabled' => false]);
        
        $this->startBenchmark('parsing_without_cache');
        for ($i = 0; $i < 5; $i++) {
            $this->parser->parse($schema);
        }
        $noCacheResult = $this->endBenchmark('parsing_without_cache');
        
        // Test with caching
        config(['api-client.schemas.testing.caching.enabled' => true]);
        
        $this->startBenchmark('parsing_with_cache');
        for ($i = 0; $i < 5; $i++) {
            $this->parser->parse($schema);
        }
        $cacheResult = $this->endBenchmark('parsing_with_cache');
        
        // Caching should provide significant performance improvement
        $improvementRatio = $noCacheResult['execution_time'] / $cacheResult['execution_time'];
        $this->assertGreaterThan(1.5, $improvementRatio, 
            'Caching should provide at least 50% performance improvement');
    }

    /**
     * Test memory usage patterns
     */
    public function test_memory_usage_patterns(): void
    {
        $schemas = [
            'petstore' => $this->fixtureManager->getSchema('petstore-3.0.0'),
            'ecommerce' => $this->fixtureManager->getSchema('ecommerce'),
            'microservices' => $this->fixtureManager->getSchema('microservices')
        ];

        $memoryBefore = memory_get_usage(true);
        $peakMemoryBefore = memory_get_peak_usage(true);
        
        $this->startBenchmark('memory_usage_test');
        
        // Process multiple schemas multiple times
        for ($iteration = 0; $iteration < 3; $iteration++) {
            foreach ($schemas as $name => $schema) {
                $result = $this->parser->parse($schema);
                $endpoints = $this->parser->extractEndpoints($schema);
                $extractedSchemas = $this->parser->extractSchemas($schema);
                
                // Generate validation rules for all schemas
                foreach ($extractedSchemas as $schemaName => $schemaDefinition) {
                    $rules = $this->parser->generateValidationRules($schemaDefinition);
                }
            }
        }
        
        $memoryResult = $this->endBenchmark('memory_usage_test');
        
        $memoryAfter = memory_get_usage(true);
        $peakMemoryAfter = memory_get_peak_usage(true);
        
        $memoryIncrease = $memoryAfter - $memoryBefore;
        $peakMemoryIncrease = $peakMemoryAfter - $peakMemoryBefore;
        
        // Memory usage should be reasonable
        $this->assertLessThan(50 * 1024 * 1024, $memoryIncrease, 
            'Memory increase should be less than 50MB');
        
        $this->assertLessThan(100 * 1024 * 1024, $peakMemoryIncrease, 
            'Peak memory increase should be less than 100MB');
    }

    /**
     * Test concurrent processing performance
     */
    public function test_concurrent_processing_performance(): void
    {
        $schemas = [
            $this->fixtureManager->getSchema('petstore-3.0.0'),
            $this->fixtureManager->getSchema('ecommerce'),
            $this->fixtureManager->getSchema('microservices')
        ];

        $this->startBenchmark('concurrent_processing');
        
        // Simulate concurrent processing with multiple parser instances
        $parsers = [];
        $results = [];
        
        for ($i = 0; $i < 3; $i++) {
            $parsers[$i] = new OpenApiSchemaParser();
        }
        
        // Process schemas concurrently (simulated)
        foreach ($schemas as $index => $schema) {
            $results[$index] = $parsers[$index]->parse($schema);
        }
        
        $concurrentResult = $this->endBenchmark('concurrent_processing');
        
        $this->assertCount(3, $results);
        $this->assertLessThan(1.0, $concurrentResult['execution_time'], 
            'Concurrent processing should be efficient');
    }

    /**
     * Test performance with different OpenAPI versions
     */
    public function test_version_parsing_performance(): void
    {
        $versionSchemas = $this->getOpenApiVersionTestCases();
        
        foreach ($versionSchemas as $version => $schema) {
            $this->startBenchmark("parse_version_{$version}");
            
            // Parse each version multiple times
            for ($i = 0; $i < 5; $i++) {
                $result = $this->parser->parse($schema);
                $this->assertIsArray($result);
                $this->assertEquals($version, $result['openapi']);
            }
            
            $versionResult = $this->endBenchmark("parse_version_{$version}");
            
            // All versions should parse efficiently
            $this->assertLessThan(0.5, $versionResult['execution_time'], 
                "OpenAPI {$version} parsing should complete within 500ms");
        }
    }

    /**
     * Test performance degradation with schema complexity
     */
    public function test_complexity_performance_impact(): void
    {
        $complexityLevels = [
            'simple' => $this->createSimpleSchema(),
            'moderate' => $this->fixtureManager->getSchema('petstore-3.0.0'),
            'complex' => $this->fixtureManager->getSchema('ecommerce'),
            'very_complex' => $this->fixtureManager->getEdgeCaseFixtures()['complex_nested']
        ];

        $performanceResults = [];
        
        foreach ($complexityLevels as $level => $schema) {
            $this->startBenchmark("complexity_{$level}");
            
            $result = $this->parser->parse($schema);
            $endpoints = $this->parser->extractEndpoints($schema);
            $schemas = $this->parser->extractSchemas($schema);
            
            $complexityResult = $this->endBenchmark("complexity_{$level}");
            $performanceResults[$level] = $complexityResult['execution_time'];
        }
        
        // Performance should degrade gracefully with complexity
        $this->assertLessThan($performanceResults['simple'] * 10, $performanceResults['very_complex'], 
            'Very complex schemas should not be more than 10x slower than simple schemas');
    }

    /**
     * Test validation performance with large datasets
     */
    public function test_large_dataset_validation_performance(): void
    {
        $schema = $this->fixtureManager->getSchema('ecommerce');
        $productSchema = $schema['components']['schemas']['Product'];
        $rules = $this->validationHelper->generateLaravelRules($productSchema);
        
        // Generate large dataset
        $largeDataset = [];
        for ($i = 0; $i < 1000; $i++) {
            $largeDataset[] = [
                'name' => "Product {$i}",
                'price' => rand(10, 1000),
                'in_stock' => rand(0, 1) === 1,
                'category' => ['id' => rand(1, 10), 'name' => 'Category ' . rand(1, 10)]
            ];
        }

        $strictnessManager = new ValidationStrictnessManager('testing');
        
        $this->startBenchmark('large_dataset_validation');
        
        $validCount = 0;
        foreach ($largeDataset as $data) {
            $result = $strictnessManager->validateParameters($data, $rules);
            if ($result['valid']) {
                $validCount++;
            }
        }
        
        $largeDatasetResult = $this->endBenchmark('large_dataset_validation');
        
        $this->assertGreaterThan(0, $validCount);
        $this->assertLessThan(5.0, $largeDatasetResult['execution_time'], 
            'Validating 1000 items should complete within 5 seconds');
        
        // Calculate items per second
        $itemsPerSecond = 1000 / $largeDatasetResult['execution_time'];
        $this->assertGreaterThan(200, $itemsPerSecond, 
            'Should validate at least 200 items per second');
    }

    /**
     * Test performance regression over time
     */
    public function test_performance_regression(): void
    {
        $schema = $this->fixtureManager->getSchema('petstore-3.0.0');
        
        $performanceSamples = [];
        
        // Take multiple performance samples
        for ($sample = 0; $sample < 10; $sample++) {
            $this->startBenchmark("regression_sample_{$sample}");
            
            $result = $this->parser->parse($schema);
            $endpoints = $this->parser->extractEndpoints($schema);
            $schemas = $this->parser->extractSchemas($schema);
            
            $sampleResult = $this->endBenchmark("regression_sample_{$sample}");
            $performanceSamples[] = $sampleResult['execution_time'];
        }
        
        // Calculate performance statistics
        $averageTime = array_sum($performanceSamples) / count($performanceSamples);
        $maxTime = max($performanceSamples);
        $minTime = min($performanceSamples);
        $variance = array_sum(array_map(function($time) use ($averageTime) {
            return pow($time - $averageTime, 2);
        }, $performanceSamples)) / count($performanceSamples);
        $standardDeviation = sqrt($variance);
        
        // Performance should be consistent
        $this->assertLessThan($averageTime * 2, $maxTime, 
            'Maximum execution time should not be more than 2x average');
        
        $this->assertLessThan($averageTime * 0.5, $standardDeviation, 
            'Performance should be consistent (low standard deviation)');
    }

    /**
     * Generate performance report
     */
    public function test_generate_performance_report(): void
    {
        // Run a comprehensive performance test
        $this->test_schema_parsing_performance();
        $this->test_validation_performance();
        $this->test_caching_performance();
        
        // Generate and validate performance report
        $report = $this->benchmark->generateReport();
        
        $this->assertIsArray($report);
        $this->assertArrayHasKey('summary', $report);
        $this->assertArrayHasKey('operations', $report);
        
        // Log performance report for analysis
        $this->benchmark->logResults('info');
        
        // Assert overall performance meets criteria
        $this->assertLessThan(10.0, $report['summary']['total_time'], 
            'Total test execution time should be reasonable');
    }

    /**
     * Create a simple schema for performance testing
     */
    protected function createSimpleSchema(): array
    {
        return [
            'openapi' => '3.0.0',
            'info' => [
                'title' => 'Simple API',
                'version' => '1.0.0'
            ],
            'paths' => [
                '/simple' => [
                    'get' => [
                        'responses' => [
                            '200' => [
                                'description' => 'Success',
                                'content' => [
                                    'application/json' => [
                                        'schema' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'id' => ['type' => 'integer'],
                                                'name' => ['type' => 'string']
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];
    }

    /**
     * Benchmark a specific operation multiple times
     */
    protected function benchmarkOperation(string $operation, callable $callback, int $iterations = 10): array
    {
        $results = $this->benchmark->benchmarkIterations($operation, $callback, $iterations);
        
        // Assert performance criteria
        $this->assertLessThan(1.0, $results['average_time'], 
            "Average time for {$operation} should be less than 1 second");
        
        $this->assertGreaterThan(90, $results['success_rate'], 
            "Success rate for {$operation} should be greater than 90%");
        
        return $results;
    }
}
