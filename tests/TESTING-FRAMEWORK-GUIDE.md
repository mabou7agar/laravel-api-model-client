# OpenAPI Integration Testing Framework Guide

This comprehensive testing framework provides extensive coverage for OpenAPI integration in the Laravel API Model Client package. The framework includes unit tests, integration tests, performance tests, compatibility tests, and edge case coverage.

## Table of Contents

1. [Framework Overview](#framework-overview)
2. [Test Structure](#test-structure)
3. [Base Test Case](#base-test-case)
4. [Testing Utilities](#testing-utilities)
5. [Test Categories](#test-categories)
6. [Running Tests](#running-tests)
7. [Writing Custom Tests](#writing-custom-tests)
8. [Performance Benchmarking](#performance-benchmarking)
9. [Mock Data Generation](#mock-data-generation)
10. [Configuration](#configuration)

## Framework Overview

The OpenAPI Integration Testing Framework is designed to provide comprehensive coverage for:

- **Schema Parsing**: Validation of OpenAPI schema parsing across different versions
- **Parameter Validation**: Testing validation strictness levels and rule generation
- **Integration Testing**: End-to-end testing with real OpenAPI specifications
- **Performance Testing**: Benchmarking and performance regression detection
- **Compatibility Testing**: Cross-version compatibility and feature support
- **Edge Case Coverage**: Error handling, malformed data, and boundary conditions

## Test Structure

```
tests/
├── OpenApiTestCase.php              # Base test case with common setup
├── Utilities/                       # Testing utility classes
│   ├── MockApiServer.php           # HTTP server simulation
│   ├── SchemaFixtureManager.php    # Schema fixture management
│   ├── PerformanceBenchmark.php    # Performance measurement
│   ├── MockDataGenerator.php      # Test data generation
│   └── ParameterValidationHelper.php # Validation testing helpers
├── Unit/                           # Unit tests
│   ├── OpenApi/
│   │   └── SchemaParsingTest.php   # Schema parsing unit tests
│   └── Configuration/
│       └── ParameterValidationTest.php # Validation unit tests
├── Integration/                    # Integration tests
│   ├── OpenApiIntegrationTest.php  # End-to-end integration tests
│   └── QueryBuilderIntegrationTest.php # Query builder tests
├── Performance/                    # Performance tests
│   └── OpenApiPerformanceTest.php  # Performance benchmarking
├── Compatibility/                  # Compatibility tests
│   └── OpenApiVersionCompatibilityTest.php # Version compatibility
├── EdgeCases/                      # Edge case and error handling
│   └── ErrorHandlingTest.php      # Error handling and edge cases
└── fixtures/                      # Test fixtures and schemas
    └── schemas/                   # OpenAPI schema fixtures
```

## Base Test Case

The `OpenApiTestCase` class provides common functionality for all OpenAPI tests:

```php
use MTechStack\LaravelApiModelClient\Tests\OpenApiTestCase;

class MyOpenApiTest extends OpenApiTestCase
{
    public function test_my_feature(): void
    {
        // Access to pre-configured utilities
        $schema = $this->fixtureManager->getSchema('petstore');
        $mockData = $this->mockDataGenerator->generateFromSchema($schema);
        
        // Performance benchmarking
        $this->startBenchmark('my_test');
        
        // Your test logic here
        $result = $this->parser->parse($schema);
        
        $this->endBenchmark('my_test');
        
        // Assertions
        $this->assertIsArray($result);
    }
}
```

## Testing Utilities

### MockApiServer

Simulates HTTP API endpoints for testing:

```php
// Setup mock responses
$this->mockServer->addRoute('GET', '/pets', [
    'status' => 200,
    'body' => ['pets' => []]
]);

// Start server
$this->mockServer->start();
```

### SchemaFixtureManager

Manages OpenAPI schema fixtures:

```php
// Get predefined schemas
$petstoreSchema = $this->fixtureManager->getSchema('petstore');
$ecommerceSchema = $this->fixtureManager->getSchema('ecommerce');
$invalidSchema = $this->fixtureManager->getSchema('invalid');

// Get schema variations
$largeSchema = $this->fixtureManager->getSchema('large');
$nestedSchema = $this->fixtureManager->getSchema('nested');
```

### PerformanceBenchmark

Measures execution time and memory usage:

```php
// Start benchmark
$this->startBenchmark('operation_name');

// Your code here
performOperation();

// End benchmark and get results
$result = $this->endBenchmark('operation_name');
// $result contains: execution_time, memory_usage, peak_memory

// Compare performance
$this->benchmark->compare('operation_a', 'operation_b');
```

### MockDataGenerator

Generates test data from OpenAPI schemas:

```php
// Generate valid data
$validData = $this->mockDataGenerator->generateFromSchema($schema);

// Generate invalid data for testing
$invalidData = $this->mockDataGenerator->generateInvalidData($schema);

// Generate edge case data
$edgeCaseData = $this->mockDataGenerator->generateEdgeCaseData($schema);
```

### ParameterValidationHelper

Assists with validation testing:

```php
// Create validation test cases
$testCases = $this->validationHelper->createValidationTestCases($schema);

// Run validation tests with different strictness levels
$results = $this->validationHelper->runValidationTests($testCases, 'strict');

// Generate Laravel validation rules
$rules = $this->validationHelper->generateValidationRules($schema);
```

## Test Categories

### Unit Tests

Test individual components in isolation:

- **SchemaParsingTest**: Tests OpenAPI schema parsing functionality
- **ParameterValidationTest**: Tests parameter validation with different strictness levels

### Integration Tests

Test complete workflows and component interaction:

- **OpenApiIntegrationTest**: End-to-end testing with real OpenAPI specifications
- **QueryBuilderIntegrationTest**: Tests OpenAPI query builder integration

### Performance Tests

Benchmark and monitor performance:

- **OpenApiPerformanceTest**: Performance benchmarking for schema operations

### Compatibility Tests

Test cross-version compatibility:

- **OpenApiVersionCompatibilityTest**: Tests compatibility across OpenAPI versions

### Edge Case Tests

Test error handling and boundary conditions:

- **ErrorHandlingTest**: Tests error handling, malformed data, and edge cases

## Running Tests

### Run All Tests

```bash
# Run complete test suite
./vendor/bin/phpunit

# Run with coverage
./vendor/bin/phpunit --coverage-html coverage/
```

### Run Specific Test Categories

```bash
# Unit tests only
./vendor/bin/phpunit tests/Unit/

# Integration tests only
./vendor/bin/phpunit tests/Integration/

# Performance tests only
./vendor/bin/phpunit tests/Performance/

# Compatibility tests only
./vendor/bin/phpunit tests/Compatibility/

# Edge case tests only
./vendor/bin/phpunit tests/EdgeCases/
```

### Run Individual Test Classes

```bash
# Run schema parsing tests
./vendor/bin/phpunit tests/Unit/OpenApi/SchemaParsingTest.php

# Run integration tests
./vendor/bin/phpunit tests/Integration/OpenApiIntegrationTest.php
```

### Run with Filters

```bash
# Run tests matching pattern
./vendor/bin/phpunit --filter="test_schema_parsing"

# Run tests in specific group
./vendor/bin/phpunit --group="performance"
```

## Writing Custom Tests

### Creating a New Test Class

```php
<?php

namespace MTechStack\LaravelApiModelClient\Tests\Custom;

use MTechStack\LaravelApiModelClient\Tests\OpenApiTestCase;

class MyCustomTest extends OpenApiTestCase
{
    public function test_my_custom_functionality(): void
    {
        // Setup
        $schema = $this->fixtureManager->getSchema('petstore');
        
        // Start performance measurement
        $this->startBenchmark('custom_test');
        
        // Test logic
        $result = $this->parser->parse($schema);
        
        // Assertions
        $this->assertIsArray($result);
        $this->assertArrayHasKey('openapi', $result);
        
        // End performance measurement
        $benchmarkResult = $this->endBenchmark('custom_test');
        
        // Performance assertions
        $this->assertLessThan(1.0, $benchmarkResult['execution_time']);
    }
}
```

### Adding Custom Schema Fixtures

```php
// In your test setup
protected function setUp(): void
{
    parent::setUp();
    
    // Add custom schema fixture
    $customSchema = [
        'openapi' => '3.0.0',
        'info' => ['title' => 'Custom API', 'version' => '1.0.0'],
        'paths' => [
            '/custom' => [
                'get' => [
                    'responses' => [
                        '200' => ['description' => 'Success']
                    ]
                ]
            ]
        ]
    ];
    
    $this->fixtureManager->addSchema('custom', $customSchema);
}
```

## Performance Benchmarking

### Benchmark Individual Operations

```php
public function test_parsing_performance(): void
{
    $schema = $this->fixtureManager->getSchema('large');
    
    // Benchmark parsing
    $this->startBenchmark('large_schema_parsing');
    $result = $this->parser->parse($schema);
    $parseResult = $this->endBenchmark('large_schema_parsing');
    
    // Performance assertions
    $this->assertLessThan(5.0, $parseResult['execution_time'], 
        'Large schema parsing should complete within 5 seconds');
    
    $this->assertLessThan(100 * 1024 * 1024, $parseResult['memory_usage'], 
        'Memory usage should be under 100MB');
}
```

### Compare Performance Between Operations

```php
public function test_performance_comparison(): void
{
    $smallSchema = $this->fixtureManager->getSchema('petstore');
    $largeSchema = $this->fixtureManager->getSchema('large');
    
    // Benchmark small schema
    $this->startBenchmark('small_schema');
    $this->parser->parse($smallSchema);
    $this->endBenchmark('small_schema');
    
    // Benchmark large schema
    $this->startBenchmark('large_schema');
    $this->parser->parse($largeSchema);
    $this->endBenchmark('large_schema');
    
    // Compare performance
    $comparison = $this->benchmark->compare('small_schema', 'large_schema');
    
    $this->assertGreaterThan(1.0, $comparison['time_ratio'], 
        'Large schema should take more time than small schema');
}
```

## Mock Data Generation

### Generate Test Data from Schema

```php
public function test_with_generated_data(): void
{
    $schema = $this->fixtureManager->getSchema('petstore');
    $petSchema = $schema['components']['schemas']['Pet'];
    
    // Generate valid test data
    $validPet = $this->mockDataGenerator->generateFromSchema($petSchema);
    
    // Test with generated data
    $validator = $this->validationHelper->createValidator($petSchema);
    $this->assertTrue($validator->passes($validPet));
    
    // Generate invalid data for negative testing
    $invalidPet = $this->mockDataGenerator->generateInvalidData($petSchema);
    $this->assertFalse($validator->passes($invalidPet));
}
```

### Generate Edge Case Data

```php
public function test_edge_case_handling(): void
{
    $schema = $this->fixtureManager->getSchema('petstore');
    $petSchema = $schema['components']['schemas']['Pet'];
    
    // Generate edge case data
    $edgeCases = $this->mockDataGenerator->generateEdgeCaseData($petSchema);
    
    foreach ($edgeCases as $caseName => $data) {
        try {
            $result = $this->parser->validateData($data, $petSchema);
            // Handle each edge case appropriately
        } catch (\Exception $e) {
            $this->assertNotEmpty($e->getMessage(), 
                "Edge case '{$caseName}' should provide meaningful error");
        }
    }
}
```

## Configuration

### Test Configuration

The testing framework can be configured via environment variables:

```bash
# Enable performance benchmarking
OPENAPI_TEST_BENCHMARKING=true

# Set performance thresholds
OPENAPI_TEST_MAX_PARSE_TIME=5.0
OPENAPI_TEST_MAX_MEMORY_MB=100

# Enable detailed logging
OPENAPI_TEST_VERBOSE=true

# Configure mock server
OPENAPI_TEST_MOCK_SERVER_PORT=8080
```

### PHPUnit Configuration

```xml
<!-- phpunit.xml -->
<phpunit>
    <testsuites>
        <testsuite name="OpenAPI Unit Tests">
            <directory>tests/Unit</directory>
        </testsuite>
        <testsuite name="OpenAPI Integration Tests">
            <directory>tests/Integration</directory>
        </testsuite>
        <testsuite name="OpenAPI Performance Tests">
            <directory>tests/Performance</directory>
        </testsuite>
        <testsuite name="OpenAPI Compatibility Tests">
            <directory>tests/Compatibility</directory>
        </testsuite>
        <testsuite name="OpenAPI Edge Case Tests">
            <directory>tests/EdgeCases</directory>
        </testsuite>
    </testsuites>
    
    <php>
        <env name="OPENAPI_TEST_BENCHMARKING" value="true"/>
        <env name="OPENAPI_TEST_MAX_PARSE_TIME" value="5.0"/>
        <env name="OPENAPI_TEST_MAX_MEMORY_MB" value="100"/>
    </php>
</phpunit>
```

## Best Practices

### Test Organization

1. **Use descriptive test names** that clearly indicate what is being tested
2. **Group related tests** in the same test class
3. **Use appropriate test categories** (Unit, Integration, Performance, etc.)
4. **Include performance benchmarking** for operations that might be slow

### Performance Testing

1. **Set realistic performance thresholds** based on expected usage
2. **Test with various data sizes** (small, medium, large schemas)
3. **Monitor memory usage** as well as execution time
4. **Use benchmarking consistently** across all performance-sensitive tests

### Error Testing

1. **Test both expected and unexpected errors**
2. **Verify error messages are meaningful**
3. **Test error recovery and graceful degradation**
4. **Include edge cases and boundary conditions**

### Mock Data

1. **Use realistic test data** that matches real-world scenarios
2. **Test with both valid and invalid data**
3. **Include edge cases and boundary values**
4. **Generate data programmatically** when possible for consistency

This comprehensive testing framework ensures robust validation of OpenAPI integration functionality across all scenarios and use cases.
