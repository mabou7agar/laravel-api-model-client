<?php

namespace MTechStack\LaravelApiModelClient\Tests\Unit\Configuration;

use MTechStack\LaravelApiModelClient\Tests\OpenApiTestCase;
use MTechStack\LaravelApiModelClient\Configuration\ValidationStrictnessManager;
use MTechStack\LaravelApiModelClient\Tests\Utilities\ParameterValidationHelper;
use Illuminate\Validation\ValidationException;

/**
 * Unit tests for parameter validation with different strictness levels
 */
class ParameterValidationTest extends OpenApiTestCase
{
    protected ValidationStrictnessManager $strictnessManager;
    protected ParameterValidationHelper $parameterValidationHelper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->strictnessManager = new ValidationStrictnessManager('testing');
        $this->parameterValidationHelper = new ParameterValidationHelper();
    }

    /**
     * Test strict validation mode
     */
    public function test_strict_validation_enforces_all_rules(): void
    {
        $this->strictnessManager->setStrictnessLevel('strict');
        
        $schema = $this->createTestSchema();
        $petSchema = $schema['components']['schemas']['Pet'];
        $rules = $this->parameterValidationHelper->generateLaravelRules($petSchema);

        // Valid data should pass
        $validData = [
            'name' => 'Fluffy',
            'status' => 'available'
        ];

        $this->startBenchmark('strict_validation_valid');
        $result = $this->strictnessManager->validateParameters($validData, $rules);
        $this->endBenchmark('strict_validation_valid');

        $this->assertTrue($result['valid']);
        $this->assertEquals('strict', $result['strictness']);
        $this->assertEmpty($result['errors']);

        // Invalid data should fail
        $invalidData = [
            'name' => '', // Empty required field
            'status' => 'invalid_status' // Not in enum
        ];

        $this->expectException(ValidationException::class);
        $this->strictnessManager->validateParameters($invalidData, $rules);
    }

    /**
     * Test moderate validation mode
     */
    public function test_moderate_validation_allows_flexibility(): void
    {
        $this->strictnessManager->setStrictnessLevel('moderate');
        
        $schema = $this->createTestSchema();
        $petSchema = $schema['components']['schemas']['Pet'];
        $rules = $this->parameterValidationHelper->generateLaravelRules($petSchema);

        // Data with minor issues should pass with warnings
        $dataWithMinorIssues = [
            'name' => 'Fluffy',
            'status' => 'available',
            'unknown_field' => 'should_warn' // Unknown field
        ];

        $this->startBenchmark('moderate_validation');
        $result = $this->strictnessManager->validateParameters($dataWithMinorIssues, $rules);
        $this->endBenchmark('moderate_validation');

        $this->assertTrue($result['valid']);
        $this->assertEquals('moderate', $result['strictness']);
        $this->assertNotEmpty($result['warnings']);
    }

    /**
     * Test lenient validation mode
     */
    public function test_lenient_validation_minimal_enforcement(): void
    {
        $this->strictnessManager->setStrictnessLevel('lenient');
        
        $schema = $this->createTestSchema();
        $petSchema = $schema['components']['schemas']['Pet'];
        $rules = $this->parameterValidationHelper->generateLaravelRules($petSchema);

        // Data with many issues should still pass with warnings
        $problematicData = [
            'name' => '', // Empty required field
            'status' => 'invalid_status', // Invalid enum
            'unknown_field' => 'value',
            'another_unknown' => 123
        ];

        $this->startBenchmark('lenient_validation');
        $result = $this->strictnessManager->validateParameters($problematicData, $rules);
        $this->endBenchmark('lenient_validation');

        $this->assertTrue($result['valid']);
        $this->assertEquals('lenient', $result['strictness']);
        $this->assertNotEmpty($result['warnings']);
    }

    /**
     * Test auto-casting functionality
     */
    public function test_auto_casting_converts_types_correctly(): void
    {
        $this->strictnessManager->setStrictnessLevel('strict');
        
        $rules = [
            'id' => ['integer'],
            'price' => ['numeric'],
            'active' => ['boolean'],
            'tags' => ['array']
        ];

        $dataWithStringTypes = [
            'id' => '123',
            'price' => '99.99',
            'active' => 'true',
            'tags' => '["tag1", "tag2"]'
        ];

        $result = $this->strictnessManager->validateParameters($dataWithStringTypes, $rules);

        $this->assertTrue($result['valid']);
        $this->assertIsInt($result['data']['id']);
        $this->assertIsFloat($result['data']['price']);
        $this->assertIsBool($result['data']['active']);
        $this->assertIsArray($result['data']['tags']);
    }

    /**
     * Test comprehensive validation test cases
     */
    public function test_comprehensive_validation_scenarios(): void
    {
        $schema = $this->createTestSchema();
        $petSchema = $schema['components']['schemas']['Pet'];
        
        $testCases = $this->parameterValidationHelper->createValidationTestCases($petSchema);
        $rules = $this->parameterValidationHelper->generateLaravelRules($petSchema);

        $results = [];
        $totalTests = 0;
        $passedTests = 0;

        foreach ($testCases as $category => $cases) {
            foreach ($cases as $caseName => $testCase) {
                $totalTests++;
                
                $this->startBenchmark("validation_test_{$caseName}");
                $result = $this->parameterValidationHelper->runValidationTestCase($testCase, $rules);
                $benchmarkResult = $this->endBenchmark("validation_test_{$caseName}");
                
                $result['validation_time'] = $benchmarkResult['execution_time'];
                $results["{$category}_{$caseName}"] = $result;
                
                if ($result['test_passed']) {
                    $passedTests++;
                }
            }
        }

        $statistics = $this->parameterValidationHelper->getValidationStatistics($results);
        
        $this->assertGreaterThan(0, $totalTests);
        $this->assertGreaterThanOrEqual(0.8, $statistics['success_rate'] / 100, 
            'At least 80% of validation tests should pass');
        
        // Performance assertion
        $this->assertLessThan(0.01, $statistics['average_validation_time'], 
            'Average validation time should be less than 10ms');
    }

    /**
     * Test boundary value validation
     */
    public function test_boundary_value_validation(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'count' => [
                    'type' => 'integer',
                    'minimum' => 1,
                    'maximum' => 100
                ],
                'name' => [
                    'type' => 'string',
                    'minLength' => 2,
                    'maxLength' => 50
                ]
            ],
            'required' => ['count', 'name']
        ];

        $rules = $this->parameterValidationHelper->generateLaravelRules($schema);

        // Test minimum boundary
        $minBoundaryData = ['count' => 1, 'name' => 'AB'];
        $result = $this->strictnessManager->validateParameters($minBoundaryData, $rules);
        $this->assertTrue($result['valid']);

        // Test maximum boundary
        $maxBoundaryData = ['count' => 100, 'name' => str_repeat('X', 50)];
        $result = $this->strictnessManager->validateParameters($maxBoundaryData, $rules);
        $this->assertTrue($result['valid']);

        // Test below minimum (should fail)
        $belowMinData = ['count' => 0, 'name' => 'A'];
        $this->expectException(ValidationException::class);
        $this->strictnessManager->validateParameters($belowMinData, $rules);
    }

    /**
     * Test format validation
     */
    public function test_format_validation(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'email' => [
                    'type' => 'string',
                    'format' => 'email'
                ],
                'website' => [
                    'type' => 'string',
                    'format' => 'uri'
                ],
                'created_at' => [
                    'type' => 'string',
                    'format' => 'date-time'
                ]
            ],
            'required' => ['email']
        ];

        $rules = $this->parameterValidationHelper->generateLaravelRules($schema);

        // Valid formats
        $validData = [
            'email' => 'test@example.com',
            'website' => 'https://example.com',
            'created_at' => '2023-12-25T10:30:00Z'
        ];

        $result = $this->strictnessManager->validateParameters($validData, $rules);
        $this->assertTrue($result['valid']);

        // Invalid email format
        $invalidEmailData = ['email' => 'invalid-email'];
        $this->expectException(ValidationException::class);
        $this->strictnessManager->validateParameters($invalidEmailData, $rules);
    }

    /**
     * Test enum validation
     */
    public function test_enum_validation(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'status' => [
                    'type' => 'string',
                    'enum' => ['active', 'inactive', 'pending']
                ],
                'priority' => [
                    'type' => 'integer',
                    'enum' => [1, 2, 3, 4, 5]
                ]
            ],
            'required' => ['status']
        ];

        $rules = $this->parameterValidationHelper->generateLaravelRules($schema);

        // Valid enum values
        $validData = ['status' => 'active', 'priority' => 3];
        $result = $this->strictnessManager->validateParameters($validData, $rules);
        $this->assertTrue($result['valid']);

        // Invalid enum value
        $invalidData = ['status' => 'invalid_status'];
        $this->expectException(ValidationException::class);
        $this->strictnessManager->validateParameters($invalidData, $rules);
    }

    /**
     * Test nested object validation
     */
    public function test_nested_object_validation(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'user' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => ['type' => 'string'],
                        'email' => ['type' => 'string', 'format' => 'email']
                    ],
                    'required' => ['name', 'email']
                ]
            ],
            'required' => ['user']
        ];

        $rules = [
            'user' => ['required', 'array'],
            'user.name' => ['required', 'string'],
            'user.email' => ['required', 'string', 'email']
        ];

        // Valid nested data
        $validData = [
            'user' => [
                'name' => 'John Doe',
                'email' => 'john@example.com'
            ]
        ];

        $result = $this->strictnessManager->validateParameters($validData, $rules);
        $this->assertTrue($result['valid']);

        // Invalid nested data
        $invalidData = [
            'user' => [
                'name' => 'John Doe',
                'email' => 'invalid-email'
            ]
        ];

        $this->expectException(ValidationException::class);
        $this->strictnessManager->validateParameters($invalidData, $rules);
    }

    /**
     * Test array validation
     */
    public function test_array_validation(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'tags' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'minItems' => 1,
                    'maxItems' => 5
                ]
            ],
            'required' => ['tags']
        ];

        $rules = [
            'tags' => ['required', 'array', 'min:1', 'max:5'],
            'tags.*' => ['string']
        ];

        // Valid array
        $validData = ['tags' => ['tag1', 'tag2', 'tag3']];
        $result = $this->strictnessManager->validateParameters($validData, $rules);
        $this->assertTrue($result['valid']);

        // Array with too many items
        $tooManyItemsData = ['tags' => ['tag1', 'tag2', 'tag3', 'tag4', 'tag5', 'tag6']];
        $this->expectException(ValidationException::class);
        $this->strictnessManager->validateParameters($tooManyItemsData, $rules);
    }

    /**
     * Test validation performance with large datasets
     */
    public function test_validation_performance_with_large_datasets(): void
    {
        $schema = $this->createTestSchema();
        $petSchema = $schema['components']['schemas']['Pet'];
        $rules = $this->parameterValidationHelper->generateLaravelRules($petSchema);

        // Generate large dataset
        $largeDataset = [];
        for ($i = 0; $i < 1000; $i++) {
            $largeDataset[] = [
                'name' => "Pet {$i}",
                'status' => 'available'
            ];
        }

        $this->startBenchmark('large_dataset_validation');
        
        foreach ($largeDataset as $data) {
            $result = $this->strictnessManager->validateParameters($data, $rules);
            $this->assertTrue($result['valid']);
        }
        
        $benchmarkResult = $this->endBenchmark('large_dataset_validation');

        // Should validate 1000 items in reasonable time
        $this->assertLessThan(5.0, $benchmarkResult['execution_time'], 
            'Should validate 1000 items in less than 5 seconds');
        
        // Memory usage should be reasonable
        $this->assertLessThan(50 * 1024 * 1024, $benchmarkResult['memory_usage'], 
            'Memory usage should be less than 50MB');
    }

    /**
     * Test validation with different strictness levels on same data
     */
    public function test_strictness_level_comparison(): void
    {
        $rules = [
            'name' => ['required', 'string', 'min:2'],
            'email' => ['required', 'email'],
            'age' => ['integer', 'min:18']
        ];

        $problematicData = [
            'name' => 'A', // Too short
            'email' => 'invalid-email', // Invalid format
            'age' => 16, // Below minimum
            'unknown_field' => 'value' // Unknown field
        ];

        $results = $this->parameterValidationHelper->testValidationStrictness($problematicData, $rules);

        // Strict should fail
        $this->assertFalse($results['strict']['passed']);
        
        // Moderate might pass with warnings
        $this->assertTrue(isset($results['moderate']));
        
        // Lenient should pass with warnings
        $this->assertTrue($results['lenient']['passed']);
        $this->assertNotEmpty($results['lenient']['result']['warnings']);
    }

    /**
     * Test custom validation messages
     */
    public function test_custom_validation_messages(): void
    {
        $rules = [
            'name' => ['required', 'string', 'min:2'],
            'email' => ['required', 'email']
        ];

        $messages = [
            'name.required' => 'Pet name is mandatory',
            'name.min' => 'Pet name must be at least 2 characters',
            'email.email' => 'Please provide a valid email address'
        ];

        $invalidData = [
            'name' => '',
            'email' => 'invalid-email'
        ];

        try {
            $this->strictnessManager->validateParameters($invalidData, $rules, $messages);
            $this->fail('Should have thrown validation exception');
        } catch (ValidationException $e) {
            $errors = $e->errors();
            $this->assertStringContainsString('Pet name is mandatory', $errors['name'][0]);
            $this->assertStringContainsString('valid email address', $errors['email'][0]);
        }
    }
}
