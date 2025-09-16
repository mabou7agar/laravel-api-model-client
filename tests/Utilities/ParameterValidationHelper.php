<?php

namespace MTechStack\LaravelApiModelClient\Tests\Utilities;

use Illuminate\Support\Facades\Validator;
use MTechStack\LaravelApiModelClient\Configuration\ValidationStrictnessManager;

/**
 * Helper for testing parameter validation in OpenAPI integration
 */
class ParameterValidationHelper
{
    protected ValidationStrictnessManager $strictnessManager;
    protected MockDataGenerator $dataGenerator;

    public function __construct()
    {
        $this->strictnessManager = new ValidationStrictnessManager();
        $this->dataGenerator = new MockDataGenerator();
    }

    /**
     * Create validation test cases for a schema
     */
    public function createValidationTestCases(array $schema): array
    {
        return [
            'valid_data' => $this->createValidTestCases($schema),
            'invalid_data' => $this->createInvalidTestCases($schema),
            'edge_cases' => $this->createEdgeCaseTestCases($schema),
            'boundary_cases' => $this->createBoundaryTestCases($schema),
            'type_mismatch' => $this->createTypeMismatchTestCases($schema),
            'missing_required' => $this->createMissingRequiredTestCases($schema),
            'format_validation' => $this->createFormatValidationTestCases($schema),
            'enum_validation' => $this->createEnumValidationTestCases($schema)
        ];
    }

    /**
     * Create valid test cases
     */
    protected function createValidTestCases(array $schema): array
    {
        $cases = [];
        
        // Generate multiple valid variations
        for ($i = 0; $i < 5; $i++) {
            $cases["valid_case_{$i}"] = [
                'data' => $this->dataGenerator->generate($schema),
                'should_pass' => true,
                'description' => "Valid data case {$i}"
            ];
        }

        return $cases;
    }

    /**
     * Create invalid test cases
     */
    protected function createInvalidTestCases(array $schema): array
    {
        $cases = [];
        
        // Generate multiple invalid variations
        for ($i = 0; $i < 3; $i++) {
            $cases["invalid_case_{$i}"] = [
                'data' => $this->dataGenerator->generateInvalidData($schema),
                'should_pass' => false,
                'description' => "Invalid data case {$i}"
            ];
        }

        return $cases;
    }

    /**
     * Create edge case test scenarios
     */
    protected function createEdgeCaseTestCases(array $schema): array
    {
        $edgeCases = $this->dataGenerator->generateEdgeCaseData($schema);
        $cases = [];

        foreach ($edgeCases as $caseName => $data) {
            $cases[$caseName] = [
                'data' => $data,
                'should_pass' => $caseName === 'empty_object' ? false : null, // Depends on schema
                'description' => "Edge case: {$caseName}"
            ];
        }

        return $cases;
    }

    /**
     * Create boundary test cases
     */
    protected function createBoundaryTestCases(array $schema): array
    {
        $cases = [];
        $properties = $schema['properties'] ?? [];

        foreach ($properties as $propertyName => $propertySchema) {
            $type = $propertySchema['type'] ?? 'string';

            switch ($type) {
                case 'string':
                    if (isset($propertySchema['maxLength'])) {
                        $cases["max_length_{$propertyName}"] = [
                            'data' => [$propertyName => str_repeat('x', $propertySchema['maxLength'])],
                            'should_pass' => true,
                            'description' => "Maximum length for {$propertyName}"
                        ];

                        $cases["exceed_max_length_{$propertyName}"] = [
                            'data' => [$propertyName => str_repeat('x', $propertySchema['maxLength'] + 1)],
                            'should_pass' => false,
                            'description' => "Exceeds maximum length for {$propertyName}"
                        ];
                    }

                    if (isset($propertySchema['minLength'])) {
                        $cases["min_length_{$propertyName}"] = [
                            'data' => [$propertyName => str_repeat('x', $propertySchema['minLength'])],
                            'should_pass' => true,
                            'description' => "Minimum length for {$propertyName}"
                        ];

                        if ($propertySchema['minLength'] > 0) {
                            $cases["below_min_length_{$propertyName}"] = [
                                'data' => [$propertyName => str_repeat('x', $propertySchema['minLength'] - 1)],
                                'should_pass' => false,
                                'description' => "Below minimum length for {$propertyName}"
                            ];
                        }
                    }
                    break;

                case 'integer':
                case 'number':
                    if (isset($propertySchema['maximum'])) {
                        $cases["max_value_{$propertyName}"] = [
                            'data' => [$propertyName => $propertySchema['maximum']],
                            'should_pass' => true,
                            'description' => "Maximum value for {$propertyName}"
                        ];

                        $cases["exceed_max_value_{$propertyName}"] = [
                            'data' => [$propertyName => $propertySchema['maximum'] + 1],
                            'should_pass' => false,
                            'description' => "Exceeds maximum value for {$propertyName}"
                        ];
                    }

                    if (isset($propertySchema['minimum'])) {
                        $cases["min_value_{$propertyName}"] = [
                            'data' => [$propertyName => $propertySchema['minimum']],
                            'should_pass' => true,
                            'description' => "Minimum value for {$propertyName}"
                        ];

                        $cases["below_min_value_{$propertyName}"] = [
                            'data' => [$propertyName => $propertySchema['minimum'] - 1],
                            'should_pass' => false,
                            'description' => "Below minimum value for {$propertyName}"
                        ];
                    }
                    break;

                case 'array':
                    if (isset($propertySchema['maxItems'])) {
                        $cases["max_items_{$propertyName}"] = [
                            'data' => [$propertyName => array_fill(0, $propertySchema['maxItems'], 'item')],
                            'should_pass' => true,
                            'description' => "Maximum items for {$propertyName}"
                        ];

                        $cases["exceed_max_items_{$propertyName}"] = [
                            'data' => [$propertyName => array_fill(0, $propertySchema['maxItems'] + 1, 'item')],
                            'should_pass' => false,
                            'description' => "Exceeds maximum items for {$propertyName}"
                        ];
                    }

                    if (isset($propertySchema['minItems'])) {
                        $cases["min_items_{$propertyName}"] = [
                            'data' => [$propertyName => array_fill(0, $propertySchema['minItems'], 'item')],
                            'should_pass' => true,
                            'description' => "Minimum items for {$propertyName}"
                        ];

                        if ($propertySchema['minItems'] > 0) {
                            $cases["below_min_items_{$propertyName}"] = [
                                'data' => [$propertyName => array_fill(0, $propertySchema['minItems'] - 1, 'item')],
                                'should_pass' => false,
                                'description' => "Below minimum items for {$propertyName}"
                            ];
                        }
                    }
                    break;
            }
        }

        return $cases;
    }

    /**
     * Create type mismatch test cases
     */
    protected function createTypeMismatchTestCases(array $schema): array
    {
        $cases = [];
        $properties = $schema['properties'] ?? [];

        foreach ($properties as $propertyName => $propertySchema) {
            $expectedType = $propertySchema['type'] ?? 'string';

            $wrongTypes = [
                'string' => [123, true, [], null],
                'integer' => ['not_a_number', true, [], null],
                'number' => ['not_a_number', true, [], null],
                'boolean' => ['not_a_boolean', 123, [], null],
                'array' => ['not_an_array', 123, true, null],
                'object' => ['not_an_object', 123, true, null]
            ];

            if (isset($wrongTypes[$expectedType])) {
                foreach ($wrongTypes[$expectedType] as $index => $wrongValue) {
                    $cases["type_mismatch_{$propertyName}_{$index}"] = [
                        'data' => [$propertyName => $wrongValue],
                        'should_pass' => false,
                        'description' => "Type mismatch for {$propertyName}: expected {$expectedType}, got " . gettype($wrongValue)
                    ];
                }
            }
        }

        return $cases;
    }

    /**
     * Create missing required field test cases
     */
    protected function createMissingRequiredTestCases(array $schema): array
    {
        $cases = [];
        $required = $schema['required'] ?? [];

        foreach ($required as $requiredField) {
            $validData = $this->dataGenerator->generate($schema);
            unset($validData[$requiredField]);

            $cases["missing_required_{$requiredField}"] = [
                'data' => $validData,
                'should_pass' => false,
                'description' => "Missing required field: {$requiredField}"
            ];
        }

        return $cases;
    }

    /**
     * Create format validation test cases
     */
    protected function createFormatValidationTestCases(array $schema): array
    {
        $cases = [];
        $properties = $schema['properties'] ?? [];

        foreach ($properties as $propertyName => $propertySchema) {
            $format = $propertySchema['format'] ?? null;
            
            if (!$format) {
                continue;
            }

            switch ($format) {
                case 'email':
                    $cases["valid_email_{$propertyName}"] = [
                        'data' => [$propertyName => 'test@example.com'],
                        'should_pass' => true,
                        'description' => "Valid email format for {$propertyName}"
                    ];

                    $cases["invalid_email_{$propertyName}"] = [
                        'data' => [$propertyName => 'invalid-email'],
                        'should_pass' => false,
                        'description' => "Invalid email format for {$propertyName}"
                    ];
                    break;

                case 'uri':
                case 'url':
                    $cases["valid_url_{$propertyName}"] = [
                        'data' => [$propertyName => 'https://example.com'],
                        'should_pass' => true,
                        'description' => "Valid URL format for {$propertyName}"
                    ];

                    $cases["invalid_url_{$propertyName}"] = [
                        'data' => [$propertyName => 'not-a-url'],
                        'should_pass' => false,
                        'description' => "Invalid URL format for {$propertyName}"
                    ];
                    break;

                case 'uuid':
                    $cases["valid_uuid_{$propertyName}"] = [
                        'data' => [$propertyName => '550e8400-e29b-41d4-a716-446655440000'],
                        'should_pass' => true,
                        'description' => "Valid UUID format for {$propertyName}"
                    ];

                    $cases["invalid_uuid_{$propertyName}"] = [
                        'data' => [$propertyName => 'not-a-uuid'],
                        'should_pass' => false,
                        'description' => "Invalid UUID format for {$propertyName}"
                    ];
                    break;

                case 'date':
                    $cases["valid_date_{$propertyName}"] = [
                        'data' => [$propertyName => '2023-12-25'],
                        'should_pass' => true,
                        'description' => "Valid date format for {$propertyName}"
                    ];

                    $cases["invalid_date_{$propertyName}"] = [
                        'data' => [$propertyName => 'not-a-date'],
                        'should_pass' => false,
                        'description' => "Invalid date format for {$propertyName}"
                    ];
                    break;

                case 'date-time':
                    $cases["valid_datetime_{$propertyName}"] = [
                        'data' => [$propertyName => '2023-12-25T10:30:00Z'],
                        'should_pass' => true,
                        'description' => "Valid datetime format for {$propertyName}"
                    ];

                    $cases["invalid_datetime_{$propertyName}"] = [
                        'data' => [$propertyName => 'not-a-datetime'],
                        'should_pass' => false,
                        'description' => "Invalid datetime format for {$propertyName}"
                    ];
                    break;
            }
        }

        return $cases;
    }

    /**
     * Create enum validation test cases
     */
    protected function createEnumValidationTestCases(array $schema): array
    {
        $cases = [];
        $properties = $schema['properties'] ?? [];

        foreach ($properties as $propertyName => $propertySchema) {
            $enum = $propertySchema['enum'] ?? null;
            
            if (!$enum) {
                continue;
            }

            // Valid enum values
            foreach ($enum as $index => $validValue) {
                $cases["valid_enum_{$propertyName}_{$index}"] = [
                    'data' => [$propertyName => $validValue],
                    'should_pass' => true,
                    'description' => "Valid enum value '{$validValue}' for {$propertyName}"
                ];
            }

            // Invalid enum values
            $invalidValues = ['invalid_enum_value', 'not_in_enum', 'wrong_value'];
            foreach ($invalidValues as $index => $invalidValue) {
                $cases["invalid_enum_{$propertyName}_{$index}"] = [
                    'data' => [$propertyName => $invalidValue],
                    'should_pass' => false,
                    'description' => "Invalid enum value '{$invalidValue}' for {$propertyName}"
                ];
            }
        }

        return $cases;
    }

    /**
     * Test validation with different strictness levels
     */
    public function testValidationStrictness(array $data, array $rules): array
    {
        $results = [];
        $strictnessLevels = ['strict', 'moderate', 'lenient'];

        foreach ($strictnessLevels as $level) {
            $this->strictnessManager->setStrictnessLevel($level);
            
            try {
                $result = $this->strictnessManager->validateParameters($data, $rules);
                $results[$level] = [
                    'passed' => true,
                    'result' => $result,
                    'error' => null
                ];
            } catch (\Exception $e) {
                $results[$level] = [
                    'passed' => false,
                    'result' => null,
                    'error' => $e->getMessage()
                ];
            }
        }

        return $results;
    }

    /**
     * Generate Laravel validation rules from OpenAPI schema
     */
    public function generateLaravelRules(array $schema): array
    {
        $rules = [];
        $properties = $schema['properties'] ?? [];
        $required = $schema['required'] ?? [];

        foreach ($properties as $propertyName => $propertySchema) {
            $fieldRules = [];

            // Required validation
            if (in_array($propertyName, $required)) {
                $fieldRules[] = 'required';
            } else {
                $fieldRules[] = 'nullable';
            }

            // Type validation
            $type = $propertySchema['type'] ?? 'string';
            switch ($type) {
                case 'string':
                    $fieldRules[] = 'string';
                    if (isset($propertySchema['maxLength'])) {
                        $fieldRules[] = "max:{$propertySchema['maxLength']}";
                    }
                    if (isset($propertySchema['minLength'])) {
                        $fieldRules[] = "min:{$propertySchema['minLength']}";
                    }
                    break;

                case 'integer':
                    $fieldRules[] = 'integer';
                    if (isset($propertySchema['maximum'])) {
                        $fieldRules[] = "max:{$propertySchema['maximum']}";
                    }
                    if (isset($propertySchema['minimum'])) {
                        $fieldRules[] = "min:{$propertySchema['minimum']}";
                    }
                    break;

                case 'number':
                    $fieldRules[] = 'numeric';
                    if (isset($propertySchema['maximum'])) {
                        $fieldRules[] = "max:{$propertySchema['maximum']}";
                    }
                    if (isset($propertySchema['minimum'])) {
                        $fieldRules[] = "min:{$propertySchema['minimum']}";
                    }
                    break;

                case 'boolean':
                    $fieldRules[] = 'boolean';
                    break;

                case 'array':
                    $fieldRules[] = 'array';
                    if (isset($propertySchema['maxItems'])) {
                        $fieldRules[] = "max:{$propertySchema['maxItems']}";
                    }
                    if (isset($propertySchema['minItems'])) {
                        $fieldRules[] = "min:{$propertySchema['minItems']}";
                    }
                    break;
            }

            // Format validation
            $format = $propertySchema['format'] ?? null;
            if ($format) {
                switch ($format) {
                    case 'email':
                        $fieldRules[] = 'email';
                        break;
                    case 'url':
                    case 'uri':
                        $fieldRules[] = 'url';
                        break;
                    case 'uuid':
                        $fieldRules[] = 'uuid';
                        break;
                    case 'date':
                        $fieldRules[] = 'date';
                        break;
                    case 'date-time':
                        $fieldRules[] = 'date';
                        break;
                }
            }

            // Enum validation
            if (isset($propertySchema['enum'])) {
                $enumValues = implode(',', $propertySchema['enum']);
                $fieldRules[] = "in:{$enumValues}";
            }

            $rules[$propertyName] = $fieldRules;
        }

        return $rules;
    }

    /**
     * Run validation test case
     */
    public function runValidationTestCase(array $testCase, array $rules): array
    {
        $data = $testCase['data'];
        $shouldPass = $testCase['should_pass'];
        $description = $testCase['description'];

        try {
            $validator = Validator::make($data, $rules);
            
            if ($validator->fails()) {
                $passed = false;
                $errors = $validator->errors()->toArray();
            } else {
                $passed = true;
                $errors = [];
            }

            $result = [
                'description' => $description,
                'data' => $data,
                'expected_to_pass' => $shouldPass,
                'actually_passed' => $passed,
                'test_passed' => ($shouldPass === $passed) || ($shouldPass === null),
                'errors' => $errors,
                'validation_time' => 0 // Will be measured in actual test
            ];

        } catch (\Exception $e) {
            $result = [
                'description' => $description,
                'data' => $data,
                'expected_to_pass' => $shouldPass,
                'actually_passed' => false,
                'test_passed' => $shouldPass === false,
                'errors' => [$e->getMessage()],
                'exception' => get_class($e),
                'validation_time' => 0
            ];
        }

        return $result;
    }

    /**
     * Get validation statistics from test results
     */
    public function getValidationStatistics(array $testResults): array
    {
        $total = count($testResults);
        $passed = 0;
        $failed = 0;
        $totalTime = 0;

        foreach ($testResults as $result) {
            if ($result['test_passed']) {
                $passed++;
            } else {
                $failed++;
            }
            $totalTime += $result['validation_time'] ?? 0;
        }

        return [
            'total_tests' => $total,
            'passed' => $passed,
            'failed' => $failed,
            'success_rate' => $total > 0 ? ($passed / $total) * 100 : 0,
            'total_validation_time' => $totalTime,
            'average_validation_time' => $total > 0 ? $totalTime / $total : 0
        ];
    }
}
