# Migration Guide: From Manual to OpenAPI-Driven Configuration

This guide will help you migrate your existing Laravel API Model Client implementations from manual configuration to OpenAPI-driven automation.

## Table of Contents

1. [Migration Overview](#migration-overview)
2. [Pre-Migration Assessment](#pre-migration-assessment)
3. [Step-by-Step Migration](#step-by-step-migration)
4. [Configuration Migration](#configuration-migration)
5. [Model Migration](#model-migration)
6. [Query Migration](#query-migration)
7. [Validation Migration](#validation-migration)
8. [Testing Migration](#testing-migration)
9. [Common Migration Issues](#common-migration-issues)
10. [Rollback Strategy](#rollback-strategy)

## Migration Overview

### What Changes

**Before (Manual Configuration):**
```php
class Pet extends ApiModel
{
    protected $fillable = ['name', 'status', 'category_id'];
    protected $casts = ['id' => 'integer', 'category_id' => 'integer'];
    protected string $endpoint = '/pets';
    
    public function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'status' => 'required|in:available,pending,sold',
        ];
    }
}
```

**After (OpenAPI-Driven):**
```php
class Pet extends ApiModel
{
    use HasOpenApiSchema;
    
    protected string $openApiSchemaSource = 'primary';
    // fillable, casts, validation rules auto-generated from OpenAPI schema
}
```

### Benefits of Migration

- **Automatic Validation**: Rules generated from OpenAPI schema
- **Type Safety**: Automatic casting based on schema types
- **Documentation Sync**: Models stay in sync with API documentation
- **Enhanced Query Builder**: OpenAPI-aware parameter validation
- **Relationship Detection**: Automatic relationship mapping from schema references
- **Performance**: Built-in caching and optimization

## Pre-Migration Assessment

### 1. Inventory Your Current Models

Create a list of your existing API models:

```bash
# Find all ApiModel classes
find app/ -name "*.php" -exec grep -l "extends ApiModel" {} \;

# Or use this PHP script
php artisan tinker
>>> $models = collect(get_declared_classes())
    ->filter(fn($class) => is_subclass_of($class, 'MTechStack\LaravelApiModelClient\Models\ApiModel'))
    ->values();
>>> $models->each(fn($model) => dump($model));
```

### 2. Assess API Documentation

Check if your APIs have OpenAPI/Swagger documentation:

```bash
# Common OpenAPI endpoints to check
curl https://your-api.com/swagger.json
curl https://your-api.com/openapi.json
curl https://your-api.com/api-docs
curl https://your-api.com/docs/swagger.json
```

### 3. Compatibility Check

Run the compatibility checker:

```php
<?php
// Create a migration assessment script

use MTechStack\LaravelApiModelClient\OpenApi\OpenApiSchemaParser;

class MigrationAssessment
{
    public function assessModel(string $modelClass): array
    {
        $reflection = new ReflectionClass($modelClass);
        $model = new $modelClass;
        
        return [
            'class' => $modelClass,
            'endpoint' => $model->getEndpoint(),
            'fillable' => $model->getFillable(),
            'casts' => $model->getCasts(),
            'has_validation' => method_exists($model, 'rules'),
            'has_relationships' => $this->hasRelationships($reflection),
            'complexity' => $this->assessComplexity($model),
        ];
    }
    
    private function hasRelationships(ReflectionClass $reflection): bool
    {
        $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);
        return collect($methods)->contains(function($method) {
            $code = file_get_contents($method->getFileName());
            return str_contains($code, 'belongsTo') || 
                   str_contains($code, 'hasMany') || 
                   str_contains($code, 'hasOne');
        });
    }
    
    private function assessComplexity(ApiModel $model): string
    {
        $fillableCount = count($model->getFillable());
        $castCount = count($model->getCasts());
        
        if ($fillableCount > 20 || $castCount > 15) return 'high';
        if ($fillableCount > 10 || $castCount > 8) return 'medium';
        return 'low';
    }
}

// Run assessment
$assessment = new MigrationAssessment();
$models = [App\Models\Api\Pet::class, App\Models\Api\Category::class];
foreach ($models as $model) {
    dump($assessment->assessModel($model));
}
```

## Step-by-Step Migration

### Step 1: Install OpenAPI Dependencies

```bash
# Update composer.json if needed
composer require cebe/php-openapi

# Update the package
composer update m-tech-stack/laravel-api-model-client
```

### Step 2: Configure OpenAPI Schemas

Add OpenAPI configuration to `config/api-client.php`:

```php
<?php

return [
    'schemas' => [
        'primary' => [
            'source' => env('API_CLIENT_PRIMARY_SCHEMA'),
            'base_url' => env('API_CLIENT_PRIMARY_BASE_URL'),
            'authentication' => [
                'type' => env('API_CLIENT_AUTH_TYPE', 'bearer'),
                'token' => env('API_CLIENT_TOKEN'),
            ],
            'validation' => [
                'strictness' => 'moderate', // Start with moderate for migration
            ],
        ],
    ],
    
    'default_schema' => 'primary',
    
    'migration' => [
        'backup_original_models' => true,
        'generate_comparison_report' => true,
        'validation_mode' => 'warning', // Don't fail on validation differences
    ],
];
```

### Step 3: Generate OpenAPI Models

```bash
# Generate new models in a separate namespace for comparison
php artisan api-client:generate-models \
    --namespace="App\\Models\\ApiGenerated" \
    --output-dir="app/Models/ApiGenerated" \
    --dry-run

# Review the generated models
php artisan api-client:generate-models \
    --namespace="App\\Models\\ApiGenerated" \
    --output-dir="app/Models/ApiGenerated"
```

### Step 4: Compare Generated vs Manual Models

Create a comparison script:

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CompareModels extends Command
{
    protected $signature = 'migration:compare-models';
    protected $description = 'Compare manual and generated models';

    public function handle()
    {
        $comparisons = [
            ['manual' => \App\Models\Api\Pet::class, 'generated' => \App\Models\ApiGenerated\Pet::class],
            ['manual' => \App\Models\Api\Category::class, 'generated' => \App\Models\ApiGenerated\Category::class],
        ];

        foreach ($comparisons as $comparison) {
            $this->compareModels($comparison['manual'], $comparison['generated']);
        }
    }

    private function compareModels(string $manualClass, string $generatedClass)
    {
        $this->info("Comparing {$manualClass} vs {$generatedClass}");
        
        $manual = new $manualClass;
        $generated = new $generatedClass;
        
        // Compare fillable
        $manualFillable = $manual->getFillable();
        $generatedFillable = $generated->getFillable();
        
        $this->line("Fillable differences:");
        $this->line("  Manual only: " . json_encode(array_diff($manualFillable, $generatedFillable)));
        $this->line("  Generated only: " . json_encode(array_diff($generatedFillable, $manualFillable)));
        
        // Compare casts
        $manualCasts = $manual->getCasts();
        $generatedCasts = $generated->getCasts();
        
        $this->line("Cast differences:");
        foreach ($manualCasts as $field => $cast) {
            if (!isset($generatedCasts[$field])) {
                $this->line("  Manual cast missing in generated: {$field} => {$cast}");
            } elseif ($generatedCasts[$field] !== $cast) {
                $this->line("  Cast difference: {$field} => manual: {$cast}, generated: {$generatedCasts[$field]}");
            }
        }
        
        $this->line("");
    }
}
```

### Step 5: Migrate Models Gradually

#### Option A: In-Place Migration

```php
<?php

namespace App\Models\Api;

use MTechStack\LaravelApiModelClient\Models\ApiModel;
use MTechStack\LaravelApiModelClient\Traits\HasOpenApiSchema;

class Pet extends ApiModel
{
    use HasOpenApiSchema;

    protected string $openApiSchemaSource = 'primary';
    
    // Keep manual configuration as fallback during migration
    protected $fillable = ['name', 'status', 'category_id']; // Will be overridden by OpenAPI
    protected $casts = ['id' => 'integer']; // Will be merged with OpenAPI casts
    
    // Migration flag - remove after migration is complete
    protected bool $useOpenApiValidation = true;
    
    // Override validation during migration
    public function getValidationRules(string $operation = 'create'): array
    {
        if ($this->useOpenApiValidation) {
            try {
                return parent::getValidationRules($operation);
            } catch (\Exception $e) {
                // Fallback to manual rules if OpenAPI fails
                \Log::warning("OpenAPI validation failed for {$operation}, falling back to manual rules", [
                    'model' => static::class,
                    'error' => $e->getMessage()
                ]);
                return $this->getManualValidationRules($operation);
            }
        }
        
        return $this->getManualValidationRules($operation);
    }
    
    // Keep original validation as fallback
    private function getManualValidationRules(string $operation): array
    {
        return [
            'name' => 'required|string|max:255',
            'status' => 'required|in:available,pending,sold',
            'category_id' => 'integer|exists:categories,id',
        ];
    }
}
```

#### Option B: Side-by-Side Migration

```php
// Keep original model
class PetLegacy extends ApiModel
{
    // Original implementation
}

// Create new OpenAPI model
class Pet extends ApiModel
{
    use HasOpenApiSchema;
    
    protected string $openApiSchemaSource = 'primary';
}

// Use feature flags to switch between implementations
class PetService
{
    public function getModel(): string
    {
        return config('features.use_openapi_models') ? Pet::class : PetLegacy::class;
    }
}
```

## Configuration Migration

### Environment Variables

Update your `.env` file:

```env
# Old configuration
API_BASE_URL=https://api.example.com
API_TOKEN=your-token

# New OpenAPI configuration
API_CLIENT_PRIMARY_SCHEMA=https://api.example.com/openapi.json
API_CLIENT_PRIMARY_BASE_URL=https://api.example.com
API_CLIENT_PRIMARY_TOKEN=your-token
API_CLIENT_VALIDATION_STRICTNESS=moderate
```

### Configuration File Migration

```php
<?php
// config/api-client.php

return [
    // Migrate from old config structure
    'legacy' => [
        'base_url' => env('API_BASE_URL'), // Keep for fallback
        'token' => env('API_TOKEN'), // Keep for fallback
    ],
    
    // New OpenAPI configuration
    'schemas' => [
        'primary' => [
            'source' => env('API_CLIENT_PRIMARY_SCHEMA'),
            'base_url' => env('API_CLIENT_PRIMARY_BASE_URL', env('API_BASE_URL')), // Fallback
            'authentication' => [
                'type' => 'bearer',
                'token' => env('API_CLIENT_PRIMARY_TOKEN', env('API_TOKEN')), // Fallback
            ],
        ],
    ],
    
    'migration' => [
        'enable_legacy_fallback' => env('API_CLIENT_LEGACY_FALLBACK', true),
        'log_migration_issues' => true,
    ],
];
```

## Model Migration

### Automated Model Migration

Create a migration command:

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class MigrateToOpenApi extends Command
{
    protected $signature = 'migration:to-openapi {model?} {--dry-run} {--backup}';
    protected $description = 'Migrate models to OpenAPI configuration';

    public function handle()
    {
        $modelName = $this->argument('model');
        $dryRun = $this->option('dry-run');
        $backup = $this->option('backup');

        if ($modelName) {
            $this->migrateModel($modelName, $dryRun, $backup);
        } else {
            $this->migrateAllModels($dryRun, $backup);
        }
    }

    private function migrateModel(string $modelName, bool $dryRun, bool $backup)
    {
        $modelClass = "App\\Models\\Api\\{$modelName}";
        
        if (!class_exists($modelClass)) {
            $this->error("Model {$modelClass} not found");
            return;
        }

        $this->info("Migrating {$modelClass}");

        // Backup original if requested
        if ($backup && !$dryRun) {
            $this->backupModel($modelClass);
        }

        // Generate new model content
        $newContent = $this->generateOpenApiModel($modelClass);

        if ($dryRun) {
            $this->line("Would update {$modelClass}:");
            $this->line($newContent);
        } else {
            $this->updateModel($modelClass, $newContent);
            $this->info("✓ Migrated {$modelClass}");
        }
    }

    private function generateOpenApiModel(string $modelClass): string
    {
        $reflection = new \ReflectionClass($modelClass);
        $model = new $modelClass;
        
        // Extract current configuration
        $fillable = $model->getFillable();
        $casts = $model->getCasts();
        $endpoint = $model->getEndpoint();
        
        // Generate new model content
        return <<<PHP
<?php

namespace {$reflection->getNamespaceName()};

use MTechStack\LaravelApiModelClient\Models\ApiModel;
use MTechStack\LaravelApiModelClient\Traits\HasOpenApiSchema;

/**
 * {$reflection->getShortName()} Model - Migrated to OpenAPI
 * 
 * Auto-generated from OpenAPI schema
 */
class {$reflection->getShortName()} extends ApiModel
{
    use HasOpenApiSchema;

    protected string \$openApiSchemaSource = 'primary';
    protected string \$endpoint = '{$endpoint}';
    
    // Migration fallback - remove after confirming OpenAPI works
    protected \$fillable = [
        '" . implode("',\n        '", $fillable) . "'
    ];
    
    protected \$casts = [
        " . $this->formatCasts($casts) . "
    ];
}
PHP;
    }

    private function formatCasts(array $casts): string
    {
        $formatted = [];
        foreach ($casts as $field => $cast) {
            $formatted[] = "'{$field}' => '{$cast}'";
        }
        return implode(",\n        ", $formatted);
    }

    private function backupModel(string $modelClass)
    {
        $reflection = new \ReflectionClass($modelClass);
        $originalFile = $reflection->getFileName();
        $backupFile = $originalFile . '.backup.' . date('Y-m-d-H-i-s');
        
        copy($originalFile, $backupFile);
        $this->info("✓ Backed up to {$backupFile}");
    }

    private function updateModel(string $modelClass, string $newContent)
    {
        $reflection = new \ReflectionClass($modelClass);
        $file = $reflection->getFileName();
        
        file_put_contents($file, $newContent);
    }
}
```

### Manual Model Migration Steps

1. **Add the OpenAPI trait**:
```php
use MTechStack\LaravelApiModelClient\Traits\HasOpenApiSchema;

class Pet extends ApiModel
{
    use HasOpenApiSchema; // Add this line
}
```

2. **Configure schema source**:
```php
protected string $openApiSchemaSource = 'primary';
```

3. **Remove manual configuration** (gradually):
```php
// Comment out manual configuration first
// protected $fillable = ['name', 'status'];
// protected $casts = ['id' => 'integer'];
```

4. **Test and verify**:
```php
// Test that auto-generated configuration works
$pet = new Pet();
dd($pet->getFillable(), $pet->getCasts());
```

## Query Migration

### Before (Manual Queries)

```php
// Manual parameter validation
$pets = Pet::where('status', 'available')
    ->where('category_id', '>', 1)
    ->orderBy('name')
    ->limit(10)
    ->get();

// Manual validation
$validator = Validator::make($request->all(), [
    'status' => 'in:available,pending,sold',
    'category_id' => 'integer|min:1',
]);
```

### After (OpenAPI Queries)

```php
// OpenAPI-aware queries with automatic validation
$pets = Pet::whereOpenApi('status', 'available')
    ->whereOpenApi('category_id', '>', 1)
    ->orderByOpenApi('name')
    ->limitOpenApi(10)
    ->get();

// Automatic validation from OpenAPI schema
$validator = $pet->validateParameters($request->all());
```

### Migration Strategy

1. **Gradual replacement**:
```php
class Pet extends ApiModel
{
    use HasOpenApiSchema;
    
    // Migration method - use both approaches
    public function scopeAvailableProducts($query, bool $useOpenApi = null)
    {
        $useOpenApi = $useOpenApi ?? config('features.use_openapi_queries', false);
        
        if ($useOpenApi) {
            return $query->whereOpenApi('status', 'available');
        } else {
            return $query->where('status', 'available');
        }
    }
}
```

2. **Feature flag controlled**:
```php
// In a service class
class PetService
{
    public function getAvailablePets()
    {
        $query = Pet::query();
        
        if (config('features.use_openapi_queries')) {
            return $query->whereOpenApi('status', 'available')->get();
        } else {
            return $query->where('status', 'available')->get();
        }
    }
}
```

## Validation Migration

### Before (Manual Validation)

```php
class Pet extends ApiModel
{
    public function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'status' => 'required|in:available,pending,sold',
            'category_id' => 'integer|exists:categories,id',
        ];
    }
}

// Usage
$validator = Validator::make($data, $pet->rules());
```

### After (OpenAPI Validation)

```php
class Pet extends ApiModel
{
    use HasOpenApiSchema;
    
    // Validation rules auto-generated from OpenAPI schema
    // No manual rules() method needed
}

// Usage
$validator = $pet->validateParameters($data, 'create');
```

### Migration with Fallback

```php
class Pet extends ApiModel
{
    use HasOpenApiSchema;
    
    protected bool $useOpenApiValidation = true;
    
    public function validateParameters(array $data, string $operation = 'create'): \Illuminate\Validation\Validator
    {
        if ($this->useOpenApiValidation) {
            try {
                return parent::validateParameters($data, $operation);
            } catch (\Exception $e) {
                \Log::warning('OpenAPI validation failed, falling back to manual validation', [
                    'model' => static::class,
                    'operation' => $operation,
                    'error' => $e->getMessage()
                ]);
                
                return $this->validateParametersManually($data, $operation);
            }
        }
        
        return $this->validateParametersManually($data, $operation);
    }
    
    private function validateParametersManually(array $data, string $operation): \Illuminate\Validation\Validator
    {
        $rules = [
            'name' => 'required|string|max:255',
            'status' => 'required|in:available,pending,sold',
            'category_id' => 'integer|exists:categories,id',
        ];
        
        return validator($data, $rules);
    }
}
```

## Testing Migration

### Create Migration Tests

```php
<?php

namespace Tests\Feature\Migration;

use Tests\TestCase;
use App\Models\Api\Pet;

class OpenApiMigrationTest extends TestCase
{
    /** @test */
    public function openapi_model_has_same_fillable_as_manual()
    {
        $manualFillable = ['name', 'status', 'category_id']; // Known manual configuration
        $openApiFillable = (new Pet())->getFillable();
        
        $this->assertEquals(sort($manualFillable), sort($openApiFillable));
    }
    
    /** @test */
    public function openapi_validation_accepts_valid_data()
    {
        $pet = new Pet();
        $validData = [
            'name' => 'Test Pet',
            'status' => 'available',
            'category_id' => 1,
        ];
        
        $validator = $pet->validateParameters($validData);
        $this->assertTrue($validator->passes());
    }
    
    /** @test */
    public function openapi_validation_rejects_invalid_data()
    {
        $pet = new Pet();
        $invalidData = [
            'name' => '', // Required field empty
            'status' => 'invalid_status', // Invalid enum value
            'category_id' => 'not_a_number', // Invalid type
        ];
        
        $validator = $pet->validateParameters($invalidData);
        $this->assertTrue($validator->fails());
    }
    
    /** @test */
    public function openapi_queries_work_same_as_manual()
    {
        // Mock API responses
        $this->mockApiResponse('/pets?status=available', [
            ['id' => 1, 'name' => 'Pet 1', 'status' => 'available'],
            ['id' => 2, 'name' => 'Pet 2', 'status' => 'available'],
        ]);
        
        $manualQuery = Pet::where('status', 'available')->get();
        $openApiQuery = Pet::whereOpenApi('status', 'available')->get();
        
        $this->assertEquals($manualQuery->count(), $openApiQuery->count());
    }
}
```

### Performance Comparison Tests

```php
<?php

namespace Tests\Performance;

use Tests\TestCase;
use App\Models\Api\Pet;

class MigrationPerformanceTest extends TestCase
{
    /** @test */
    public function openapi_performance_is_acceptable()
    {
        $iterations = 100;
        
        // Test manual validation performance
        $manualStart = microtime(true);
        for ($i = 0; $i < $iterations; $i++) {
            $validator = Validator::make(['name' => 'Test'], ['name' => 'required|string']);
            $validator->passes();
        }
        $manualTime = microtime(true) - $manualStart;
        
        // Test OpenAPI validation performance
        $pet = new Pet();
        $openApiStart = microtime(true);
        for ($i = 0; $i < $iterations; $i++) {
            $validator = $pet->validateParameters(['name' => 'Test']);
            $validator->passes();
        }
        $openApiTime = microtime(true) - $openApiStart;
        
        // OpenAPI should not be more than 3x slower
        $this->assertLessThan($manualTime * 3, $openApiTime, 
            "OpenAPI validation too slow: {$openApiTime}s vs manual {$manualTime}s");
    }
}
```

## Common Migration Issues

### Issue 1: Schema Not Found

**Problem**: OpenAPI schema URL returns 404 or is inaccessible.

**Solution**:
```php
// Add fallback configuration
'schemas' => [
    'primary' => [
        'source' => env('API_CLIENT_PRIMARY_SCHEMA'),
        'fallback_source' => storage_path('api-schemas/fallback-schema.json'),
        'base_url' => env('API_CLIENT_PRIMARY_BASE_URL'),
    ],
],
```

### Issue 2: Field Name Mismatches

**Problem**: OpenAPI schema uses different field names than your manual configuration.

**Solution**:
```php
class Pet extends ApiModel
{
    use HasOpenApiSchema;
    
    // Map OpenAPI fields to your model fields
    protected array $openApiFieldMapping = [
        'petName' => 'name', // OpenAPI uses 'petName', model uses 'name'
        'categoryId' => 'category_id',
    ];
    
    protected function transformParametersForApi(array $parameters): array
    {
        foreach ($this->openApiFieldMapping as $apiField => $modelField) {
            if (isset($parameters[$modelField])) {
                $parameters[$apiField] = $parameters[$modelField];
                unset($parameters[$modelField]);
            }
        }
        
        return parent::transformParametersForApi($parameters);
    }
}
```

### Issue 3: Validation Too Strict

**Problem**: OpenAPI validation is stricter than your current manual validation.

**Solution**:
```php
// Use lenient validation during migration
'validation' => [
    'strictness' => 'lenient',
    'log_validation_warnings' => true,
],

// Or override specific validations
class Pet extends ApiModel
{
    public function getValidationRules(string $operation = 'create'): array
    {
        $rules = parent::getValidationRules($operation);
        
        // Relax specific rules during migration
        if (isset($rules['email'])) {
            $rules['email'] = str_replace('required|', '', $rules['email']);
        }
        
        return $rules;
    }
}
```

### Issue 4: Performance Issues

**Problem**: OpenAPI parsing is slow on every request.

**Solution**:
```php
// Enable aggressive caching
'caching' => [
    'schema_cache' => [
        'enabled' => true,
        'ttl' => 86400, // 24 hours
        'store' => 'redis',
    ],
    'validation_cache' => [
        'enabled' => true,
        'ttl' => 3600,
    ],
],
```

## Rollback Strategy

### Automated Rollback

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class RollbackOpenApiMigration extends Command
{
    protected $signature = 'migration:rollback-openapi {model?}';
    protected $description = 'Rollback OpenAPI migration';

    public function handle()
    {
        $modelName = $this->argument('model');
        
        if ($modelName) {
            $this->rollbackModel($modelName);
        } else {
            $this->rollbackAllModels();
        }
    }

    private function rollbackModel(string $modelName)
    {
        $modelClass = "App\\Models\\Api\\{$modelName}";
        $reflection = new \ReflectionClass($modelClass);
        $currentFile = $reflection->getFileName();
        
        // Find backup file
        $backupFiles = glob($currentFile . '.backup.*');
        if (empty($backupFiles)) {
            $this->error("No backup found for {$modelClass}");
            return;
        }
        
        // Use most recent backup
        $latestBackup = max($backupFiles);
        
        if ($this->confirm("Rollback {$modelClass} from {$latestBackup}?")) {
            copy($latestBackup, $currentFile);
            $this->info("✓ Rolled back {$modelClass}");
        }
    }
}
```

### Feature Flag Rollback

```php
// In config/features.php
return [
    'use_openapi_models' => env('FEATURE_OPENAPI_MODELS', false),
    'use_openapi_validation' => env('FEATURE_OPENAPI_VALIDATION', false),
    'use_openapi_queries' => env('FEATURE_OPENAPI_QUERIES', false),
];

// Quick rollback via environment
FEATURE_OPENAPI_MODELS=false
FEATURE_OPENAPI_VALIDATION=false
FEATURE_OPENAPI_QUERIES=false
```

### Database Rollback

If you've migrated data structures:

```php
// Create rollback migration
php artisan make:migration rollback_openapi_changes

// In the migration
public function up()
{
    // Restore original table structures if needed
}

public function down()
{
    // Re-apply OpenAPI changes
}
```

## Post-Migration Checklist

- [ ] All models successfully migrated
- [ ] Tests passing with OpenAPI validation
- [ ] Performance benchmarks acceptable
- [ ] Error monitoring shows no increase in errors
- [ ] API documentation stays in sync
- [ ] Team trained on new OpenAPI features
- [ ] Backup strategy in place
- [ ] Monitoring and alerting configured
- [ ] Legacy fallback code removed (after stabilization period)

This migration guide provides a comprehensive approach to transitioning from manual to OpenAPI-driven configuration while minimizing risk and maintaining system stability.
