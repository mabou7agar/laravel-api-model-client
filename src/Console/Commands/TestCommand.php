<?php

namespace MTechStack\LaravelApiModelClient\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use MTechStack\LaravelApiModelClient\OpenApi\OpenApiSchemaParser;
use MTechStack\LaravelApiModelClient\Configuration\ConfigurationValidator;
use MTechStack\LaravelApiModelClient\Testing\ApiTestRunner;
use MTechStack\LaravelApiModelClient\Testing\PerformanceBenchmark;
use MTechStack\LaravelApiModelClient\Testing\CoverageAnalyzer;

/**
 * Enhanced Artisan command for comprehensive API client testing
 */
class TestCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'api-client:test 
                            {--schema= : Specific schema to test (optional)}
                            {--models= : Comma-separated list of models to test}
                            {--endpoints= : Comma-separated list of endpoints to test}
                            {--performance : Run performance benchmarks}
                            {--coverage : Generate test coverage report}
                            {--load-test : Run load testing}
                            {--timeout=30 : Request timeout in seconds}
                            {--concurrent=5 : Number of concurrent requests for load testing}
                            {--iterations=100 : Number of iterations for performance testing}
                            {--format=table : Output format (table, json, yaml, html)}
                            {--output= : Save test results to file}
                            {--verbose : Show detailed test information}
                            {--fail-fast : Stop on first failure}
                            {--dry-run : Show what would be tested without executing}';

    /**
     * The console command description.
     */
    protected $description = 'Run comprehensive tests for API client with OpenAPI validation, performance benchmarks, and coverage analysis';

    protected ConfigurationValidator $validator;
    protected OpenApiSchemaParser $parser;
    protected ApiTestRunner $testRunner;
    protected PerformanceBenchmark $benchmark;
    protected CoverageAnalyzer $coverage;

    public function __construct()
    {
        parent::__construct();
        $this->validator = new ConfigurationValidator();
        $this->parser = new OpenApiSchemaParser();
        $this->testRunner = new ApiTestRunner();
        $this->benchmark = new PerformanceBenchmark();
        $this->coverage = new CoverageAnalyzer();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🧪 Laravel API Model Client - Comprehensive Testing Suite');
        $this->newLine();

        $schema = $this->option('schema');
        $models = $this->parseList($this->option('models'));
        $endpoints = $this->parseList($this->option('endpoints'));
        $performance = $this->option('performance');
        $coverage = $this->option('coverage');
        $loadTest = $this->option('load-test');
        $timeout = (int) $this->option('timeout');
        $concurrent = (int) $this->option('concurrent');
        $iterations = (int) $this->option('iterations');
        $format = $this->option('format');
        $output = $this->option('output');
        $verbose = $this->option('verbose');
        $failFast = $this->option('fail-fast');
        $dryRun = $this->option('dry-run');

        $results = [
            'timestamp' => now()->toISOString(),
            'configuration' => [],
            'schemas' => [],
            'connectivity' => [],
            'models' => [],
            'endpoints' => [],
            'performance' => [],
            'coverage' => [],
            'summary' => [],
        ];

        try {
            // Step 1: Configuration Validation
            $this->line('📋 Step 1: Validating Configuration...');
            $configResults = $this->validateConfiguration();
            $results['configuration'] = $configResults;
            $this->displayConfigurationResults($configResults, $verbose);

            if (!$configResults['valid'] && $failFast) {
                $this->error('❌ Configuration validation failed. Use --verbose for details.');
                return 1;
            }

            // Step 2: Schema Validation
            $this->newLine();
            $this->line('🔍 Step 2: Validating OpenAPI Schemas...');
            $schemaResults = $this->validateSchemas($schema, $dryRun);
            $results['schemas'] = $schemaResults;
            $this->displaySchemaResults($schemaResults, $verbose);

            if (!$this->allSchemasValid($schemaResults) && $failFast) {
                $this->error('❌ Schema validation failed. Use --verbose for details.');
                return 1;
            }

            // Step 3: Connectivity Testing
            $this->newLine();
            $this->line('🌐 Step 3: Testing API Connectivity...');
            $connectivityResults = $this->testConnectivity($schema, $timeout, $dryRun);
            $results['connectivity'] = $connectivityResults;
            $this->displayConnectivityResults($connectivityResults, $verbose);

            if (!$this->allConnectionsSuccessful($connectivityResults) && $failFast) {
                $this->error('❌ Connectivity tests failed. Use --verbose for details.');
                return 1;
            }

            // Step 4: Model Testing
            if ($models || !$schema) {
                $this->newLine();
                $this->line('🏗️ Step 4: Testing API Models...');
                $modelResults = $this->testModels($models, $schema, $dryRun);
                $results['models'] = $modelResults;
                $this->displayModelResults($modelResults, $verbose);
            }

            // Step 5: Endpoint Testing
            if ($endpoints) {
                $this->newLine();
                $this->line('🎯 Step 5: Testing API Endpoints...');
                $endpointResults = $this->testEndpoints($endpoints, $schema, $timeout, $dryRun);
                $results['endpoints'] = $endpointResults;
                $this->displayEndpointResults($endpointResults, $verbose);
            }

            // Step 6: Performance Testing
            if ($performance) {
                $this->newLine();
                $this->line('⚡ Step 6: Running Performance Benchmarks...');
                $performanceResults = $this->runPerformanceTests($schema, $iterations, $concurrent, $dryRun);
                $results['performance'] = $performanceResults;
                $this->displayPerformanceResults($performanceResults, $verbose);
            }

            // Step 7: Load Testing
            if ($loadTest) {
                $this->newLine();
                $this->line('🚀 Step 7: Running Load Tests...');
                $loadResults = $this->runLoadTests($schema, $concurrent, $iterations, $dryRun);
                $results['performance']['load_testing'] = $loadResults;
                $this->displayLoadTestResults($loadResults, $verbose);
            }

            // Step 8: Coverage Analysis
            if ($coverage) {
                $this->newLine();
                $this->line('📊 Step 8: Analyzing Test Coverage...');
                $coverageResults = $this->analyzeCoverage($schema, $models, $endpoints, $dryRun);
                $results['coverage'] = $coverageResults;
                $this->displayCoverageResults($coverageResults, $verbose);
            }

            // Generate Summary
            $this->newLine();
            $this->line('📈 Test Summary');
            $summary = $this->generateSummary($results);
            $results['summary'] = $summary;
            $this->displaySummary($summary, $format);

            // Save Results
            if ($output) {
                $this->saveResults($results, $output, $format);
            }

            // Determine overall success
            $success = $this->determineOverallSuccess($results);
            
            if ($success) {
                $this->info('✅ All tests passed successfully!');
                return 0;
            } else {
                $this->error('❌ Some tests failed. Check the results above.');
                return 1;
            }

        } catch (\Exception $e) {
            $this->error("❌ Testing failed with error: {$e->getMessage()}");
            
            if ($verbose) {
                $this->line("Stack trace:");
                $this->line($e->getTraceAsString());
            }
            
            return 1;
        }
    }

    /**
     * Parse comma-separated list option
     */
    protected function parseList(?string $list): array
    {
        if (!$list) {
            return [];
        }
        
        return array_map('trim', explode(',', $list));
    }

    /**
     * Validate configuration
     */
    protected function validateConfiguration(): array
    {
        try {
            return $this->validator->validate();
        } catch (\Exception $e) {
            return [
                'valid' => false,
                'error' => $e->getMessage(),
                'summary' => [
                    'total_errors' => 1,
                    'total_warnings' => 0,
                    'schemas_count' => 0,
                    'enabled_schemas' => 0,
                ],
            ];
        }
    }

    /**
     * Validate OpenAPI schemas
     */
    protected function validateSchemas(?string $targetSchema, bool $dryRun): array
    {
        $schemas = Config::get('api-client.schemas', []);
        $results = [];
        
        foreach ($schemas as $schemaName => $schemaConfig) {
            if ($targetSchema && $schemaName !== $targetSchema) {
                continue;
            }

            if ($dryRun) {
                $results[$schemaName] = [
                    'valid' => true,
                    'dry_run' => true,
                    'source' => $schemaConfig['source'] ?? 'Not configured',
                ];
                continue;
            }

            try {
                $source = $schemaConfig['source'] ?? null;
                if (!$source) {
                    $results[$schemaName] = [
                        'valid' => false,
                        'error' => 'No source configured',
                    ];
                    continue;
                }

                $parsed = $this->parser->parse($source, false);
                $results[$schemaName] = [
                    'valid' => true,
                    'source' => $source,
                    'schemas_count' => count($parsed['schemas'] ?? []),
                    'endpoints_count' => count($parsed['endpoints'] ?? []),
                    'openapi_version' => $parsed['info']['openapi'] ?? 'Unknown',
                    'parsed_at' => $parsed['parsed_at'] ?? null,
                ];
                
            } catch (\Exception $e) {
                $results[$schemaName] = [
                    'valid' => false,
                    'error' => $e->getMessage(),
                    'source' => $schemaConfig['source'] ?? 'Not configured',
                ];
            }
        }
        
        return $results;
    }

    /**
     * Test API connectivity
     */
    protected function testConnectivity(?string $targetSchema, int $timeout, bool $dryRun): array
    {
        $schemas = Config::get('api-client.schemas', []);
        $results = [];
        
        foreach ($schemas as $schemaName => $schemaConfig) {
            if ($targetSchema && $schemaName !== $targetSchema) {
                continue;
            }

            if ($dryRun) {
                $results[$schemaName] = [
                    'success' => true,
                    'dry_run' => true,
                    'base_url' => $schemaConfig['base_url'] ?? 'Not configured',
                ];
                continue;
            }

            $baseUrl = $schemaConfig['base_url'] ?? null;
            if (!$baseUrl) {
                $results[$schemaName] = [
                    'success' => false,
                    'error' => 'No base URL configured',
                ];
                continue;
            }

            try {
                $startTime = microtime(true);
                
                $response = Http::timeout($timeout)
                    ->withHeaders($this->getAuthHeaders($schemaConfig))
                    ->get($baseUrl);
                
                $endTime = microtime(true);
                $responseTime = ($endTime - $startTime) * 1000; // Convert to milliseconds

                $results[$schemaName] = [
                    'success' => $response->successful(),
                    'status_code' => $response->status(),
                    'response_time_ms' => round($responseTime, 2),
                    'base_url' => $baseUrl,
                ];
                
            } catch (\Exception $e) {
                $results[$schemaName] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                    'base_url' => $baseUrl,
                ];
            }
        }
        
        return $results;
    }

    /**
     * Test API models
     */
    protected function testModels(array $models, ?string $schema, bool $dryRun): array
    {
        return $this->testRunner->testModels($models, $schema, $dryRun);
    }

    /**
     * Test API endpoints
     */
    protected function testEndpoints(array $endpoints, ?string $schema, int $timeout, bool $dryRun): array
    {
        return $this->testRunner->testEndpoints($endpoints, $schema, $timeout, $dryRun);
    }

    /**
     * Run performance tests
     */
    protected function runPerformanceTests(?string $schema, int $iterations, int $concurrent, bool $dryRun): array
    {
        return $this->benchmark->runBenchmarks($schema, $iterations, $concurrent, $dryRun);
    }

    /**
     * Run load tests
     */
    protected function runLoadTests(?string $schema, int $concurrent, int $iterations, bool $dryRun): array
    {
        return $this->benchmark->runLoadTests($schema, $concurrent, $iterations, $dryRun);
    }

    /**
     * Analyze test coverage
     */
    protected function analyzeCoverage(?string $schema, array $models, array $endpoints, bool $dryRun): array
    {
        return $this->coverage->analyze($schema, $models, $endpoints, $dryRun);
    }

    /**
     * Get authentication headers for schema
     */
    protected function getAuthHeaders(array $schemaConfig): array
    {
        $auth = $schemaConfig['authentication'] ?? [];
        
        switch ($auth['type'] ?? '') {
            case 'bearer':
                return ['Authorization' => 'Bearer ' . ($auth['token'] ?? '')];
            case 'api_key':
                $key = $auth['key'] ?? 'X-API-Key';
                $value = $auth['value'] ?? $auth['token'] ?? '';
                return [$key => $value];
            case 'basic':
                $username = $auth['username'] ?? '';
                $password = $auth['password'] ?? '';
                return ['Authorization' => 'Basic ' . base64_encode("{$username}:{$password}")];
            default:
                return [];
        }
    }

    /**
     * Display configuration results
     */
    protected function displayConfigurationResults(array $results, bool $verbose): void
    {
        $status = $results['valid'] ? '✅ Valid' : '❌ Invalid';
        $this->line("  Status: {$status}");
        
        if ($verbose && !empty($results['errors'])) {
            $this->line("  Errors:");
            foreach ($results['errors'] as $error) {
                $this->line("    • {$error}");
            }
        }
    }

    /**
     * Display schema results
     */
    protected function displaySchemaResults(array $results, bool $verbose): void
    {
        foreach ($results as $schemaName => $result) {
            $status = $result['valid'] ? '✅' : '❌';
            $this->line("  {$status} {$schemaName}");
            
            if ($verbose) {
                if (isset($result['schemas_count'])) {
                    $this->line("    Schemas: {$result['schemas_count']}");
                }
                if (isset($result['endpoints_count'])) {
                    $this->line("    Endpoints: {$result['endpoints_count']}");
                }
                if (isset($result['error'])) {
                    $this->line("    Error: {$result['error']}");
                }
            }
        }
    }

    /**
     * Display connectivity results
     */
    protected function displayConnectivityResults(array $results, bool $verbose): void
    {
        foreach ($results as $schemaName => $result) {
            $status = $result['success'] ? '✅' : '❌';
            $this->line("  {$status} {$schemaName}");
            
            if ($verbose) {
                if (isset($result['response_time_ms'])) {
                    $this->line("    Response Time: {$result['response_time_ms']}ms");
                }
                if (isset($result['status_code'])) {
                    $this->line("    Status Code: {$result['status_code']}");
                }
                if (isset($result['error'])) {
                    $this->line("    Error: {$result['error']}");
                }
            }
        }
    }

    /**
     * Display model results
     */
    protected function displayModelResults(array $results, bool $verbose): void
    {
        foreach ($results as $modelName => $result) {
            $status = $result['success'] ? '✅' : '❌';
            $this->line("  {$status} {$modelName}");
            
            if ($verbose && isset($result['tests'])) {
                foreach ($result['tests'] as $testName => $testResult) {
                    $testStatus = $testResult['success'] ? '✅' : '❌';
                    $this->line("    {$testStatus} {$testName}");
                }
            }
        }
    }

    /**
     * Display endpoint results
     */
    protected function displayEndpointResults(array $results, bool $verbose): void
    {
        foreach ($results as $endpoint => $result) {
            $status = $result['success'] ? '✅' : '❌';
            $this->line("  {$status} {$endpoint}");
            
            if ($verbose) {
                if (isset($result['response_time_ms'])) {
                    $this->line("    Response Time: {$result['response_time_ms']}ms");
                }
                if (isset($result['error'])) {
                    $this->line("    Error: {$result['error']}");
                }
            }
        }
    }

    /**
     * Display performance results
     */
    protected function displayPerformanceResults(array $results, bool $verbose): void
    {
        if (isset($results['summary'])) {
            $summary = $results['summary'];
            $this->table(
                ['Metric', 'Value'],
                [
                    ['Average Response Time', ($summary['avg_response_time'] ?? 0) . 'ms'],
                    ['Min Response Time', ($summary['min_response_time'] ?? 0) . 'ms'],
                    ['Max Response Time', ($summary['max_response_time'] ?? 0) . 'ms'],
                    ['Total Requests', $summary['total_requests'] ?? 0],
                    ['Successful Requests', $summary['successful_requests'] ?? 0],
                    ['Failed Requests', $summary['failed_requests'] ?? 0],
                    ['Requests per Second', round($summary['requests_per_second'] ?? 0, 2)],
                ]
            );
        }
    }

    /**
     * Display load test results
     */
    protected function displayLoadTestResults(array $results, bool $verbose): void
    {
        if (isset($results['summary'])) {
            $summary = $results['summary'];
            $this->table(
                ['Metric', 'Value'],
                [
                    ['Concurrent Users', $summary['concurrent_users'] ?? 0],
                    ['Total Duration', ($summary['total_duration'] ?? 0) . 's'],
                    ['Total Requests', $summary['total_requests'] ?? 0],
                    ['Successful Requests', $summary['successful_requests'] ?? 0],
                    ['Failed Requests', $summary['failed_requests'] ?? 0],
                    ['Average RPS', round($summary['avg_rps'] ?? 0, 2)],
                    ['Peak RPS', round($summary['peak_rps'] ?? 0, 2)],
                ]
            );
        }
    }

    /**
     * Display coverage results
     */
    protected function displayCoverageResults(array $results, bool $verbose): void
    {
        if (isset($results['summary'])) {
            $summary = $results['summary'];
            $this->table(
                ['Coverage Type', 'Percentage'],
                [
                    ['Schema Coverage', round($summary['schema_coverage'] ?? 0, 2) . '%'],
                    ['Endpoint Coverage', round($summary['endpoint_coverage'] ?? 0, 2) . '%'],
                    ['Model Coverage', round($summary['model_coverage'] ?? 0, 2) . '%'],
                    ['Overall Coverage', round($summary['overall_coverage'] ?? 0, 2) . '%'],
                ]
            );
        }
    }

    /**
     * Generate test summary
     */
    protected function generateSummary(array $results): array
    {
        $summary = [
            'total_tests' => 0,
            'passed_tests' => 0,
            'failed_tests' => 0,
            'success_rate' => 0,
            'total_duration' => 0,
            'categories' => [],
        ];

        // Count configuration tests
        if (isset($results['configuration'])) {
            $summary['total_tests']++;
            if ($results['configuration']['valid']) {
                $summary['passed_tests']++;
            } else {
                $summary['failed_tests']++;
            }
            $summary['categories']['configuration'] = $results['configuration']['valid'];
        }

        // Count schema tests
        if (isset($results['schemas'])) {
            foreach ($results['schemas'] as $result) {
                $summary['total_tests']++;
                if ($result['valid']) {
                    $summary['passed_tests']++;
                } else {
                    $summary['failed_tests']++;
                }
            }
            $summary['categories']['schemas'] = $this->allSchemasValid($results['schemas']);
        }

        // Count connectivity tests
        if (isset($results['connectivity'])) {
            foreach ($results['connectivity'] as $result) {
                $summary['total_tests']++;
                if ($result['success']) {
                    $summary['passed_tests']++;
                } else {
                    $summary['failed_tests']++;
                }
            }
            $summary['categories']['connectivity'] = $this->allConnectionsSuccessful($results['connectivity']);
        }

        // Calculate success rate
        if ($summary['total_tests'] > 0) {
            $summary['success_rate'] = round(($summary['passed_tests'] / $summary['total_tests']) * 100, 2);
        }

        return $summary;
    }

    /**
     * Display summary
     */
    protected function displaySummary(array $summary, string $format): void
    {
        switch ($format) {
            case 'json':
                $this->line(json_encode($summary, JSON_PRETTY_PRINT));
                break;
                
            case 'yaml':
                $this->line(yaml_emit($summary));
                break;
                
            default:
                $this->table(
                    ['Metric', 'Value'],
                    [
                        ['Total Tests', $summary['total_tests']],
                        ['Passed Tests', $summary['passed_tests']],
                        ['Failed Tests', $summary['failed_tests']],
                        ['Success Rate', $summary['success_rate'] . '%'],
                    ]
                );
                break;
        }
    }

    /**
     * Save test results to file
     */
    protected function saveResults(array $results, string $output, string $format): void
    {
        try {
            $content = match ($format) {
                'json' => json_encode($results, JSON_PRETTY_PRINT),
                'yaml' => yaml_emit($results),
                'html' => $this->generateHtmlReport($results),
                default => json_encode($results, JSON_PRETTY_PRINT),
            };

            file_put_contents($output, $content);
            $this->info("📄 Test results saved to: {$output}");
        } catch (\Exception $e) {
            $this->error("❌ Failed to save results: {$e->getMessage()}");
        }
    }

    /**
     * Generate HTML report
     */
    protected function generateHtmlReport(array $results): string
    {
        // Basic HTML report generation
        $html = '<!DOCTYPE html><html><head><title>API Client Test Results</title>';
        $html .= '<style>body{font-family:Arial,sans-serif;}table{border-collapse:collapse;width:100%;}th,td{border:1px solid #ddd;padding:8px;text-align:left;}th{background-color:#f2f2f2;}</style>';
        $html .= '</head><body>';
        $html .= '<h1>API Client Test Results</h1>';
        $html .= '<p>Generated: ' . $results['timestamp'] . '</p>';
        
        if (isset($results['summary'])) {
            $html .= '<h2>Summary</h2>';
            $html .= '<table><tr><th>Metric</th><th>Value</th></tr>';
            foreach ($results['summary'] as $key => $value) {
                if (!is_array($value)) {
                    $html .= "<tr><td>{$key}</td><td>{$value}</td></tr>";
                }
            }
            $html .= '</table>';
        }
        
        $html .= '</body></html>';
        return $html;
    }

    /**
     * Check if all schemas are valid
     */
    protected function allSchemasValid(array $results): bool
    {
        foreach ($results as $result) {
            if (!($result['valid'] ?? false)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Check if all connections are successful
     */
    protected function allConnectionsSuccessful(array $results): bool
    {
        foreach ($results as $result) {
            if (!($result['success'] ?? false)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Determine overall test success
     */
    protected function determineOverallSuccess(array $results): bool
    {
        $summary = $results['summary'] ?? [];
        return ($summary['failed_tests'] ?? 1) === 0;
    }
}
