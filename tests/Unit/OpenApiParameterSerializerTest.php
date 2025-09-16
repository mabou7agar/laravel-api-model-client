<?php

namespace MTechStack\LaravelApiModelClient\Tests\Unit;

use Carbon\Carbon;
use MTechStack\LaravelApiModelClient\OpenApi\OpenApiParameterSerializer;
use MTechStack\LaravelApiModelClient\Tests\TestCase;

class OpenApiParameterSerializerTest extends TestCase
{
    /** @test */
    public function it_can_convert_integer_types()
    {
        $this->assertEquals(42, OpenApiParameterSerializer::convertType('42', 'integer'));
        $this->assertEquals(0, OpenApiParameterSerializer::convertType('0', 'integer'));
        $this->assertEquals(-10, OpenApiParameterSerializer::convertType('-10', 'integer'));
        $this->assertNull(OpenApiParameterSerializer::convertType(null, 'integer'));
        $this->assertNull(OpenApiParameterSerializer::convertType('', 'integer'));
    }

    /** @test */
    public function it_throws_exception_for_invalid_integer()
    {
        $this->expectException(\InvalidArgumentException::class);
        OpenApiParameterSerializer::convertType('not-a-number', 'integer');
    }

    /** @test */
    public function it_can_convert_number_types()
    {
        $this->assertEquals(42.5, OpenApiParameterSerializer::convertType('42.5', 'number'));
        $this->assertEquals(0.0, OpenApiParameterSerializer::convertType('0', 'number'));
        $this->assertEquals(-10.25, OpenApiParameterSerializer::convertType('-10.25', 'number'));
        $this->assertNull(OpenApiParameterSerializer::convertType(null, 'number'));
    }

    /** @test */
    public function it_can_convert_boolean_types()
    {
        // True values
        $this->assertTrue(OpenApiParameterSerializer::convertType('true', 'boolean'));
        $this->assertTrue(OpenApiParameterSerializer::convertType('1', 'boolean'));
        $this->assertTrue(OpenApiParameterSerializer::convertType('yes', 'boolean'));
        $this->assertTrue(OpenApiParameterSerializer::convertType('on', 'boolean'));
        $this->assertTrue(OpenApiParameterSerializer::convertType(1, 'boolean'));

        // False values
        $this->assertFalse(OpenApiParameterSerializer::convertType('false', 'boolean'));
        $this->assertFalse(OpenApiParameterSerializer::convertType('0', 'boolean'));
        $this->assertFalse(OpenApiParameterSerializer::convertType('no', 'boolean'));
        $this->assertFalse(OpenApiParameterSerializer::convertType('off', 'boolean'));
        $this->assertFalse(OpenApiParameterSerializer::convertType(0, 'boolean'));

        // Null
        $this->assertNull(OpenApiParameterSerializer::convertType(null, 'boolean'));
    }

    /** @test */
    public function it_can_convert_string_types_with_formats()
    {
        // Date format
        $dateString = OpenApiParameterSerializer::convertType('2023-12-25', 'string', 'date');
        $this->assertEquals('2023-12-25', $dateString);

        // Date-time format
        $carbon = Carbon::parse('2023-12-25 15:30:00');
        $dateTimeString = OpenApiParameterSerializer::convertType($carbon, 'string', 'date-time');
        $this->assertStringContainsString('2023-12-25', $dateTimeString);

        // Email format
        $email = OpenApiParameterSerializer::convertType('  TEST@EXAMPLE.COM  ', 'string', 'email');
        $this->assertEquals('test@example.com', $email);

        // UUID format
        $uuid = OpenApiParameterSerializer::convertType('550E8400-E29B-41D4-A716-446655440000', 'string', 'uuid');
        $this->assertEquals('550e8400-e29b-41d4-a716-446655440000', $uuid);
    }

    /** @test */
    public function it_can_convert_array_types()
    {
        // From array
        $this->assertEquals([1, 2, 3], OpenApiParameterSerializer::convertType([1, 2, 3], 'array'));

        // From string (comma-separated)
        $this->assertEquals(['a', 'b', 'c'], OpenApiParameterSerializer::convertType('a,b,c', 'array'));

        // From JSON string
        $this->assertEquals(['key' => 'value'], OpenApiParameterSerializer::convertType('{"key":"value"}', 'array'));

        // From single value
        $this->assertEquals(['single'], OpenApiParameterSerializer::convertType('single', 'array'));

        // From null
        $this->assertEquals([], OpenApiParameterSerializer::convertType(null, 'array'));
    }

    /** @test */
    public function it_can_serialize_arrays_with_different_styles()
    {
        $value = ['red', 'blue', 'green'];
        $definitions = ['style' => 'simple'];

        // Test through serialize method which calls protected methods
        $result = OpenApiParameterSerializer::serialize(['tags' => $value], ['tags' => $definitions]);
        $this->assertEquals('red,blue,green', $result['tags']);

        // Test different styles through serialize method
        $definitions['style'] = 'spaceDelimited';
        $result = OpenApiParameterSerializer::serialize(['tags' => $value], ['tags' => $definitions]);
        $this->assertEquals('red blue green', $result['tags']);

        $definitions['style'] = 'pipeDelimited';
        $result = OpenApiParameterSerializer::serialize(['tags' => $value], ['tags' => $definitions]);
        $this->assertEquals('red|blue|green', $result['tags']);
    }

    /** @test */
    public function it_can_serialize_objects_with_different_styles()
    {
        $value = ['name' => 'John', 'age' => 30];
        $definitions = ['type' => 'object', 'style' => 'simple'];

        // Test through serialize method which calls protected methods
        $result = OpenApiParameterSerializer::serialize(['user' => $value], ['user' => $definitions]);
        $this->assertEquals('name,John,age,30', $result['user']);

        // Form style (not exploded)
        $definitions['style'] = 'form';
        $result = OpenApiParameterSerializer::serialize(['user' => $value], ['user' => $definitions]);
        $this->assertEquals('name,John,age,30', $result['user']);
    }

    /** @test */
    public function it_can_validate_numeric_parameters()
    {
        $definition = [
            'type' => 'integer',
            'minimum' => 1,
            'maximum' => 100,
            'multipleOf' => 5
        ];

        // Valid value
        $errors = OpenApiParameterSerializer::validateParameter(25, $definition);
        $this->assertEmpty($errors);

        // Below minimum
        $errors = OpenApiParameterSerializer::validateParameter(0, $definition);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('greater than or equal to 1', $errors[0]);

        // Above maximum
        $errors = OpenApiParameterSerializer::validateParameter(150, $definition);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('less than or equal to 100', $errors[0]);

        // Not multiple of 5
        $errors = OpenApiParameterSerializer::validateParameter(23, $definition);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('multiple of 5', $errors[0]);
    }

    /** @test */
    public function it_can_validate_string_parameters()
    {
        $definition = [
            'type' => 'string',
            'minLength' => 3,
            'maxLength' => 10,
            'pattern' => '^[a-zA-Z]+$',
            'enum' => ['red', 'blue', 'green']
        ];

        // Valid value
        $errors = OpenApiParameterSerializer::validateParameter('blue', $definition);
        $this->assertEmpty($errors);

        // Too short
        $errors = OpenApiParameterSerializer::validateParameter('ab', $definition);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('at least 3 characters', $errors[0]);

        // Too long
        $errors = OpenApiParameterSerializer::validateParameter('verylongstring', $definition);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('no more than 10 characters', $errors[0]);

        // Invalid pattern
        $errors = OpenApiParameterSerializer::validateParameter('red123', $definition);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('does not match required pattern', $errors[0]);

        // Not in enum
        $errors = OpenApiParameterSerializer::validateParameter('yellow', $definition);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('must be one of: red, blue, green', $errors[0]);
    }

    /** @test */
    public function it_can_validate_email_format()
    {
        $definition = [
            'type' => 'string',
            'format' => 'email'
        ];

        // Valid email
        $errors = OpenApiParameterSerializer::validateParameter('test@example.com', $definition);
        $this->assertEmpty($errors);

        // Invalid email
        $errors = OpenApiParameterSerializer::validateParameter('not-an-email', $definition);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('valid email address', $errors[0]);
    }

    /** @test */
    public function it_can_validate_uuid_format()
    {
        $definition = [
            'type' => 'string',
            'format' => 'uuid'
        ];

        // Valid UUID
        $errors = OpenApiParameterSerializer::validateParameter('550e8400-e29b-41d4-a716-446655440000', $definition);
        $this->assertEmpty($errors);

        // Invalid UUID
        $errors = OpenApiParameterSerializer::validateParameter('not-a-uuid', $definition);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('valid UUID', $errors[0]);
    }

    /** @test */
    public function it_can_validate_date_formats()
    {
        $dateDefinition = [
            'type' => 'string',
            'format' => 'date'
        ];

        // Valid date
        $errors = OpenApiParameterSerializer::validateParameter('2023-12-25', $dateDefinition);
        $this->assertEmpty($errors);

        // Invalid date
        $errors = OpenApiParameterSerializer::validateParameter('not-a-date', $dateDefinition);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('valid date in YYYY-MM-DD format', $errors[0]);

        $dateTimeDefinition = [
            'type' => 'string',
            'format' => 'date-time'
        ];

        // Valid date-time
        $errors = OpenApiParameterSerializer::validateParameter('2023-12-25T15:30:00Z', $dateTimeDefinition);
        $this->assertEmpty($errors);

        // Invalid date-time
        $errors = OpenApiParameterSerializer::validateParameter('not-a-datetime', $dateTimeDefinition);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('valid date-time in ISO 8601 format', $errors[0]);
    }

    /** @test */
    public function it_can_validate_array_parameters()
    {
        $definition = [
            'type' => 'array',
            'minItems' => 2,
            'maxItems' => 5,
            'uniqueItems' => true,
            'items' => [
                'type' => 'string',
                'minLength' => 2
            ]
        ];

        // Valid array
        $errors = OpenApiParameterSerializer::validateParameter(['ab', 'cd', 'ef'], $definition);
        $this->assertEmpty($errors);

        // Too few items
        $errors = OpenApiParameterSerializer::validateParameter(['ab'], $definition);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('at least 2 items', $errors[0]);

        // Too many items
        $errors = OpenApiParameterSerializer::validateParameter(['a', 'b', 'c', 'd', 'e', 'f'], $definition);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('no more than 5 items', $errors[0]);

        // Non-unique items
        $errors = OpenApiParameterSerializer::validateParameter(['ab', 'ab', 'cd'], $definition);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('must be unique', $errors[0]);

        // Invalid item
        $errors = OpenApiParameterSerializer::validateParameter(['ab', 'x', 'cd'], $definition);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('Item 1:', $errors[0]);
    }

    /** @test */
    public function it_can_validate_required_parameters()
    {
        $definition = [
            'type' => 'string',
            'required' => true
        ];

        // Valid value
        $errors = OpenApiParameterSerializer::validateParameter('value', $definition);
        $this->assertEmpty($errors);

        // Missing value
        $errors = OpenApiParameterSerializer::validateParameter(null, $definition);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('required', $errors[0]);

        // Empty string
        $errors = OpenApiParameterSerializer::validateParameter('', $definition);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('required', $errors[0]);
    }

    /** @test */
    public function it_can_serialize_parameters_with_definitions()
    {
        $parameters = [
            'name' => 'John Doe',
            'age' => '30',
            'active' => 'true',
            'tags' => ['admin', 'user'],
            'created_at' => '2023-12-25'
        ];

        $definitions = [
            'name' => ['type' => 'string'],
            'age' => ['type' => 'integer'],
            'active' => ['type' => 'boolean'],
            'tags' => ['type' => 'array', 'style' => 'simple'],
            'created_at' => ['type' => 'string', 'format' => 'date']
        ];

        $serialized = OpenApiParameterSerializer::serialize($parameters, $definitions);

        $this->assertEquals('John Doe', $serialized['name']);
        $this->assertEquals(30, $serialized['age']);
        $this->assertTrue($serialized['active']);
        $this->assertEquals('admin,user', $serialized['tags']);
        $this->assertEquals('2023-12-25', $serialized['created_at']);
    }

    /** @test */
    public function it_can_parse_query_parameters()
    {
        $queryString = 'name=John&age=30&active=true&tags=red,blue,green';
        
        $definitions = [
            'name' => ['type' => 'string'],
            'age' => ['type' => 'integer'],
            'active' => ['type' => 'boolean'],
            'tags' => ['type' => 'array', 'style' => 'simple']
        ];

        $parsed = OpenApiParameterSerializer::parseQueryParameters($queryString, $definitions);

        $this->assertEquals('John', $parsed['name']);
        $this->assertEquals(30, $parsed['age']);
        $this->assertTrue($parsed['active']);
        $this->assertEquals(['red', 'blue', 'green'], $parsed['tags']);
    }

    /** @test */
    public function it_handles_different_array_parsing_styles()
    {
        // Test through parseQueryParameters which calls protected methods
        $queryString = 'tags=red,blue,green';
        $definitions = ['tags' => ['type' => 'array', 'style' => 'simple']];
        $parsed = OpenApiParameterSerializer::parseQueryParameters($queryString, $definitions);
        $this->assertEquals(['red', 'blue', 'green'], $parsed['tags']);

        // Test space delimited through array conversion
        $result = OpenApiParameterSerializer::convertType('red blue green', 'array');
        $this->assertIsArray($result);

        // Test pipe delimited through array conversion
        $result = OpenApiParameterSerializer::convertType('red|blue|green', 'array');
        $this->assertIsArray($result);

        // Already an array
        $result = OpenApiParameterSerializer::convertType(['red', 'blue', 'green'], 'array');
        $this->assertEquals(['red', 'blue', 'green'], $result);
    }

    /** @test */
    public function it_handles_object_parameter_parsing()
    {
        // JSON string through convertType
        $result = OpenApiParameterSerializer::convertType('{"name":"John","age":30}', 'object');
        $this->assertEquals(['name' => 'John', 'age' => 30], $result);

        // Test through parseQueryParameters
        $queryString = 'user={"name":"John","age":30}';
        $definitions = ['user' => ['type' => 'object']];
        $parsed = OpenApiParameterSerializer::parseQueryParameters($queryString, $definitions);
        $this->assertEquals(['name' => 'John', 'age' => 30], $parsed['user']);

        // Already an array
        $result = OpenApiParameterSerializer::convertType(['name' => 'John'], 'object');
        $this->assertEquals(['name' => 'John'], $result);
    }

    /** @test */
    public function it_handles_edge_cases_gracefully()
    {
        // Null values
        $this->assertNull(OpenApiParameterSerializer::convertType(null, 'string'));
        $this->assertEquals([], OpenApiParameterSerializer::convertType(null, 'array'));

        // Empty strings
        $this->assertNull(OpenApiParameterSerializer::convertType('', 'integer'));
        $this->assertNull(OpenApiParameterSerializer::convertType('', 'number'));

        // Invalid JSON in array conversion
        $result = OpenApiParameterSerializer::convertType('invalid-json', 'array');
        $this->assertEquals(['invalid-json'], $result);

        // Unknown parameter in serialization
        $result = OpenApiParameterSerializer::serialize(['unknown' => 'value'], []);
        $this->assertEquals(['unknown' => 'value'], $result);
    }

    /** @test */
    public function it_validates_exclusive_minimum_and_maximum()
    {
        $definition = [
            'type' => 'number',
            'minimum' => 0,
            'exclusiveMinimum' => true,
            'maximum' => 100,
            'exclusiveMaximum' => true
        ];

        // Valid value
        $errors = OpenApiParameterSerializer::validateParameter(50, $definition);
        $this->assertEmpty($errors);

        // Equal to exclusive minimum (should fail)
        $errors = OpenApiParameterSerializer::validateParameter(0, $definition);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('greater than 0', $errors[0]);

        // Equal to exclusive maximum (should fail)
        $errors = OpenApiParameterSerializer::validateParameter(100, $definition);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('less than 100', $errors[0]);
    }
}
