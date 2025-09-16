<?php

namespace MTechStack\LaravelApiModelClient\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use MTechStack\LaravelApiModelClient\OpenApi\OpenApiSchemaParser;
use MTechStack\LaravelApiModelClient\Console\Generators\ModelGenerator;
use MTechStack\LaravelApiModelClient\Console\Generators\FactoryGenerator;
use MTechStack\LaravelApiModelClient\Console\Generators\SchemaDefinitionGenerator;

class GenerateModelsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'api-client:generate-models 
                            {schema-file : Path to the OpenAPI schema file}
                            {--output-dir= : Output directory for generated models (default: app/Models/Api)}
                            {--namespace= : Namespace for generated models (default: App\\Models\\Api)}
                            {--force : Overwrite existing models}
                            {--update : Update existing models instead of overwriting}
                            {--factories : Generate factory classes}
                            {--schemas : Generate schema definition files}
                            {--prefix= : Prefix for model class names}
                            {--suffix= : Suffix for model class names (default: empty)}
                            {--dry-run : Show what would be generated without creating files}';

    /**
     * The console command description.
     */
    protected $description = 'Generate ApiModel classes from OpenAPI schema definitions';

    /**
     * OpenAPI schema parser instance
     */
    protected OpenApiSchemaParser $parser;

    /**
     * Model generator instance
     */
    protected ModelGenerator $modelGenerator;

    /**
     * Factory generator instance
     */
    protected ?FactoryGenerator $factoryGenerator = null;

    /**
     * Schema definition generator instance
     */
    protected ?SchemaDefinitionGenerator $schemaGenerator = null;

    /**
     * Generated models tracking
     */
    protected array $generatedModels = [];

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();
        
        $this->parser = new OpenApiSchemaParser();
        $this->modelGenerator = new ModelGenerator();
        
        if (class_exists(FactoryGenerator::class)) {
            $this->factoryGenerator = new FactoryGenerator();
        }
        
        if (class_exists(SchemaDefinitionGenerator::class)) {
            $this->schemaGenerator = new SchemaDefinitionGenerator();
        }
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $schemaFile = $this->argument('schema-file');
        
        // Validate schema file
        if (!File::exists($schemaFile)) {
            $this->error("Schema file not found: {$schemaFile}");
            return self::FAILURE;
        }

        $this->info("🚀 Generating ApiModel classes from OpenAPI schema...");
        $this->newLine();

        try {
            // Parse OpenAPI schema
            $this->info("📋 Parsing OpenAPI schema: {$schemaFile}");
            $schemaData = $this->parser->parse($schemaFile);
            
            // Get configuration
            $config = $this->getConfiguration();
            
            // Show dry run information
            if ($config['dry_run']) {
                $this->showDryRun($schemaData, $config);
                return self::SUCCESS;
            }
            
            // Create output directory
            $this->createOutputDirectory($config['output_dir']);
            
            // Generate models
            $this->generateModels($schemaData, $config);
            
            // Generate factories if requested
            if ($config['generate_factories']) {
                $this->generateFactories($config);
            }
            
            // Generate schema definitions if requested
            if ($config['generate_schemas']) {
                $this->generateSchemaDefinitions($config);
            }
            
            $this->displaySummary();
            
            return self::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error("❌ Error generating models: " . $e->getMessage());
            
            if ($this->output->isVerbose()) {
                $this->error($e->getTraceAsString());
            }
            
            return self::FAILURE;
        }
    }

    /**
     * Get command configuration
     */
    protected function getConfiguration(): array
    {
        return [
            'output_dir' => $this->option('output-dir') ?: app_path('Models/Api'),
            'namespace' => $this->option('namespace') ?: 'App\\Models\\Api',
            'force' => $this->option('force'),
            'update' => $this->option('update'),
            'generate_factories' => $this->option('factories'),
            'generate_schemas' => $this->option('schemas'),
            'prefix' => $this->option('prefix') ?: '',
            'suffix' => $this->option('suffix') ?: '',
            'dry_run' => $this->option('dry-run'),
        ];
    }

    /**
     * Show dry run information
     */
    protected function showDryRun(array $schemaData, array $config): void
    {
        $this->info("🔍 Dry Run - Models that would be generated:");
        $this->newLine();
        
        $models = $this->parser->extractModels($schemaData);
        
        foreach ($models as $modelName => $modelData) {
            $className = $this->generateClassName($modelName, $config);
            $filePath = $this->getModelFilePath($className, $config);
            
            $status = File::exists($filePath) ? 
                ($config['force'] ? '🔄 OVERWRITE' : ($config['update'] ? '📝 UPDATE' : '⚠️  EXISTS')) : 
                '✨ NEW';
            
            $this->line("  {$status} {$className}");
            $this->line("    📁 {$filePath}");
            
            // Show properties
            if (isset($modelData['properties'])) {
                $this->line("    📋 Properties: " . implode(', ', array_keys($modelData['properties'])));
            }
            
            // Show relationships
            $relationships = $this->detectRelationships($modelData);
            if (!empty($relationships)) {
                $this->line("    🔗 Relationships: " . implode(', ', array_keys($relationships)));
            }
            
            $this->newLine();
        }
        
        if ($config['generate_factories']) {
            $this->info("🏭 Factories would also be generated");
        }
        
        if ($config['generate_schemas']) {
            $this->info("📊 Schema definitions would also be generated");
        }
    }

    /**
     * Create output directory if it doesn't exist
     */
    protected function createOutputDirectory(string $outputDir): void
    {
        if (!File::isDirectory($outputDir)) {
            File::makeDirectory($outputDir, 0755, true);
            $this->info("📁 Created output directory: {$outputDir}");
        }
    }

    /**
     * Generate model classes
     */
    protected function generateModels(array $schemaData, array $config): void
    {
        $models = $this->parser->extractModels($schemaData);
        
        // Debug: Log the extracted models
        if ($this->output->isVerbose()) {
            $this->line("Debug: Extracted " . count($models) . " models: " . implode(', ', array_keys($models)));
        }
        
        $this->info("🏗️  Generating " . count($models) . " model(s)...");
        $this->newLine();
        
        $progressBar = $this->output->createProgressBar(count($models));
        $progressBar->start();
        
        foreach ($models as $modelName => $modelData) {
            $this->generateSingleModel($modelName, $modelData, $schemaData, $config);
            $progressBar->advance();
        }
        
        $progressBar->finish();
        $this->newLine(2);
    }

    /**
     * Generate a single model class
     */
    protected function generateSingleModel(string $modelName, array $modelData, array $schemaData, array $config): void
    {
        $className = $this->generateClassName($modelName, $config);
        $filePath = $this->getModelFilePath($className, $config);
        
        // Check if file exists and handle accordingly
        if (File::exists($filePath) && !$config['force'] && !$config['update']) {
            $this->generatedModels[$className] = [
                'status' => 'skipped',
                'path' => $filePath,
                'reason' => 'File exists (use --force or --update)'
            ];
            return;
        }
        
        try {
            // Generate model content
            $modelContent = $this->modelGenerator->generate(
                $className,
                $modelData,
                $schemaData,
                $config
            );
            
            // Handle update vs overwrite
            if ($config['update'] && File::exists($filePath)) {
                $modelContent = $this->modelGenerator->updateExisting($filePath, $modelContent, $config);
            }
            
            // Write file
            File::put($filePath, $modelContent);
            
            $this->generatedModels[$className] = [
                'status' => File::exists($filePath) && !$config['force'] && !$config['update'] ? 'updated' : 'created',
                'path' => $filePath,
                'properties' => array_keys($modelData['properties'] ?? []),
                'relationships' => array_keys($this->detectRelationships($modelData)),
                'modelData' => $modelData,
                'modelName' => $modelName
            ];
            
        } catch (\Exception $e) {
            $this->generatedModels[$className] = [
                'status' => 'error',
                'path' => $filePath,
                'error' => $e->getMessage(),
                'modelData' => $modelData,
                'modelName' => $modelName
            ];
        }
    }

    /**
     * Generate factory classes
     */
    protected function generateFactories(array $config): void
    {
        if (!$this->factoryGenerator) {
            $this->warn("⚠️  Factory generator not available");
            return;
        }
        
        $this->info("🏭 Generating factory classes...");
        
        foreach ($this->generatedModels as $className => $modelInfo) {
            if ($modelInfo['status'] === 'error') {
                continue;
            }
            
            try {
                // Prepare model data for factory generation
                $modelData = $modelInfo['modelData'] ?? [];
                $modelData['modelName'] = $modelInfo['modelName'] ?? $className;
                
                $factoryContent = $this->factoryGenerator->generate($modelData, $config['namespace'] ?? 'Database\\Factories');
                
                // Generate factory file path
                $factoryName = $modelData['modelName'] . 'Factory';
                $factoryPath = database_path('factories/' . $factoryName . '.php');
                
                // Write factory file
                if (!$config['dry_run']) {
                    File::put($factoryPath, $factoryContent);
                }
                
                $this->line("  ✨ Generated factory: {$factoryPath}");
            } catch (\Exception $e) {
                $this->warn("  ⚠️  Failed to generate factory for {$className}: " . $e->getMessage());
            }
        }
    }

    /**
     * Generate schema definition files
     */
    protected function generateSchemaDefinitions(array $config): void
    {
        if (!$this->schemaGenerator) {
            $this->warn("⚠️  Schema definition generator not available");
            return;
        }
        
        $this->info("📊 Generating schema definition files...");
        
        foreach ($this->generatedModels as $className => $modelInfo) {
            if ($modelInfo['status'] === 'error') {
                continue;
            }
            
            try {
                $schemaPath = $this->schemaGenerator->generate($className, $modelInfo, $config);
                $this->line("  ✨ Generated schema: {$schemaPath}");
            } catch (\Exception $e) {
                $this->warn("  ⚠️  Failed to generate schema for {$className}: " . $e->getMessage());
            }
        }
    }

    /**
     * Display generation summary
     */
    protected function displaySummary(): void
    {
        $this->info("📋 Generation Summary:");
        $this->newLine();
        
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = 0;
        
        foreach ($this->generatedModels as $className => $info) {
            $icon = match($info['status']) {
                'created' => '✨',
                'updated' => '📝',
                'skipped' => '⏭️ ',
                'error' => '❌',
                default => '❓'
            };
            
            $this->line("  {$icon} {$className} - " . ucfirst($info['status']));
            
            if ($info['status'] === 'error') {
                $this->line("    Error: " . $info['error']);
                $errors++;
            } else {
                $this->line("    📁 " . $info['path']);
                
                if (isset($info['properties'])) {
                    $this->line("    📋 Properties: " . count($info['properties']));
                }
                
                if (isset($info['relationships'])) {
                    $this->line("    🔗 Relationships: " . count($info['relationships']));
                }
                
                match($info['status']) {
                    'created' => $created++,
                    'updated' => $updated++,
                    'skipped' => $skipped++,
                    default => null
                };
            }
            
            $this->newLine();
        }
        
        $this->info("🎉 Generation completed!");
        $this->line("  ✨ Created: {$created}");
        $this->line("  📝 Updated: {$updated}");
        $this->line("  ⏭️  Skipped: {$skipped}");
        $this->line("  ❌ Errors: {$errors}");
    }

    /**
     * Generate class name from model name
     */
    protected function generateClassName(string $modelName, array $config): string
    {
        $className = Str::studly($modelName);
        
        if ($config['prefix']) {
            $className = Str::studly($config['prefix']) . $className;
        }
        
        if ($config['suffix']) {
            $className = $className . Str::studly($config['suffix']);
        }
        
        return $className;
    }

    /**
     * Get model file path
     */
    protected function getModelFilePath(string $className, array $config): string
    {
        return $config['output_dir'] . '/' . $className . '.php';
    }

    /**
     * Detect relationships from model data
     */
    protected function detectRelationships(array $modelData): array
    {
        $relationships = [];
        
        if (!isset($modelData['properties'])) {
            return $relationships;
        }
        
        foreach ($modelData['properties'] as $propertyName => $propertyData) {
            // Check for $ref (belongsTo relationship)
            if (isset($propertyData['$ref'])) {
                $relationships[$propertyName] = 'belongsTo';
            }
            
            // Check for array of objects (hasMany relationship)
            if (isset($propertyData['type']) && $propertyData['type'] === 'array') {
                if (isset($propertyData['items']['$ref'])) {
                    $relationships[$propertyName] = 'hasMany';
                }
            }
            
            // Check for nested objects (embedded relationship)
            if (isset($propertyData['type']) && $propertyData['type'] === 'object') {
                if (isset($propertyData['properties'])) {
                    $relationships[$propertyName] = 'embedded';
                }
            }
        }
        
        return $relationships;
    }
}
