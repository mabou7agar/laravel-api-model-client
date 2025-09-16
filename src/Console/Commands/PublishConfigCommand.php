<?php

namespace MTechStack\LaravelApiModelClient\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Artisan;

/**
 * Artisan command for publishing and setting up OpenAPI configuration
 */
class PublishConfigCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'api-client:publish-config 
                            {--force : Overwrite existing configuration}
                            {--env-template : Also publish environment file template}
                            {--directories : Create required directories}
                            {--examples : Include example configurations}
                            {--all : Publish everything (config, env, directories, examples)}';

    /**
     * The console command description.
     */
    protected $description = 'Publish OpenAPI configuration files and setup directories';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('📦 Publishing OpenAPI Configuration');
        $this->newLine();

        $force = $this->option('force');
        $publishEnv = $this->option('env-template');
        $createDirectories = $this->option('directories');
        $includeExamples = $this->option('examples');
        $publishAll = $this->option('all');

        if ($publishAll) {
            $publishEnv = true;
            $createDirectories = true;
            $includeExamples = true;
        }

        $success = true;

        try {
            // Publish main configuration file
            $success &= $this->publishMainConfig($force);

            // Publish environment template
            if ($publishEnv) {
                $success &= $this->publishEnvironmentTemplate($force);
            }

            // Create required directories
            if ($createDirectories) {
                $success &= $this->createRequiredDirectories();
            }

            // Include example configurations
            if ($includeExamples) {
                $success &= $this->publishExampleConfigurations($force);
            }

            if ($success) {
                $this->displaySuccessMessage();
                return 0;
            } else {
                $this->error('❌ Some operations failed. Check the output above for details.');
                return 1;
            }

        } catch (\Exception $e) {
            $this->error("❌ Publishing failed: {$e->getMessage()}");
            return 1;
        }
    }

    /**
     * Publish the main configuration file
     */
    protected function publishMainConfig(bool $force): bool
    {
        $this->line('📋 Publishing main configuration file...');

        $sourcePath = __DIR__ . '/../../../config/api-client.php';
        $targetPath = config_path('api-client.php');

        if (File::exists($targetPath) && !$force) {
            if (!$this->confirm('Configuration file already exists. Overwrite?')) {
                $this->warn('⚠️  Skipped main configuration (already exists)');
                return true;
            }
        }

        try {
            // Ensure config directory exists
            $configDir = dirname($targetPath);
            if (!File::isDirectory($configDir)) {
                File::makeDirectory($configDir, 0755, true);
            }

            // Copy the configuration file
            if (File::copy($sourcePath, $targetPath)) {
                $this->info("✅ Published: {$targetPath}");
                return true;
            } else {
                $this->error("❌ Failed to copy configuration file");
                return false;
            }

        } catch (\Exception $e) {
            $this->error("❌ Failed to publish main configuration: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Publish environment template
     */
    protected function publishEnvironmentTemplate(bool $force): bool
    {
        $this->line('🌍 Publishing environment template...');

        $targetPath = base_path('.env.api-client.example');

        if (File::exists($targetPath) && !$force) {
            if (!$this->confirm('Environment template already exists. Overwrite?')) {
                $this->warn('⚠️  Skipped environment template (already exists)');
                return true;
            }
        }

        try {
            $envTemplate = $this->generateEnvironmentTemplate();
            
            if (File::put($targetPath, $envTemplate)) {
                $this->info("✅ Published: {$targetPath}");
                $this->line("   📝 Copy relevant variables to your .env file");
                return true;
            } else {
                $this->error("❌ Failed to create environment template");
                return false;
            }

        } catch (\Exception $e) {
            $this->error("❌ Failed to publish environment template: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Create required directories
     */
    protected function createRequiredDirectories(): bool
    {
        $this->line('📁 Creating required directories...');

        $directories = [
            storage_path('api-client'),
            storage_path('api-client/schemas'),
            storage_path('api-client/mocks'),
            storage_path('api-client/cache'),
            app_path('Models/Api'),
            app_path('Models/Api/Primary'),
            app_path('Models/Api/Secondary'),
            database_path('factories'),
        ];

        $created = 0;
        $errors = 0;

        foreach ($directories as $directory) {
            try {
                if (!File::isDirectory($directory)) {
                    if (File::makeDirectory($directory, 0755, true)) {
                        $this->line("  ✅ Created: {$directory}");
                        $created++;
                    } else {
                        $this->line("  ❌ Failed: {$directory}");
                        $errors++;
                    }
                } else {
                    $this->line("  ℹ️  Exists: {$directory}");
                }
            } catch (\Exception $e) {
                $this->line("  ❌ Error creating {$directory}: {$e->getMessage()}");
                $errors++;
            }
        }

        if ($errors === 0) {
            $this->info("✅ Directory setup completed ({$created} created)");
            return true;
        } else {
            $this->warn("⚠️  Directory setup completed with {$errors} errors");
            return false;
        }
    }

    /**
     * Publish example configurations
     */
    protected function publishExampleConfigurations(bool $force): bool
    {
        $this->line('📚 Publishing example configurations...');

        $examples = [
            'petstore-openapi.json' => $this->generatePetStoreExample(),
            'api-client-usage.php' => $this->generateUsageExample(),
            'schema-migration.md' => $this->generateMigrationGuide(),
        ];

        $examplesDir = base_path('examples/api-client');
        
        // Create examples directory
        if (!File::isDirectory($examplesDir)) {
            File::makeDirectory($examplesDir, 0755, true);
        }

        $created = 0;
        $errors = 0;

        foreach ($examples as $filename => $content) {
            $filePath = $examplesDir . '/' . $filename;
            
            try {
                if (File::exists($filePath) && !$force) {
                    if (!$this->confirm("Example file '{$filename}' already exists. Overwrite?")) {
                        $this->line("  ⚠️  Skipped: {$filename}");
                        continue;
                    }
                }

                if (File::put($filePath, $content)) {
                    $this->line("  ✅ Created: {$filename}");
                    $created++;
                } else {
                    $this->line("  ❌ Failed: {$filename}");
                    $errors++;
                }

            } catch (\Exception $e) {
                $this->line("  ❌ Error creating {$filename}: {$e->getMessage()}");
                $errors++;
            }
        }

        if ($errors === 0) {
            $this->info("✅ Example configurations published ({$created} files)");
            return true;
        } else {
            $this->warn("⚠️  Example configurations published with {$errors} errors");
            return false;
        }
    }

    /**
     * Generate environment template
     */
    protected function generateEnvironmentTemplate(): string
    {
        return <<<'ENV'
# Laravel API Model Client Configuration
# Copy the relevant variables to your .env file and configure as needed

# Default schema to use
API_CLIENT_DEFAULT_SCHEMA=primary

# Primary API Configuration
API_CLIENT_PRIMARY_SCHEMA=https://petstore3.swagger.io/api/v3/openapi.json
API_CLIENT_PRIMARY_BASE_URL=https://petstore3.swagger.io/api/v3
API_CLIENT_PRIMARY_VERSION=v3
API_CLIENT_PRIMARY_ENABLED=true
API_CLIENT_PRIMARY_TIMEOUT=30
API_CLIENT_PRIMARY_RETRY=3

# Primary API Authentication
API_CLIENT_PRIMARY_AUTH_TYPE=api_key
API_CLIENT_PRIMARY_TOKEN=
API_CLIENT_PRIMARY_API_KEY=your-api-key-here
API_CLIENT_PRIMARY_API_KEY_HEADER=X-API-Key
API_CLIENT_PRIMARY_USERNAME=
API_CLIENT_PRIMARY_PASSWORD=

# Primary API Model Generation
API_CLIENT_PRIMARY_AUTO_GENERATE=false

# Primary API Validation
API_CLIENT_PRIMARY_VALIDATION_STRICTNESS=strict

# Primary API Caching
API_CLIENT_PRIMARY_CACHE_TTL=3600
API_CLIENT_PRIMARY_CACHE_STORE=default

# Secondary API Configuration (optional)
API_CLIENT_SECONDARY_SCHEMA=
API_CLIENT_SECONDARY_BASE_URL=https://api2.example.com
API_CLIENT_SECONDARY_VERSION=v1
API_CLIENT_SECONDARY_ENABLED=false
API_CLIENT_SECONDARY_TIMEOUT=30
API_CLIENT_SECONDARY_RETRY=3
API_CLIENT_SECONDARY_AUTH_TYPE=bearer
API_CLIENT_SECONDARY_TOKEN=

# Testing API Configuration
API_CLIENT_TESTING_SCHEMA=
API_CLIENT_TESTING_BASE_URL=https://test-api.example.com
API_CLIENT_TESTING_VERSION=v1
API_CLIENT_TESTING_ENABLED=false
API_CLIENT_TESTING_TOKEN=test-token

# Global Configuration
API_CLIENT_VERSIONING_ENABLED=true
API_CLIENT_AUTO_MIGRATE=false
API_CLIENT_CACHE_ENABLED=true
API_CLIENT_CACHE_TTL=3600
API_CLIENT_CACHE_STORE=default
API_CLIENT_CACHE_PREFIX=api_client_
API_CLIENT_CACHE_COMPRESSION=false

# Health Checks
API_CLIENT_HEALTH_CHECKS_ENABLED=true
API_CLIENT_HEALTH_CHECK_SCHEDULE="0 */6 * * *"
API_CLIENT_HEALTH_CHECK_TIMEOUT=30
API_CLIENT_HEALTH_WEBHOOK_URL=

# Logging
API_CLIENT_LOGGING_ENABLED=true
API_CLIENT_LOG_LEVEL=info
API_CLIENT_LOG_CHANNEL=default
API_CLIENT_LOG_REQUESTS=false
API_CLIENT_LOG_RESPONSES=false
API_CLIENT_LOG_SCHEMA_PARSING=true
API_CLIENT_LOG_MODEL_GENERATION=true
API_CLIENT_LOG_CACHE_OPERATIONS=false
API_CLIENT_LOG_VALIDATION_ERRORS=true

# Security
API_CLIENT_VERIFY_SSL=true
API_CLIENT_SSL_CERT_PATH=
API_CLIENT_SSL_KEY_PATH=
API_CLIENT_ALLOWED_HOSTS=
API_CLIENT_RATE_LIMITING_ENABLED=true
API_CLIENT_RATE_LIMIT_RPM=60
API_CLIENT_RATE_LIMIT_BURST=10
API_CLIENT_ENCRYPTION_ENABLED=false
API_CLIENT_ENCRYPTION_KEY=

# Performance
API_CLIENT_CONNECTION_POOLING=true
API_CLIENT_KEEP_ALIVE=true
API_CLIENT_COMPRESSION=true
API_CLIENT_PARALLEL_REQUESTS=5
API_CLIENT_MEMORY_LIMIT=256M
API_CLIENT_MAX_EXECUTION_TIME=300
API_CLIENT_LAZY_LOADING=true

# Development
API_CLIENT_MOCK_RESPONSES=false
API_CLIENT_GENERATE_MOCK_DATA=false
API_CLIENT_VALIDATE_EXAMPLES=true
API_CLIENT_DEBUG_MODE=false
API_CLIENT_PROFILING=false
API_CLIENT_TEST_COVERAGE=false
ENV;
    }

    /**
     * Generate PetStore example OpenAPI schema
     */
    protected function generatePetStoreExample(): string
    {
        return json_encode([
            'openapi' => '3.0.3',
            'info' => [
                'title' => 'Swagger Petstore - OpenAPI 3.0',
                'description' => 'This is a sample Pet Store Server',
                'version' => '1.0.11',
            ],
            'servers' => [
                [
                    'url' => 'https://petstore3.swagger.io/api/v3',
                ],
            ],
            'paths' => [
                '/pet' => [
                    'post' => [
                        'tags' => ['pet'],
                        'summary' => 'Add a new pet to the store',
                        'operationId' => 'addPet',
                        'requestBody' => [
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        '$ref' => '#/components/schemas/Pet',
                                    ],
                                ],
                            ],
                            'required' => true,
                        ],
                        'responses' => [
                            '200' => [
                                'description' => 'Successful operation',
                                'content' => [
                                    'application/json' => [
                                        'schema' => [
                                            '$ref' => '#/components/schemas/Pet',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'components' => [
                'schemas' => [
                    'Pet' => [
                        'required' => ['name', 'photoUrls'],
                        'type' => 'object',
                        'properties' => [
                            'id' => [
                                'type' => 'integer',
                                'format' => 'int64',
                                'example' => 10,
                            ],
                            'name' => [
                                'type' => 'string',
                                'example' => 'doggie',
                            ],
                            'category' => [
                                '$ref' => '#/components/schemas/Category',
                            ],
                            'photoUrls' => [
                                'type' => 'array',
                                'items' => [
                                    'type' => 'string',
                                ],
                            ],
                            'tags' => [
                                'type' => 'array',
                                'items' => [
                                    '$ref' => '#/components/schemas/Tag',
                                ],
                            ],
                            'status' => [
                                'type' => 'string',
                                'description' => 'pet status in the store',
                                'enum' => ['available', 'pending', 'sold'],
                            ],
                        ],
                    ],
                    'Category' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => [
                                'type' => 'integer',
                                'format' => 'int64',
                                'example' => 1,
                            ],
                            'name' => [
                                'type' => 'string',
                                'example' => 'Dogs',
                            ],
                        ],
                    ],
                    'Tag' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => [
                                'type' => 'integer',
                                'format' => 'int64',
                            ],
                            'name' => [
                                'type' => 'string',
                            ],
                        ],
                    ],
                ],
            ],
        ], JSON_PRETTY_PRINT);
    }

    /**
     * Generate usage example
     */
    protected function generateUsageExample(): string
    {
        return <<<'PHP'
<?php

/**
 * Laravel API Model Client - Usage Examples
 * 
 * This file demonstrates how to use the Laravel API Model Client
 * with OpenAPI configuration system.
 */

use App\Models\Api\Pet;
use App\Models\Api\Category;
use MTechStack\LaravelApiModelClient\Configuration\ConfigurationValidator;
use MTechStack\LaravelApiModelClient\Configuration\SchemaVersionManager;

// Example 1: Basic model usage with OpenAPI validation
$pet = new Pet([
    'name' => 'Buddy',
    'status' => 'available',
    'category' => ['id' => 1, 'name' => 'Dogs'],
]);

// Validate using OpenAPI schema rules
$validator = $pet->validateParameters($pet->toArray(), 'create');
if ($validator->fails()) {
    echo "Validation failed: " . implode(', ', $validator->errors()->all());
}

// Example 2: Using multiple schemas
// Configure different schemas for different environments
$primaryPet = Pet::setSchema('primary')->find(1);
$testingPet = Pet::setSchema('testing')->find(1);

// Example 3: Configuration validation
$configValidator = new ConfigurationValidator();
$result = $configValidator->validate();

if (!$result['valid']) {
    echo "Configuration errors:\n";
    foreach ($result['errors'] as $error) {
        echo "- {$error}\n";
    }
}

// Example 4: Health checks
$healthResults = $configValidator->performHealthChecks();
echo "Overall health: " . $healthResults['overall_status'] . "\n";

// Example 5: Schema versioning
$versionManager = new SchemaVersionManager();

// Create a new version
$version = $versionManager->createVersion('primary', file_get_contents('schema.json'));

// List all versions
$versions = $versionManager->listVersions('primary');
foreach ($versions as $version) {
    echo "Version: {$version['version']} - Created: {$version['created_at']}\n";
}

// Compare versions
$comparison = $versionManager->compareVersions('primary', 'v1.0.0', 'v1.1.0');
if (!$comparison['identical']) {
    echo "Schemas are different\n";
}

// Example 6: Cache management
// Warm cache for all schemas
Artisan::call('api-client:cache', ['action' => 'warm']);

// Clear cache for specific schema
Artisan::call('api-client:cache', ['action' => 'clear', 'schema' => 'primary']);

// Check cache status
Artisan::call('api-client:cache', ['action' => 'status', '--stats' => true]);

// Example 7: Schema validation
// Validate all schemas and perform health checks
Artisan::call('api-client:validate-schema', ['--health-check' => true, '--detailed' => true]);

// Validate specific schema
Artisan::call('api-client:validate-schema', ['schema' => 'primary', '--format' => 'json']);

// Example 8: Model generation with configuration
// Generate models from OpenAPI schema
Artisan::call('api-client:generate-models', [
    'schema_path' => 'https://petstore3.swagger.io/api/v3/openapi.json',
    '--factories' => true,
    '--schemas' => true,
    '--namespace' => 'App\\Models\\Api\\PetStore',
    '--output-dir' => app_path('Models/Api/PetStore'),
]);

// Example 9: Environment-specific configurations
if (app()->environment('production')) {
    // Use production schema with strict validation
    config(['api-client.default_schema' => 'primary']);
    config(['api-client.schemas.primary.validation.strictness' => 'strict']);
} elseif (app()->environment('testing')) {
    // Use testing schema with lenient validation
    config(['api-client.default_schema' => 'testing']);
    config(['api-client.schemas.testing.validation.strictness' => 'lenient']);
}

// Example 10: Custom validation rules
$customRules = Pet::getValidationRules();
$customRules['name'][] = 'min:2';
$customRules['weight'] = ['required', 'numeric', 'min:0.1'];

$validator = Validator::make($pet->toArray(), $customRules);
if ($validator->fails()) {
    echo "Custom validation failed\n";
}
PHP;
    }

    /**
     * Generate migration guide
     */
    protected function generateMigrationGuide(): string
    {
        return <<<'MD'
# OpenAPI Schema Migration Guide

This guide explains how to manage schema versions and migrations using the Laravel API Model Client.

## Overview

The schema versioning system allows you to:
- Track changes to your OpenAPI schemas over time
- Migrate between different schema versions
- Compare schema versions to understand changes
- Backup and restore schemas

## Basic Usage

### Creating a Version

```bash
# Create a new version of a schema
php artisan api-client:schema-version create primary /path/to/schema.json

# Create with custom version identifier
php artisan api-client:schema-version create primary /path/to/schema.json --version=v2.0.0
```

### Listing Versions

```bash
# List all versions of a schema
php artisan api-client:schema-version list primary

# Show detailed information
php artisan api-client:schema-version list primary --detailed
```

### Comparing Versions

```bash
# Compare two versions
php artisan api-client:schema-version compare primary v1.0.0 v2.0.0

# Use different comparison strategies
php artisan api-client:schema-version compare primary v1.0.0 v2.0.0 --strategy=content
```

### Migration Strategies

#### 1. Backup and Replace
```bash
php artisan api-client:schema-version migrate primary v1.0.0 v2.0.0 --strategy=backup_and_replace
```
- Creates a backup of the current version
- Replaces current schema with the target version
- Safest option for production

#### 2. Merge
```bash
php artisan api-client:schema-version migrate primary v1.0.0 v2.0.0 --strategy=merge
```
- Attempts to merge schemas
- Useful for non-conflicting changes
- Requires manual review

#### 3. Manual
```bash
php artisan api-client:schema-version migrate primary v1.0.0 v2.0.0 --strategy=manual
```
- Provides migration instructions
- No automatic changes
- Best for complex migrations

## Configuration

### Versioning Settings

```php
// config/api-client.php
'versioning' => [
    'enabled' => true,
    'storage_path' => storage_path('api-client/schemas'),
    'backup_enabled' => true,
    'backup_retention_days' => 30,
    'auto_migrate' => false,
    'migration_strategy' => 'backup_and_replace',
    'version_format' => 'Y-m-d_H-i-s',
    'compare_strategy' => 'hash',
],
```

### Environment Variables

```env
API_CLIENT_VERSIONING_ENABLED=true
API_CLIENT_AUTO_MIGRATE=false
```

## Best Practices

### 1. Version Naming
- Use semantic versioning (v1.0.0, v1.1.0, v2.0.0)
- Include meaningful descriptions
- Tag breaking changes clearly

### 2. Testing Migrations
- Always test migrations in development first
- Use the `--dry-run` option when available
- Validate schemas after migration

### 3. Backup Strategy
- Enable automatic backups
- Set appropriate retention periods
- Test restore procedures

### 4. Change Management
- Document schema changes
- Review breaking changes carefully
- Coordinate with API consumers

## Troubleshooting

### Common Issues

1. **Migration Fails**
   ```bash
   # Check schema validity
   php artisan api-client:validate-schema primary
   
   # Compare versions to understand changes
   php artisan api-client:schema-version compare primary current target
   ```

2. **Backup Restoration**
   ```bash
   # List available backups
   php artisan api-client:schema-version list primary --backups
   
   # Restore from backup
   php artisan api-client:schema-version restore primary backup_version
   ```

3. **Storage Issues**
   ```bash
   # Check storage permissions
   ls -la storage/api-client/schemas/
   
   # Recreate directories
   php artisan api-client:publish-config --directories
   ```

## Advanced Usage

### Programmatic Access

```php
use MTechStack\LaravelApiModelClient\Configuration\SchemaVersionManager;

$manager = new SchemaVersionManager();

// Create version
$version = $manager->createVersion('primary', $schemaContent);

// Get latest version
$latest = $manager->getLatestVersion('primary');

// Compare versions
$comparison = $manager->compareVersions('primary', 'v1.0.0', 'v2.0.0');

// Migrate
$result = $manager->migrate('primary', 'v1.0.0', 'v2.0.0');
```

### Custom Migration Logic

```php
// Extend the SchemaVersionManager for custom migration logic
class CustomSchemaVersionManager extends SchemaVersionManager
{
    protected function migrateCustom(string $schemaName, string $fromVersion, string $toVersion): array
    {
        // Your custom migration logic here
        return [
            'success' => true,
            'strategy' => 'custom',
            'changes' => $this->analyzeChanges($fromVersion, $toVersion),
        ];
    }
}
```

## Integration with CI/CD

### Automated Schema Updates

```yaml
# .github/workflows/schema-update.yml
name: Update API Schema
on:
  schedule:
    - cron: '0 2 * * *'  # Daily at 2 AM

jobs:
  update-schema:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      - name: Update Schema
        run: |
          php artisan api-client:schema-version create primary $SCHEMA_URL
          php artisan api-client:validate-schema --health-check
```

### Pre-deployment Validation

```bash
# In your deployment script
php artisan api-client:validate-schema --health-check
if [ $? -ne 0 ]; then
    echo "Schema validation failed, aborting deployment"
    exit 1
fi
```
MD;
    }

    /**
     * Display success message with next steps
     */
    protected function displaySuccessMessage(): void
    {
        $this->newLine();
        $this->info('🎉 OpenAPI Configuration Published Successfully!');
        $this->newLine();
        
        $this->line('📋 Next Steps:');
        $this->line('  1. Review and customize config/api-client.php');
        $this->line('  2. Copy relevant variables from .env.api-client.example to your .env file');
        $this->line('  3. Configure your OpenAPI schema sources');
        $this->line('  4. Run validation: php artisan api-client:validate-schema');
        $this->line('  5. Generate models: php artisan api-client:generate-models [schema-path]');
        $this->newLine();
        
        $this->line('📚 Available Commands:');
        $this->line('  • php artisan api-client:validate-schema     - Validate configuration and schemas');
        $this->line('  • php artisan api-client:cache               - Manage schema cache');
        $this->line('  • php artisan api-client:generate-models     - Generate models from schemas');
        $this->newLine();
        
        $this->line('📖 Documentation:');
        $this->line('  • Configuration: config/api-client.php');
        $this->line('  • Examples: examples/api-client/');
        $this->line('  • Migration Guide: examples/api-client/schema-migration.md');
    }
}
