<?php

namespace MTechStack\LaravelApiModelClient\Tests\Utilities;

use Illuminate\Support\Facades\Log;

/**
 * Performance benchmarking utility for OpenAPI tests
 */
class PerformanceBenchmark
{
    protected array $benchmarks = [];
    protected array $results = [];

    /**
     * Start a benchmark
     */
    public function start(string $operation): void
    {
        $this->benchmarks[$operation] = [
            'start_time' => microtime(true),
            'start_memory' => memory_get_usage(true),
            'start_peak_memory' => memory_get_peak_usage(true)
        ];
    }

    /**
     * End a benchmark and return results
     */
    public function end(string $operation): array
    {
        if (!isset($this->benchmarks[$operation])) {
            throw new \InvalidArgumentException("Benchmark '{$operation}' was not started");
        }

        $benchmark = $this->benchmarks[$operation];
        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);
        $endPeakMemory = memory_get_peak_usage(true);

        $result = [
            'operation' => $operation,
            'execution_time' => $endTime - $benchmark['start_time'],
            'memory_usage' => $endMemory - $benchmark['start_memory'],
            'peak_memory_usage' => $endPeakMemory - $benchmark['start_peak_memory'],
            'start_memory' => $benchmark['start_memory'],
            'end_memory' => $endMemory,
            'timestamp' => now()->toISOString()
        ];

        $this->results[$operation] = $result;
        unset($this->benchmarks[$operation]);

        return $result;
    }

    /**
     * Get benchmark result
     */
    public function getResult(string $operation): ?array
    {
        return $this->results[$operation] ?? null;
    }

    /**
     * Get all results
     */
    public function getAllResults(): array
    {
        return $this->results;
    }

    /**
     * Benchmark a callable function
     */
    public function benchmark(string $operation, callable $callback): array
    {
        $this->start($operation);
        
        try {
            $return = $callback();
            $result = $this->end($operation);
            $result['return_value'] = $return;
            $result['success'] = true;
            return $result;
        } catch (\Exception $e) {
            $result = $this->end($operation);
            $result['error'] = $e->getMessage();
            $result['success'] = false;
            return $result;
        }
    }

    /**
     * Benchmark multiple iterations of an operation
     */
    public function benchmarkIterations(string $operation, callable $callback, int $iterations = 10): array
    {
        $results = [];
        $totalTime = 0;
        $totalMemory = 0;
        $errors = 0;

        for ($i = 0; $i < $iterations; $i++) {
            $iterationOperation = "{$operation}_iteration_{$i}";
            $result = $this->benchmark($iterationOperation, $callback);
            
            $results[] = $result;
            $totalTime += $result['execution_time'];
            $totalMemory += $result['memory_usage'];
            
            if (!$result['success']) {
                $errors++;
            }
        }

        return [
            'operation' => $operation,
            'iterations' => $iterations,
            'total_time' => $totalTime,
            'average_time' => $totalTime / $iterations,
            'min_time' => min(array_column($results, 'execution_time')),
            'max_time' => max(array_column($results, 'execution_time')),
            'total_memory' => $totalMemory,
            'average_memory' => $totalMemory / $iterations,
            'min_memory' => min(array_column($results, 'memory_usage')),
            'max_memory' => max(array_column($results, 'memory_usage')),
            'errors' => $errors,
            'success_rate' => (($iterations - $errors) / $iterations) * 100,
            'results' => $results
        ];
    }

    /**
     * Compare performance between operations
     */
    public function compare(array $operations): array
    {
        $comparison = [];
        
        foreach ($operations as $operation) {
            if (!isset($this->results[$operation])) {
                continue;
            }
            
            $result = $this->results[$operation];
            $comparison[$operation] = [
                'execution_time' => $result['execution_time'],
                'memory_usage' => $result['memory_usage'],
                'peak_memory_usage' => $result['peak_memory_usage']
            ];
        }

        if (count($comparison) < 2) {
            return $comparison;
        }

        // Calculate relative performance
        $baseOperation = array_key_first($comparison);
        $baseTime = $comparison[$baseOperation]['execution_time'];
        $baseMemory = $comparison[$baseOperation]['memory_usage'];

        foreach ($comparison as $operation => &$data) {
            if ($operation === $baseOperation) {
                $data['time_ratio'] = 1.0;
                $data['memory_ratio'] = 1.0;
            } else {
                $data['time_ratio'] = $data['execution_time'] / $baseTime;
                $data['memory_ratio'] = $data['memory_usage'] / $baseMemory;
            }
        }

        return $comparison;
    }

    /**
     * Generate performance report
     */
    public function generateReport(): array
    {
        $report = [
            'summary' => [
                'total_operations' => count($this->results),
                'total_time' => array_sum(array_column($this->results, 'execution_time')),
                'total_memory' => array_sum(array_column($this->results, 'memory_usage')),
                'average_time' => count($this->results) > 0 ? array_sum(array_column($this->results, 'execution_time')) / count($this->results) : 0,
                'average_memory' => count($this->results) > 0 ? array_sum(array_column($this->results, 'memory_usage')) / count($this->results) : 0
            ],
            'operations' => []
        ];

        foreach ($this->results as $operation => $result) {
            $report['operations'][$operation] = [
                'execution_time' => $result['execution_time'],
                'execution_time_ms' => $result['execution_time'] * 1000,
                'memory_usage' => $result['memory_usage'],
                'memory_usage_mb' => $result['memory_usage'] / 1024 / 1024,
                'peak_memory_usage' => $result['peak_memory_usage'],
                'peak_memory_usage_mb' => $result['peak_memory_usage'] / 1024 / 1024,
                'performance_grade' => $this->getPerformanceGrade($result)
            ];
        }

        // Sort by execution time
        uasort($report['operations'], function ($a, $b) {
            return $a['execution_time'] <=> $b['execution_time'];
        });

        return $report;
    }

    /**
     * Get performance grade based on execution time and memory usage
     */
    protected function getPerformanceGrade(array $result): string
    {
        $time = $result['execution_time'];
        $memory = $result['memory_usage'] / 1024 / 1024; // Convert to MB

        // Grade based on time (seconds) and memory (MB)
        if ($time < 0.01 && $memory < 1) {
            return 'A+';
        } elseif ($time < 0.05 && $memory < 5) {
            return 'A';
        } elseif ($time < 0.1 && $memory < 10) {
            return 'B+';
        } elseif ($time < 0.5 && $memory < 25) {
            return 'B';
        } elseif ($time < 1.0 && $memory < 50) {
            return 'C+';
        } elseif ($time < 2.0 && $memory < 100) {
            return 'C';
        } elseif ($time < 5.0 && $memory < 200) {
            return 'D';
        } else {
            return 'F';
        }
    }

    /**
     * Assert performance meets criteria
     */
    public function assertPerformance(string $operation, array $criteria): bool
    {
        if (!isset($this->results[$operation])) {
            throw new \InvalidArgumentException("No benchmark result found for operation '{$operation}'");
        }

        $result = $this->results[$operation];
        $passes = true;

        if (isset($criteria['max_time']) && $result['execution_time'] > $criteria['max_time']) {
            $passes = false;
        }

        if (isset($criteria['max_memory']) && $result['memory_usage'] > $criteria['max_memory']) {
            $passes = false;
        }

        if (isset($criteria['max_peak_memory']) && $result['peak_memory_usage'] > $criteria['max_peak_memory']) {
            $passes = false;
        }

        return $passes;
    }

    /**
     * Log performance results
     */
    public function logResults(string $level = 'info'): void
    {
        $report = $this->generateReport();
        
        Log::log($level, 'Performance Benchmark Report', [
            'summary' => $report['summary'],
            'operations_count' => count($report['operations']),
            'top_performers' => array_slice($report['operations'], 0, 5, true),
            'worst_performers' => array_slice(array_reverse($report['operations'], true), 0, 5, true)
        ]);
    }

    /**
     * Reset all benchmarks and results
     */
    public function reset(): void
    {
        $this->benchmarks = [];
        $this->results = [];
    }

    /**
     * Export results to array
     */
    public function export(): array
    {
        return [
            'benchmarks' => $this->benchmarks,
            'results' => $this->results,
            'report' => $this->generateReport(),
            'exported_at' => now()->toISOString()
        ];
    }

    /**
     * Import results from array
     */
    public function import(array $data): void
    {
        if (isset($data['benchmarks'])) {
            $this->benchmarks = $data['benchmarks'];
        }
        
        if (isset($data['results'])) {
            $this->results = $data['results'];
        }
    }

    /**
     * Get memory usage in human readable format
     */
    public function formatMemory(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * Get execution time in human readable format
     */
    public function formatTime(float $seconds): string
    {
        if ($seconds < 0.001) {
            return round($seconds * 1000000, 2) . ' μs';
        } elseif ($seconds < 1) {
            return round($seconds * 1000, 2) . ' ms';
        } else {
            return round($seconds, 3) . ' s';
        }
    }
}
