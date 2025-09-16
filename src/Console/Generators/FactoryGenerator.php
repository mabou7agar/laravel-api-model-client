<?php

namespace MTechStack\LaravelApiModelClient\Console\Generators;

use Illuminate\Support\Str;

/**
 * Generates Laravel model factory classes from OpenAPI model data
 */
class FactoryGenerator
{
    /**
     * Generate factory class code
     */
    public function generate(array $modelData, string $namespace = 'Database\\Factories'): string
    {
        $modelName = $modelData['modelName'];
        $factoryName = $modelName . 'Factory';
        
        $code = $this->generateFactoryClass($modelData, $factoryName, $namespace);
        
        return $code;
    }

    /**
     * Generate the complete factory class
     */
    protected function generateFactoryClass(array $modelData, string $factoryName, string $namespace): string
    {
        $modelName = $modelData['modelName'];
        $definition = $this->generateDefinitionMethod($modelData);
        $states = $this->generateStates($modelData);
        
        return "<?php

namespace {$namespace};

use Illuminate\Database\Eloquent\Factories\Factory;
use App\\Models\\{$modelName};

/**
 * Factory for {$modelName} model
 * 
 * Generated from OpenAPI schema
 */
class {$factoryName} extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected \$model = {$modelName}::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
{$definition}
        ];
    }
{$states}
}
";
    }

    /**
     * Generate the definition method content
     */
    protected function generateDefinitionMethod(array $modelData): string
    {
        $properties = $modelData['properties'] ?? [];
        $definitions = [];
        
        foreach ($properties as $propertyName => $propertyData) {
            $fakerMethod = $this->getFakerMethod($propertyData);
            if ($fakerMethod) {
                $definitions[] = "            '{$propertyName}' => \$this->faker->{$fakerMethod},";
            }
        }
        
        return implode("\n", $definitions);
    }

    /**
     * Generate factory states based on enum values and validation rules
     */
    protected function generateStates(array $modelData): string
    {
        $states = [];
        $properties = $modelData['properties'] ?? [];
        
        // Generate states for enum properties
        foreach ($properties as $propertyName => $propertyData) {
            if (isset($propertyData['enum']) && is_array($propertyData['enum'])) {
                foreach ($propertyData['enum'] as $enumValue) {
                    $stateName = Str::camel($propertyName . '_' . $enumValue);
                    $states[] = $this->generateEnumState($stateName, $propertyName, $enumValue);
                }
            }
        }
        
        // Generate common states
        $states[] = $this->generateInvalidState($modelData);
        
        return empty($states) ? '' : "\n" . implode("\n", $states);
    }

    /**
     * Generate state for enum value
     */
    protected function generateEnumState(string $stateName, string $propertyName, string $enumValue): string
    {
        return "
    /**
     * State for {$propertyName} = {$enumValue}
     */
    public function {$stateName}(): static
    {
        return \$this->state(fn (array \$attributes) => [
            '{$propertyName}' => '{$enumValue}',
        ]);
    }";
    }

    /**
     * Generate invalid state for testing validation
     */
    protected function generateInvalidState(array $modelData): string
    {
        $properties = $modelData['properties'] ?? [];
        $invalidAttributes = [];
        
        foreach ($properties as $propertyName => $propertyData) {
            $invalidValue = $this->getInvalidValue($propertyData);
            if ($invalidValue !== null) {
                $invalidAttributes[] = "            '{$propertyName}' => {$invalidValue},";
                break; // Only need one invalid attribute
            }
        }
        
        if (empty($invalidAttributes)) {
            return '';
        }
        
        return "
    /**
     * State with invalid data for testing validation
     */
    public function invalid(): static
    {
        return \$this->state(fn (array \$attributes) => [
" . implode("\n", $invalidAttributes) . "
        ]);
    }";
    }

    /**
     * Get appropriate faker method for property type
     */
    protected function getFakerMethod(array $propertyData): ?string
    {
        $type = $propertyData['type'] ?? 'string';
        $format = $propertyData['format'] ?? null;
        $propertyName = $propertyData['propertyName'] ?? '';
        
        // Check for specific property names first
        $nameBasedMethods = [
            'email' => 'safeEmail',
            'name' => 'name',
            'first_name' => 'firstName',
            'last_name' => 'lastName',
            'phone' => 'phoneNumber',
            'address' => 'address',
            'city' => 'city',
            'country' => 'country',
            'postal_code' => 'postcode',
            'zip_code' => 'postcode',
            'description' => 'text',
            'title' => 'sentence(3)',
            'url' => 'url',
            'website' => 'url',
            'company' => 'company',
            'username' => 'userName',
            'password' => 'password',
            'avatar' => 'imageUrl',
            'image' => 'imageUrl',
            'slug' => 'slug',
        ];
        
        $lowerName = strtolower($propertyName);
        if (isset($nameBasedMethods[$lowerName])) {
            return $nameBasedMethods[$lowerName];
        }
        
        // Check for format-specific methods
        if ($format) {
            switch ($format) {
                case 'email':
                    return 'safeEmail';
                case 'date':
                    return 'date';
                case 'date-time':
                    return 'dateTime';
                case 'uri':
                case 'url':
                    return 'url';
                case 'uuid':
                    return 'uuid';
                case 'password':
                    return 'password';
            }
        }
        
        // Check for enum values
        if (isset($propertyData['enum']) && is_array($propertyData['enum'])) {
            $enumValues = array_map(fn($v) => "'{$v}'", $propertyData['enum']);
            return 'randomElement([' . implode(', ', $enumValues) . '])';
        }
        
        // Type-based methods
        switch ($type) {
            case 'integer':
                $min = $propertyData['minimum'] ?? 1;
                $max = $propertyData['maximum'] ?? 1000;
                return "numberBetween({$min}, {$max})";
                
            case 'number':
                $min = $propertyData['minimum'] ?? 1.0;
                $max = $propertyData['maximum'] ?? 1000.0;
                return "randomFloat(2, {$min}, {$max})";
                
            case 'boolean':
                return 'boolean';
                
            case 'array':
                return 'words(3)';
                
            case 'string':
                $minLength = $propertyData['minLength'] ?? 10;
                $maxLength = $propertyData['maxLength'] ?? 255;
                
                if ($maxLength <= 50) {
                    return 'word';
                } elseif ($maxLength <= 100) {
                    return 'sentence';
                } else {
                    return "text({$maxLength})";
                }
                
            default:
                return 'word';
        }
    }

    /**
     * Get invalid value for testing validation
     */
    protected function getInvalidValue(array $propertyData): ?string
    {
        $type = $propertyData['type'] ?? 'string';
        
        switch ($type) {
            case 'integer':
                if (isset($propertyData['minimum'])) {
                    return (string)($propertyData['minimum'] - 1);
                }
                return "'invalid_integer'";
                
            case 'number':
                if (isset($propertyData['minimum'])) {
                    return (string)($propertyData['minimum'] - 0.1);
                }
                return "'invalid_number'";
                
            case 'boolean':
                return "'invalid_boolean'";
                
            case 'string':
                if (isset($propertyData['format'])) {
                    switch ($propertyData['format']) {
                        case 'email':
                            return "'invalid_email'";
                        case 'date':
                            return "'invalid_date'";
                        case 'date-time':
                            return "'invalid_datetime'";
                    }
                }
                
                if (isset($propertyData['maxLength'])) {
                    $longString = str_repeat('a', $propertyData['maxLength'] + 1);
                    return "'{$longString}'";
                }
                
                if (isset($propertyData['enum'])) {
                    return "'invalid_enum_value'";
                }
                
                return null;
                
            default:
                return null;
        }
    }

    /**
     * Get the factory file path
     */
    public function getFactoryPath(string $modelName, string $basePath = 'database/factories'): string
    {
        return $basePath . '/' . $modelName . 'Factory.php';
    }
}
