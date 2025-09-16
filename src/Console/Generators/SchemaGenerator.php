<?php

namespace MTechStack\LaravelApiModelClient\Console\Generators;

use Illuminate\Support\Str;

/**
 * Generates migration-like schema definitions from OpenAPI model data
 */
class SchemaGenerator
{
    /**
     * Generate schema definition code
     */
    public function generate(array $modelData, string $namespace = 'Database\\Schemas'): string
    {
        $modelName = $modelData['modelName'];
        $schemaName = $modelName . 'Schema';
        
        $code = $this->generateSchemaClass($modelData, $schemaName, $namespace);
        
        return $code;
    }

    /**
     * Generate the complete schema class
     */
    protected function generateSchemaClass(array $modelData, string $schemaName, string $namespace): string
    {
        $modelName = $modelData['modelName'];
        $tableName = Str::snake(Str::plural($modelName));
        $schema = $this->generateSchemaDefinition($modelData);
        $indexes = $this->generateIndexes($modelData);
        $relationships = $this->generateRelationshipConstraints($modelData);
        
        return "<?php

namespace {$namespace};

/**
 * Schema definition for {$modelName} model
 * 
 * Generated from OpenAPI schema
 * This class provides a migration-like structure for reference
 */
class {$schemaName}
{
    /**
     * Table name
     */
    public const TABLE_NAME = '{$tableName}';

    /**
     * Get the schema definition
     */
    public static function getSchema(): array
    {
        return [
{$schema}
        ];
    }

    /**
     * Get index definitions
     */
    public static function getIndexes(): array
    {
        return [
{$indexes}
        ];
    }

    /**
     * Get relationship constraints
     */
    public static function getRelationships(): array
    {
        return [
{$relationships}
        ];
    }

    /**
     * Generate Laravel migration code
     */
    public static function toMigration(): string
    {
        \$tableName = self::TABLE_NAME;
        \$schema = self::getSchema();
        \$indexes = self::getIndexes();
        
        \$migration = \"<?php\\n\\n\";
        \$migration .= \"use Illuminate\\Database\\Migrations\\Migration;\\n\";
        \$migration .= \"use Illuminate\\Database\\Schema\\Blueprint;\\n\";
        \$migration .= \"use Illuminate\\Support\\Facades\\Schema;\\n\\n\";
        \$migration .= \"return new class extends Migration\\n\";
        \$migration .= \"{\\n\";
        \$migration .= \"    public function up()\\n\";
        \$migration .= \"    {\\n\";
        \$migration .= \"        Schema::create('{\$tableName}', function (Blueprint \\$table) {\\n\";
        
        foreach (\$schema as \$column => \$definition) {
            \$migration .= \"            \\$table->{\$definition['type']}('{\$column}')\";\n            
            if (\$definition['nullable']) {
                \$migration .= \"->nullable()\";\n            }
            if (isset(\$definition['default'])) {
                \$migration .= \"->default('{\$definition['default']}')\";\n            }
            \$migration .= \";\\n\";
        }
        
        foreach (\$indexes as \$index) {
            \$migration .= \"            \\$table->{\$index['type']}({\$index['columns']});\\n\";
        }
        
        \$migration .= \"            \\$table->timestamps();\\n\";
        \$migration .= \"        });\\n\";
        \$migration .= \"    }\\n\\n\";
        \$migration .= \"    public function down()\\n\";
        \$migration .= \"    {\\n\";
        \$migration .= \"        Schema::dropIfExists('{\$tableName}');\\n\";
        \$migration .= \"    }\\n\";
        \$migration .= \"};\\n\";
        
        return \$migration;
    }
}
";
    }

    /**
     * Generate schema definition array
     */
    protected function generateSchemaDefinition(array $modelData): string
    {
        $properties = $modelData['properties'] ?? [];
        $requiredFields = $modelData['requiredFields'] ?? [];
        $definitions = [];
        
        // Add primary key
        $definitions[] = "            'id' => [
                'type' => 'id',
                'nullable' => false,
                'primary' => true,
            ],";
        
        foreach ($properties as $propertyName => $propertyData) {
            $columnDefinition = $this->getColumnDefinition($propertyData, $propertyName, $requiredFields);
            $definitions[] = "            '{$propertyName}' => [
{$columnDefinition}
            ],";
        }
        
        return implode("\n", $definitions);
    }

    /**
     * Get column definition for a property
     */
    protected function getColumnDefinition(array $propertyData, string $propertyName, array $requiredFields): string
    {
        $type = $this->getLaravelColumnType($propertyData);
        $nullable = !in_array($propertyName, $requiredFields);
        $definition = [];
        
        $definition[] = "                'type' => '{$type}',";
        $definition[] = "                'nullable' => " . ($nullable ? 'true' : 'false') . ",";
        
        // Add length constraints
        if (isset($propertyData['maxLength'])) {
            $definition[] = "                'length' => {$propertyData['maxLength']},";
        }
        
        // Add numeric constraints
        if (isset($propertyData['minimum'])) {
            $definition[] = "                'minimum' => {$propertyData['minimum']},";
        }
        if (isset($propertyData['maximum'])) {
            $definition[] = "                'maximum' => {$propertyData['maximum']},";
        }
        
        // Add default value
        if (isset($propertyData['default'])) {
            $defaultValue = is_string($propertyData['default']) 
                ? "'{$propertyData['default']}'" 
                : $propertyData['default'];
            $definition[] = "                'default' => {$defaultValue},";
        }
        
        // Add enum values
        if (isset($propertyData['enum'])) {
            $enumValues = array_map(fn($v) => "'{$v}'", $propertyData['enum']);
            $definition[] = "                'enum' => [" . implode(', ', $enumValues) . "],";
        }
        
        // Add description
        if (isset($propertyData['description'])) {
            $description = addslashes($propertyData['description']);
            $definition[] = "                'description' => '{$description}',";
        }
        
        return implode("\n", $definition);
    }

    /**
     * Get Laravel column type from OpenAPI property
     */
    protected function getLaravelColumnType(array $propertyData): string
    {
        $type = $propertyData['type'] ?? 'string';
        $format = $propertyData['format'] ?? null;
        
        switch ($type) {
            case 'integer':
                if ($format === 'int64') {
                    return 'bigInteger';
                }
                return 'integer';
                
            case 'number':
                if ($format === 'float') {
                    return 'float';
                }
                return 'decimal';
                
            case 'boolean':
                return 'boolean';
                
            case 'array':
            case 'object':
                return 'json';
                
            case 'string':
                switch ($format) {
                    case 'date':
                        return 'date';
                    case 'date-time':
                        return 'timestamp';
                    case 'email':
                        return 'string';
                    case 'uuid':
                        return 'uuid';
                    case 'password':
                        return 'string';
                    case 'binary':
                        return 'binary';
                    default:
                        // Check for text vs string based on length
                        if (isset($propertyData['maxLength']) && $propertyData['maxLength'] > 255) {
                            return 'text';
                        }
                        return 'string';
                }
                
            default:
                return 'string';
        }
    }

    /**
     * Generate index definitions
     */
    protected function generateIndexes(array $modelData): string
    {
        $properties = $modelData['properties'] ?? [];
        $indexes = [];
        
        foreach ($properties as $propertyName => $propertyData) {
            // Create index for foreign keys
            if (str_ends_with($propertyName, '_id')) {
                $indexes[] = "            [
                'type' => 'index',
                'columns' => ['{$propertyName}'],
                'name' => 'idx_{$propertyName}',
            ],";
            }
            
            // Create unique index for unique fields
            if (isset($propertyData['format']) && $propertyData['format'] === 'email') {
                $indexes[] = "            [
                'type' => 'unique',
                'columns' => ['{$propertyName}'],
                'name' => 'unique_{$propertyName}',
            ],";
            }
            
            // Create index for enum fields (for faster filtering)
            if (isset($propertyData['enum'])) {
                $indexes[] = "            [
                'type' => 'index',
                'columns' => ['{$propertyName}'],
                'name' => 'idx_{$propertyName}',
            ],";
            }
        }
        
        return implode("\n", $indexes);
    }

    /**
     * Generate relationship constraint definitions
     */
    protected function generateRelationshipConstraints(array $modelData): string
    {
        $relationships = $modelData['relationships'] ?? [];
        $constraints = [];
        
        foreach ($relationships as $relationName => $relationData) {
            if ($relationData['type'] === 'belongsTo') {
                $foreignKey = $relationData['foreignKey'];
                $relatedTable = Str::snake(Str::plural($relationData['relatedModel']));
                $localKey = $relationData['localKey'];
                
                $constraints[] = "            '{$relationName}' => [
                'type' => 'belongsTo',
                'foreign_key' => '{$foreignKey}',
                'references' => '{$localKey}',
                'on' => '{$relatedTable}',
                'on_delete' => 'cascade',
                'on_update' => 'cascade',
            ],";
            }
        }
        
        return implode("\n", $constraints);
    }

    /**
     * Get the schema file path
     */
    public function getSchemaPath(string $modelName, string $basePath = 'database/schemas'): string
    {
        return $basePath . '/' . $modelName . 'Schema.php';
    }

    /**
     * Generate a complete migration file
     */
    public function generateMigration(array $modelData, string $timestamp = null): string
    {
        $modelName = $modelData['modelName'];
        $tableName = Str::snake(Str::plural($modelName));
        $className = 'Create' . Str::plural($modelName) . 'Table';
        $timestamp = $timestamp ?? date('Y_m_d_His');
        
        $properties = $modelData['properties'] ?? [];
        $requiredFields = $modelData['requiredFields'] ?? [];
        $relationships = $modelData['relationships'] ?? [];
        
        $columns = [];
        $indexes = [];
        $foreignKeys = [];
        
        // Generate columns
        foreach ($properties as $propertyName => $propertyData) {
            $columnType = $this->getLaravelColumnType($propertyData);
            $nullable = !in_array($propertyName, $requiredFields);
            
            $columnDefinition = "\$table->{$columnType}('{$propertyName}')";
            
            if ($nullable) {
                $columnDefinition .= '->nullable()';
            }
            
            if (isset($propertyData['default'])) {
                $defaultValue = is_string($propertyData['default']) 
                    ? "'{$propertyData['default']}'" 
                    : $propertyData['default'];
                $columnDefinition .= "->default({$defaultValue})";
            }
            
            $columns[] = "            {$columnDefinition};";
            
            // Add indexes for foreign keys
            if (str_ends_with($propertyName, '_id')) {
                $indexes[] = "            \$table->index('{$propertyName}');";
            }
        }
        
        // Generate foreign key constraints
        foreach ($relationships as $relationName => $relationData) {
            if ($relationData['type'] === 'belongsTo') {
                $foreignKey = $relationData['foreignKey'];
                $relatedTable = Str::snake(Str::plural($relationData['relatedModel']));
                $localKey = $relationData['localKey'];
                
                $foreignKeys[] = "            \$table->foreign('{$foreignKey}')->references('{$localKey}')->on('{$relatedTable}')->onDelete('cascade');";
            }
        }
        
        return "<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('{$tableName}', function (Blueprint \$table) {
            \$table->id();
" . implode("\n", $columns) . "
            \$table->timestamps();
            
            // Indexes
" . implode("\n", $indexes) . "
            
            // Foreign key constraints
" . implode("\n", $foreignKeys) . "
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('{$tableName}');
    }
};
";
    }

    /**
     * Get migration file name
     */
    public function getMigrationFileName(string $modelName, string $timestamp = null): string
    {
        $timestamp = $timestamp ?? date('Y_m_d_His');
        $tableName = Str::snake(Str::plural($modelName));
        return "{$timestamp}_create_{$tableName}_table.php";
    }
}
