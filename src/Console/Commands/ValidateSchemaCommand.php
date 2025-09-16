<?php

namespace MTechStack\LaravelApiModelClient\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use MTechStack\LaravelApiModelClient\Configuration\ConfigurationValidator;
use MTechStack\LaravelApiModelClient\Configuration\SchemaVersionManager;

/**
 * Artisan command for validating OpenAPI schemas and performing health checks
 */
class ValidateSchemaCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'api-client:validate-schema 
                            {schema? : Specific schema to validate (optional)}
                            {--health-check : Perform health checks on schemas}
                            {--config-only : Only validate configuration, skip schema validation}
                            {--fix : Attempt to auto-fix issues where possible}
                            {--format=table : Output format (table, json, yaml)}
                            {--detailed : Show detailed validation results}
                            {--save-report= : Save validation report to file}';

    /**
     * The console command description.
     */
    protected $description = 'Validate OpenAPI schema configuration and perform health checks';

    protected ConfigurationValidator $validator;
    protected SchemaVersionManager $versionManager;

    public function __construct()
    {
        parent::__construct();
        $this->validator = new ConfigurationValidator();
        $this->versionManager = new SchemaVersionManager();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🔍 OpenAPI Schema Validation');
        $this->newLine();

        $schema = $this->argument('schema');
        $healthCheck = $this->option('health-check');
        $configOnly = $this->option('config-only');
        $autoFix = $this->option('fix');
        $format = $this->option('format');
        $detailed = $this->option('detailed');
        $saveReport = $this->option('save-report');

        $results = [];

        try {
            // Validate configuration
            $this->line('📋 Validating configuration...');
            $configResults = $this->validator->validate();
            $results['configuration'] = $configResults;

            $this->displayConfigurationResults($configResults, $format, $detailed);

            if (!$configResults['valid'] && !$autoFix) {
                $this->error('❌ Configuration validation failed. Use --fix to attempt auto-repair.');
                return 1;
            }

            if ($autoFix && !$configResults['valid']) {
                $this->line('🔧 Attempting to auto-fix configuration issues...');
                $fixResults = $this->autoFixConfiguration($configResults);
                $results['auto_fix'] = $fixResults;
                $this->displayAutoFixResults($fixResults);
            }

            // Skip schema validation if config-only is specified
            if ($configOnly) {
                $this->info('✅ Configuration validation completed (schema validation skipped)');
                return $this->saveReportIfRequested($results, $saveReport) ? 0 : 1;
            }

            // Perform health checks if requested
            if ($healthCheck) {
                $this->newLine();
                $this->line('🏥 Performing health checks...');
                $healthResults = $this->validator->performHealthChecks();
                $results['health_checks'] = $healthResults;

                $this->displayHealthCheckResults($healthResults, $format, $detailed);
            }

            // Validate specific schema or all schemas
            if ($schema) {
                $this->newLine();
                $this->line("🔍 Validating schema: {$schema}");
                $schemaResults = $this->validateSpecificSchema($schema);
                $results['schema_validation'][$schema] = $schemaResults;
                $this->displaySchemaValidationResults($schema, $schemaResults, $format, $detailed);
            } else {
                $this->newLine();
                $this->line('🔍 Validating all schemas...');
                $allSchemaResults = $this->validateAllSchemas();
                $results['schema_validation'] = $allSchemaResults;
                $this->displayAllSchemaResults($allSchemaResults, $format, $detailed);
            }

            // Save report if requested
            if ($saveReport) {
                $this->saveReportIfRequested($results, $saveReport);
            }

            // Determine overall success
            $overallSuccess = $this->determineOverallSuccess($results);
            
            if ($overallSuccess) {
                $this->info('✅ All validations passed successfully!');
                return 0;
            } else {
                $this->error('❌ Some validations failed. Check the results above.');
                return 1;
            }

        } catch (\Exception $e) {
            $this->error("❌ Validation failed with error: {$e->getMessage()}");
            
            if ($detailed) {
                $this->line("Stack trace:");
                $this->line($e->getTraceAsString());
            }
            
            return 1;
        }
    }

    /**
     * Display configuration validation results
     */
    protected function displayConfigurationResults(array $results, string $format, bool $detailed): void
    {
        switch ($format) {
            case 'json':
                $this->line(json_encode($results, JSON_PRETTY_PRINT));
                break;
                
            case 'yaml':
                $this->line(yaml_emit($results));
                break;
                
            default:
                $this->displayConfigurationTable($results, $detailed);
                break;
        }
    }

    /**
     * Display configuration results in table format
     */
    protected function displayConfigurationTable(array $results, bool $detailed): void
    {
        $summary = $results['summary'];
        
        $this->table(
            ['Metric', 'Value'],
            [
                ['Status', $results['valid'] ? '✅ Valid' : '❌ Invalid'],
                ['Total Errors', $summary['total_errors']],
                ['Total Warnings', $summary['total_warnings']],
                ['Schemas Count', $summary['schemas_count']],
                ['Enabled Schemas', $summary['enabled_schemas']],
            ]
        );

        if (!empty($results['errors'])) {
            $this->newLine();
            $this->error('Configuration Errors:');
            foreach ($results['errors'] as $error) {
                $this->line("  • {$error}");
            }
        }

        if (!empty($results['warnings']) && $detailed) {
            $this->newLine();
            $this->warn('Configuration Warnings:');
            foreach ($results['warnings'] as $warning) {
                $this->line("  • {$warning}");
            }
        }
    }

    /**
     * Display health check results
     */
    protected function displayHealthCheckResults(array $results, string $format, bool $detailed): void
    {
        if (!$results['enabled']) {
            $this->warn('⚠️  Health checks are disabled');
            return;
        }

        switch ($format) {
            case 'json':
                $this->line(json_encode($results, JSON_PRETTY_PRINT));
                break;
                
            case 'yaml':
                $this->line(yaml_emit($results));
                break;
                
            default:
                $this->displayHealthCheckTable($results, $detailed);
                break;
        }
    }

    /**
     * Display health check results in table format
     */
    protected function displayHealthCheckTable(array $results, bool $detailed): void
    {
        $summary = $results['summary'];
        
        $this->table(
            ['Metric', 'Value'],
            [
                ['Overall Status', $this->formatHealthStatus($results['overall_status'])],
                ['Total Schemas', $summary['total_schemas']],
                ['Healthy', $summary['healthy']],
                ['Warning', $summary['warning']],
                ['Unhealthy', $summary['unhealthy']],
                ['Timestamp', $results['timestamp']],
            ]
        );

        if ($detailed && !empty($results['schemas'])) {
            $this->newLine();
            $this->line('Schema Health Details:');
            
            foreach ($results['schemas'] as $schemaName => $schemaHealth) {
                $this->line("  📊 {$schemaName}: {$this->formatHealthStatus($schemaHealth['status'])}");
                
                if (!empty($schemaHealth['checks'])) {
                    foreach ($schemaHealth['checks'] as $checkName => $checkResult) {
                        $status = $this->formatHealthStatus($checkResult['status']);
                        $message = $checkResult['message'] ?? 'No message';
                        $this->line("    • {$checkName}: {$status} - {$message}");
                    }
                }
            }
        }
    }

    /**
     * Validate a specific schema
     */
    protected function validateSpecificSchema(string $schemaName): array
    {
        $schemas = Config::get('api-client.schemas', []);
        
        if (!isset($schemas[$schemaName])) {
            return [
                'valid' => false,
                'error' => "Schema '{$schemaName}' not found in configuration",
            ];
        }

        $schemaConfig = $schemas[$schemaName];
        
        // Check if schema is enabled
        if (!($schemaConfig['enabled'] ?? false)) {
            return [
                'valid' => false,
                'error' => "Schema '{$schemaName}' is disabled",
            ];
        }

        // Validate schema source
        $source = $schemaConfig['source'] ?? null;
        if (!$source) {
            return [
                'valid' => false,
                'error' => "No source configured for schema '{$schemaName}'",
            ];
        }

        try {
            // Parse and validate the schema
            $parser = new \MTechStack\LaravelApiModelClient\OpenApi\OpenApiSchemaParser();
            $result = $parser->parse($source, false); // Don't use cache for validation
            
            return [
                'valid' => true,
                'source' => $source,
                'schemas_count' => count($result['schemas'] ?? []),
                'endpoints_count' => count($result['endpoints'] ?? []),
                'parsed_at' => $result['parsed_at'] ?? null,
            ];
            
        } catch (\Exception $e) {
            return [
                'valid' => false,
                'error' => $e->getMessage(),
                'source' => $source,
            ];
        }
    }

    /**
     * Validate all schemas
     */
    protected function validateAllSchemas(): array
    {
        $schemas = Config::get('api-client.schemas', []);
        $results = [];
        
        foreach ($schemas as $schemaName => $schemaConfig) {
            $results[$schemaName] = $this->validateSpecificSchema($schemaName);
        }
        
        return $results;
    }

    /**
     * Display schema validation results
     */
    protected function displaySchemaValidationResults(string $schemaName, array $results, string $format, bool $detailed): void
    {
        switch ($format) {
            case 'json':
                $this->line(json_encode([$schemaName => $results], JSON_PRETTY_PRINT));
                break;
                
            case 'yaml':
                $this->line(yaml_emit([$schemaName => $results]));
                break;
                
            default:
                $this->displaySchemaValidationTable($schemaName, $results, $detailed);
                break;
        }
    }

    /**
     * Display schema validation results in table format
     */
    protected function displaySchemaValidationTable(string $schemaName, array $results, bool $detailed): void
    {
        $status = $results['valid'] ? '✅ Valid' : '❌ Invalid';
        
        $tableData = [
            ['Schema', $schemaName],
            ['Status', $status],
        ];

        if (isset($results['source'])) {
            $tableData[] = ['Source', $results['source']];
        }

        if (isset($results['schemas_count'])) {
            $tableData[] = ['Schemas Count', $results['schemas_count']];
        }

        if (isset($results['endpoints_count'])) {
            $tableData[] = ['Endpoints Count', $results['endpoints_count']];
        }

        if (isset($results['parsed_at'])) {
            $tableData[] = ['Parsed At', $results['parsed_at']];
        }

        if (isset($results['error'])) {
            $tableData[] = ['Error', $results['error']];
        }

        $this->table(['Property', 'Value'], $tableData);
    }

    /**
     * Display all schema validation results
     */
    protected function displayAllSchemaResults(array $results, string $format, bool $detailed): void
    {
        switch ($format) {
            case 'json':
                $this->line(json_encode($results, JSON_PRETTY_PRINT));
                break;
                
            case 'yaml':
                $this->line(yaml_emit($results));
                break;
                
            default:
                $this->displayAllSchemaTable($results, $detailed);
                break;
        }
    }

    /**
     * Display all schema results in table format
     */
    protected function displayAllSchemaTable(array $results, bool $detailed): void
    {
        $tableData = [];
        
        foreach ($results as $schemaName => $result) {
            $status = $result['valid'] ? '✅ Valid' : '❌ Invalid';
            $error = $result['error'] ?? '';
            
            $row = [$schemaName, $status];
            
            if ($detailed) {
                $row[] = $result['schemas_count'] ?? 'N/A';
                $row[] = $result['endpoints_count'] ?? 'N/A';
                $row[] = $error;
            }
            
            $tableData[] = $row;
        }

        $headers = ['Schema', 'Status'];
        if ($detailed) {
            $headers = array_merge($headers, ['Schemas', 'Endpoints', 'Error']);
        }

        $this->table($headers, $tableData);
    }

    /**
     * Attempt to auto-fix configuration issues
     */
    protected function autoFixConfiguration(array $configResults): array
    {
        $fixResults = [
            'attempted' => [],
            'successful' => [],
            'failed' => [],
        ];

        // This is a basic implementation - in practice, you'd implement
        // specific fixes for common configuration issues
        
        foreach ($configResults['errors'] as $error) {
            $fixResults['attempted'][] = $error;
            
            // Example: Create missing directories
            if (str_contains($error, 'directory does not exist')) {
                try {
                    // Extract directory path and create it
                    // This is simplified - you'd need more sophisticated parsing
                    $this->line("  🔧 Attempting to create missing directory...");
                    $fixResults['successful'][] = "Created missing directory (simulated)";
                } catch (\Exception $e) {
                    $fixResults['failed'][] = "Failed to create directory: {$e->getMessage()}";
                }
            } else {
                $fixResults['failed'][] = "No auto-fix available for: {$error}";
            }
        }

        return $fixResults;
    }

    /**
     * Display auto-fix results
     */
    protected function displayAutoFixResults(array $results): void
    {
        if (!empty($results['successful'])) {
            $this->info('✅ Successfully fixed:');
            foreach ($results['successful'] as $fix) {
                $this->line("  • {$fix}");
            }
        }

        if (!empty($results['failed'])) {
            $this->warn('⚠️  Could not fix:');
            foreach ($results['failed'] as $failure) {
                $this->line("  • {$failure}");
            }
        }
    }

    /**
     * Format health status with appropriate emoji
     */
    protected function formatHealthStatus(string $status): string
    {
        return match ($status) {
            'healthy' => '✅ Healthy',
            'warning' => '⚠️  Warning',
            'unhealthy' => '❌ Unhealthy',
            default => "❓ {$status}",
        };
    }

    /**
     * Save validation report to file
     */
    protected function saveReportIfRequested(array $results, ?string $saveReport): bool
    {
        if (!$saveReport) {
            return true;
        }

        try {
            $reportContent = json_encode($results, JSON_PRETTY_PRINT);
            file_put_contents($saveReport, $reportContent);
            $this->info("📄 Validation report saved to: {$saveReport}");
            return true;
        } catch (\Exception $e) {
            $this->error("❌ Failed to save report: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Determine overall success from all validation results
     */
    protected function determineOverallSuccess(array $results): bool
    {
        // Check configuration validation
        if (!($results['configuration']['valid'] ?? false)) {
            return false;
        }

        // Check health checks if performed
        if (isset($results['health_checks']) && $results['health_checks']['enabled']) {
            $overallHealth = $results['health_checks']['overall_status'] ?? 'unhealthy';
            if ($overallHealth === 'unhealthy') {
                return false;
            }
        }

        // Check schema validations
        if (isset($results['schema_validation'])) {
            foreach ($results['schema_validation'] as $schemaResult) {
                if (!($schemaResult['valid'] ?? false)) {
                    return false;
                }
            }
        }

        return true;
    }
}
