<?php

namespace MTechStack\LaravelApiModelClient\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use MTechStack\LaravelApiModelClient\OpenApi\OpenApiSchemaParser;
use MTechStack\LaravelApiModelClient\OpenApi\Exceptions\OpenApiParsingException;

/**
 * Console command for parsing OpenAPI schemas and generating models
 */
class ParseOpenApiCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'api-model:parse-openapi 
                            {source : Path or URL to OpenAPI schema file}
                            {--output-dir= : Directory to output generated models}
                            {--namespace= : Namespace for generated models}
                            {--no-cache : Disable caching}
                            {--generate-models : Generate model classes}
                            {--overwrite : Overwrite existing model files}
                            {--dry-run : Show what would be generated without creating files}';

    /**
     * The console command description.
     */
    protected $description = 'Parse OpenAPI schema and optionally generate API model classes';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $source = $this->argument('source');
        $useCache = !$this->option('no-cache');
        $generateModels = $this->option('generate-models');
        $dryRun = $this->option('dry-run');

        $this->info("Parsing OpenAPI schema from: {$source}");

        try {
            $parser = new OpenApiSchemaParser(config('openapi', []));
            
            $this->info('Loading and parsing schema...');
            $result = $parser->parse($source, $useCache);

            $this->displayParsingResults($result);

            if ($generateModels) {
                $this->generateModels($parser, $dryRun);
            }

            $this->info('✅ OpenAPI schema parsing completed successfully!');
            return Command::SUCCESS;

        } catch (OpenApiParsingException $e) {
            $this->error("❌ Failed to parse OpenAPI schema: {$e->getMessage()}");
            return Command::FAILURE;
        } catch (\Exception $e) {
            $this->error("❌ Unexpected error: {$e->getMessage()}");
            if ($this->getOutput()->isVerbose()) {
                $this->error($e->getTraceAsString());
            }
            return Command::FAILURE;
        }
    }

    /**
     * Display parsing results
     */
    protected function displayParsingResults(array $result): void
    {
        $info = $result['info'];
        $endpoints = $result['endpoints'];
        $schemas = $result['schemas'];
        $modelMappings = $result['model_mappings'];

        $this->newLine();
        $this->info('📋 Parsing Results:');
        $this->line("   Title: {$info['title']}");
        $this->line("   Version: {$info['version']}");
        $this->line("   Description: {$info['description']}");
        
        $this->newLine();
        $this->info('📊 Statistics:');
        $this->line("   Endpoints: " . count($endpoints));
        $this->line("   Schemas: " . count($schemas));
        $this->line("   Model Mappings: " . count($modelMappings));

        // Display endpoints summary
        if (!empty($endpoints)) {
            $this->newLine();
            $this->info('🔗 Endpoints:');
            
            $endpointTable = [];
            foreach ($endpoints as $operationId => $endpoint) {
                $endpointTable[] = [
                    $endpoint['method'],
                    $endpoint['path'],
                    $endpoint['summary'] ?: $operationId,
                    count($endpoint['parameters']),
                ];
            }

            $this->table(
                ['Method', 'Path', 'Summary', 'Parameters'],
                array_slice($endpointTable, 0, 10) // Show first 10
            );

            if (count($endpointTable) > 10) {
                $this->line("   ... and " . (count($endpointTable) - 10) . " more endpoints");
            }
        }

        // Display model mappings
        if (!empty($modelMappings)) {
            $this->newLine();
            $this->info('🏗️  Model Mappings:');
            
            $modelTable = [];
            foreach ($modelMappings as $modelName => $mapping) {
                $modelTable[] = [
                    $modelName,
                    $mapping['base_endpoint'],
                    count($mapping['operations']),
                    count($mapping['attributes']),
                    count($mapping['relationships']),
                ];
            }

            $this->table(
                ['Model', 'Base Endpoint', 'Operations', 'Attributes', 'Relationships'],
                $modelTable
            );
        }
    }

    /**
     * Generate model classes
     */
    protected function generateModels(OpenApiSchemaParser $parser, bool $dryRun): void
    {
        $outputDir = $this->option('output-dir') ?: config('openapi.model_generation.output_directory', app_path('Models'));
        $namespace = $this->option('namespace') ?: config('openapi.model_generation.namespace', 'App\\Models');
        $overwrite = $this->option('overwrite') ?: config('openapi.model_generation.overwrite_existing', false);

        $modelNames = $parser->getModelNames();

        if (empty($modelNames)) {
            $this->warn('No models to generate.');
            return;
        }

        $this->newLine();
        $this->info("🏗️  Generating {count($modelNames)} model classes...");

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No files will be created');
        }

        foreach ($modelNames as $modelName) {
            $this->generateSingleModel($parser, $modelName, $outputDir, $namespace, $overwrite, $dryRun);
        }

        if (!$dryRun) {
            $this->info("✅ Generated {count($modelNames)} model classes in {$outputDir}");
        }
    }

    /**
     * Generate a single model class
     */
    protected function generateSingleModel(
        OpenApiSchemaParser $parser,
        string $modelName,
        string $outputDir,
        string $namespace,
        bool $overwrite,
        bool $dryRun
    ): void {
        $filePath = $outputDir . '/' . $modelName . '.php';

        if (!$overwrite && File::exists($filePath)) {
            $this->warn("   ⚠️  Skipping {$modelName} (file exists, use --overwrite to replace)");
            return;
        }

        try {
            $modelCode = $parser->generateModelClass($modelName);
            
            // Update namespace in generated code
            $modelCode = str_replace('namespace App\\Models;', "namespace {$namespace};", $modelCode);

            if ($dryRun) {
                $this->line("   📄 Would generate: {$filePath}");
                if ($this->getOutput()->isVerbose()) {
                    $this->line("      Attributes: " . count($parser->getModelAttributes($modelName)));
                    $this->line("      Relationships: " . count($parser->getModelRelationships($modelName)));
                    $this->line("      Operations: " . count($parser->getModelOperations($modelName)));
                }
            } else {
                // Ensure directory exists
                File::ensureDirectoryExists($outputDir);
                
                // Write model file
                File::put($filePath, $modelCode);
                
                $this->line("   ✅ Generated: {$modelName}");
            }

        } catch (\Exception $e) {
            $this->error("   ❌ Failed to generate {$modelName}: {$e->getMessage()}");
        }
    }
}
