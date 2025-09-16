<?php

namespace MTechStack\LaravelApiModelClient\Testing;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Collection;

/**
 * Performance Benchmark for API testing
 */
class PerformanceBenchmark
{
    /**
     * Run performance benchmarks
     */
    public function runBenchmarks(?string $schema, int $iterations, int $concurrent, bool $dryRun): array
    {
        if ($dryRun) {
            return [
                'dry_run' => true,
                'summary' => [
                    'avg_response_time' => 150,
                    'min_response_time' => 100,
                    'max_response_time' => 300,
                    'total_requests' => $iterations,
                    'successful_requests' => $iterations,
                    'failed_requests' => 0,
                    'requests_per_second' => round($iterations / 10, 2),
                ],
            ];
        }

        $results = [];
        $schemas = $this->getSchemas($schema);

        foreach ($schemas as $schemaName => $schemaConfig) {
            $results[$schemaName] = $this->benchmarkSchema($schemaConfig, $iterations, $concurrent);
        }

        return [
            'schemas' => $results,
            'summary' => $this->calculateOverallSummary($results),
        ];
    }

    /**
     * Run load tests
     */
    public function runLoadTests(?string $schema, int $concurrent, int $iterations, bool $dryRun): array
    {
        if ($dryRun) {
            return [
                'dry_run' => true,
                'summary' => [
                    'concurrent_users' => $concurrent,
                    'total_duration' => 30,
                    'total_requests' => $concurrent * $iterations,
                    'successful_requests' => $concurrent * $iterations,
                    'failed_requests' => 0,
                    'avg_rps' => round(($concurrent * $iterations) / 30, 2),
                    'peak_rps' => round(($concurrent * $iterations) / 20, 2),
                ],
            ];
        }

        $results = [];
        $schemas = $this->getSchemas($schema);

        foreach ($schemas as $schemaName => $schemaConfig) {
            $results[$schemaName] = $this->loadTestSchema($schemaConfig, $concurrent, $iterations);
        }

        return [
            'schemas' => $results,
            'summary' => $this->calculateLoadTestSummary($results),
        ];
    }

    /**
     * Benchmark individual schema
     */
    protected function benchmarkSchema(array $schemaConfig, int $iterations, int $concurrent): array
    {
        $baseUrl = $schemaConfig['base_url'] ?? null;
        if (!$baseUrl) {
            return [
                'error' => 'No base URL configured',
                'successful_requests' => 0,
                'failed_requests' => $iterations,
            ];
        }

        $headers = $this->getAuthHeaders($schemaConfig);
        $results = [];
        $startTime = microtime(true);

        // Sequential requests for accurate timing
        for ($i = 0; $i < $iterations; $i++) {
            $requestStart = microtime(true);
            
            try {
                $response = Http::timeout(30)
                    ->withHeaders($headers)
                    ->get($baseUrl);
                
                $requestEnd = microtime(true);
                $responseTime = ($requestEnd - $requestStart) * 1000;

                $results[] = [
                    'success' => $response->successful(),
                    'status_code' => $response->status(),
                    'response_time_ms' => $responseTime,
                    'response_size' => strlen($response->body()),
                ];
                
            } catch (\Exception $e) {
                $requestEnd = microtime(true);
                $responseTime = ($requestEnd - $requestStart) * 1000;

                $results[] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                    'response_time_ms' => $responseTime,
                ];
            }
        }

        $endTime = microtime(true);
        $totalDuration = $endTime - $startTime;

        return $this->analyzeBenchmarkResults($results, $totalDuration);
    }

    /**
     * Load test individual schema
     */
    protected function loadTestSchema(array $schemaConfig, int $concurrent, int $iterations): array
    {
        $baseUrl = $schemaConfig['base_url'] ?? null;
        if (!$baseUrl) {
            return [
                'error' => 'No base URL configured',
                'successful_requests' => 0,
                'failed_requests' => $concurrent * $iterations,
            ];
        }

        $headers = $this->getAuthHeaders($schemaConfig);
        $results = [];
        $startTime = microtime(true);

        // Simulate concurrent requests
        $promises = [];
        for ($c = 0; $c < $concurrent; $c++) {
            for ($i = 0; $i < $iterations; $i++) {
                $requestStart = microtime(true);
                
                try {
                    $response = Http::timeout(30)
                        ->withHeaders($headers)
                        ->get($baseUrl);
                    
                    $requestEnd = microtime(true);
                    $responseTime = ($requestEnd - $requestStart) * 1000;

                    $results[] = [
                        'success' => $response->successful(),
                        'status_code' => $response->status(),
                        'response_time_ms' => $responseTime,
                        'concurrent_user' => $c + 1,
                        'iteration' => $i + 1,
                    ];
                    
                } catch (\Exception $e) {
                    $requestEnd = microtime(true);
                    $responseTime = ($requestEnd - $requestStart) * 1000;

                    $results[] = [
                        'success' => false,
                        'error' => $e->getMessage(),
                        'response_time_ms' => $responseTime,
                        'concurrent_user' => $c + 1,
                        'iteration' => $i + 1,
                    ];
                }
            }
        }

        $endTime = microtime(true);
        $totalDuration = $endTime - $startTime;

        return $this->analyzeLoadTestResults($results, $totalDuration, $concurrent);
    }

    /**
     * Analyze benchmark results
     */
    protected function analyzeBenchmarkResults(array $results, float $totalDuration): array
    {
        $collection = collect($results);
        $successful = $collection->where('success', true);
        $failed = $collection->where('success', false);
        
        $responseTimes = $successful->pluck('response_time_ms')->filter();
        
        return [
            'total_requests' => count($results),
            'successful_requests' => $successful->count(),
            'failed_requests' => $failed->count(),
            'success_rate' => $successful->count() / count($results) * 100,
            'total_duration' => round($totalDuration, 2),
            'avg_response_time' => $responseTimes->avg() ? round($responseTimes->avg(), 2) : 0,
            'min_response_time' => $responseTimes->min() ? round($responseTimes->min(), 2) : 0,
            'max_response_time' => $responseTimes->max() ? round($responseTimes->max(), 2) : 0,
            'median_response_time' => $responseTimes->median() ? round($responseTimes->median(), 2) : 0,
            'p95_response_time' => $this->calculatePercentile($responseTimes->toArray(), 95),
            'p99_response_time' => $this->calculatePercentile($responseTimes->toArray(), 99),
            'requests_per_second' => count($results) / $totalDuration,
            'avg_response_size' => $successful->avg('response_size') ? round($successful->avg('response_size'), 2) : 0,
            'status_codes' => $successful->groupBy('status_code')->map->count()->toArray(),
            'errors' => $failed->pluck('error')->unique()->values()->toArray(),
        ];
    }

    /**
     * Analyze load test results
     */
    protected function analyzeLoadTestResults(array $results, float $totalDuration, int $concurrent): array
    {
        $collection = collect($results);
        $successful = $collection->where('success', true);
        $failed = $collection->where('success', false);
        
        $responseTimes = $successful->pluck('response_time_ms')->filter();
        
        // Calculate RPS over time (simplified)
        $rpsData = [];
        $timeSlices = 10; // Divide duration into 10 slices
        $sliceDuration = $totalDuration / $timeSlices;
        
        for ($i = 0; $i < $timeSlices; $i++) {
            $rpsData[] = count($results) / $timeSlices / $sliceDuration;
        }
        
        return [
            'concurrent_users' => $concurrent,
            'total_requests' => count($results),
            'successful_requests' => $successful->count(),
            'failed_requests' => $failed->count(),
            'success_rate' => $successful->count() / count($results) * 100,
            'total_duration' => round($totalDuration, 2),
            'avg_response_time' => $responseTimes->avg() ? round($responseTimes->avg(), 2) : 0,
            'min_response_time' => $responseTimes->min() ? round($responseTimes->min(), 2) : 0,
            'max_response_time' => $responseTimes->max() ? round($responseTimes->max(), 2) : 0,
            'avg_rps' => count($results) / $totalDuration,
            'peak_rps' => max($rpsData),
            'min_rps' => min($rpsData),
            'rps_over_time' => $rpsData,
            'concurrent_performance' => $this->analyzeConcurrentPerformance($collection, $concurrent),
        ];
    }

    /**
     * Analyze concurrent performance
     */
    protected function analyzeConcurrentPerformance(Collection $results, int $concurrent): array
    {
        $performance = [];
        
        for ($c = 1; $c <= $concurrent; $c++) {
            $userResults = $results->where('concurrent_user', $c);
            $responseTimes = $userResults->where('success', true)->pluck('response_time_ms');
            
            $performance["user_{$c}"] = [
                'requests' => $userResults->count(),
                'successful' => $userResults->where('success', true)->count(),
                'failed' => $userResults->where('success', false)->count(),
                'avg_response_time' => $responseTimes->avg() ? round($responseTimes->avg(), 2) : 0,
            ];
        }
        
        return $performance;
    }

    /**
     * Calculate overall summary
     */
    protected function calculateOverallSummary(array $results): array
    {
        $totalRequests = 0;
        $totalSuccessful = 0;
        $totalFailed = 0;
        $totalDuration = 0;
        $allResponseTimes = [];

        foreach ($results as $schemaResult) {
            if (isset($schemaResult['error'])) {
                continue;
            }
            
            $totalRequests += $schemaResult['total_requests'] ?? 0;
            $totalSuccessful += $schemaResult['successful_requests'] ?? 0;
            $totalFailed += $schemaResult['failed_requests'] ?? 0;
            $totalDuration = max($totalDuration, $schemaResult['total_duration'] ?? 0);
            
            if (isset($schemaResult['avg_response_time'])) {
                $allResponseTimes[] = $schemaResult['avg_response_time'];
            }
        }

        return [
            'total_requests' => $totalRequests,
            'successful_requests' => $totalSuccessful,
            'failed_requests' => $totalFailed,
            'success_rate' => $totalRequests > 0 ? round(($totalSuccessful / $totalRequests) * 100, 2) : 0,
            'avg_response_time' => !empty($allResponseTimes) ? round(array_sum($allResponseTimes) / count($allResponseTimes), 2) : 0,
            'min_response_time' => !empty($allResponseTimes) ? min($allResponseTimes) : 0,
            'max_response_time' => !empty($allResponseTimes) ? max($allResponseTimes) : 0,
            'requests_per_second' => $totalDuration > 0 ? round($totalRequests / $totalDuration, 2) : 0,
        ];
    }

    /**
     * Calculate load test summary
     */
    protected function calculateLoadTestSummary(array $results): array
    {
        $totalRequests = 0;
        $totalSuccessful = 0;
        $totalFailed = 0;
        $totalDuration = 0;
        $totalConcurrent = 0;
        $allRps = [];

        foreach ($results as $schemaResult) {
            if (isset($schemaResult['error'])) {
                continue;
            }
            
            $totalRequests += $schemaResult['total_requests'] ?? 0;
            $totalSuccessful += $schemaResult['successful_requests'] ?? 0;
            $totalFailed += $schemaResult['failed_requests'] ?? 0;
            $totalDuration = max($totalDuration, $schemaResult['total_duration'] ?? 0);
            $totalConcurrent = max($totalConcurrent, $schemaResult['concurrent_users'] ?? 0);
            
            if (isset($schemaResult['avg_rps'])) {
                $allRps[] = $schemaResult['avg_rps'];
            }
        }

        return [
            'concurrent_users' => $totalConcurrent,
            'total_duration' => $totalDuration,
            'total_requests' => $totalRequests,
            'successful_requests' => $totalSuccessful,
            'failed_requests' => $totalFailed,
            'success_rate' => $totalRequests > 0 ? round(($totalSuccessful / $totalRequests) * 100, 2) : 0,
            'avg_rps' => !empty($allRps) ? round(array_sum($allRps) / count($allRps), 2) : 0,
            'peak_rps' => !empty($allRps) ? max($allRps) : 0,
        ];
    }

    /**
     * Calculate percentile
     */
    protected function calculatePercentile(array $values, int $percentile): float
    {
        if (empty($values)) {
            return 0;
        }
        
        sort($values);
        $index = ceil(($percentile / 100) * count($values)) - 1;
        $index = max(0, min($index, count($values) - 1));
        
        return round($values[$index], 2);
    }

    /**
     * Get schemas for testing
     */
    protected function getSchemas(?string $schema): array
    {
        $schemas = Config::get('api-client.schemas', []);
        
        if ($schema) {
            return isset($schemas[$schema]) ? [$schema => $schemas[$schema]] : [];
        }
        
        return $schemas;
    }

    /**
     * Get authentication headers
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
}
