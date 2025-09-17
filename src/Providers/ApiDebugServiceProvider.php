<?php

namespace MTechStack\LaravelApiModelClient\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class ApiDebugServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register debug configuration
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/api-debug.php',
            'api-debug'
        );
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Auto-enable debugging based on environment variables
        if ($this->shouldEnableDebug()) {
            $this->enableHttpDebug();
            $this->enableQueryDebug();
            $this->setupDebugConfiguration();
        }
    }

    /**
     * Determine if debugging should be enabled based on environment variables
     */
    protected function shouldEnableDebug(): bool
    {
        // Check multiple environment variables for debug activation
        return env('API_CLIENT_AUTO_DEBUG', false) ||
               env('HTTP_CLIENT_DEBUG', false) ||
               (env('APP_DEBUG', false) && env('API_CLIENT_DEBUG_MODE', false)) ||
               (app()->environment(['local', 'testing']) && env('API_CLIENT_DEBUG_IN_LOCAL', true));
    }

    /**
     * Enable HTTP client debugging via Laravel events
     */
    protected function enableHttpDebug(): void
    {
        $verbose = env('API_CLIENT_DEBUG_VERBOSE', true);
        $logToFile = env('API_CLIENT_DEBUG_LOG_FILE', true);

        // Listen for HTTP request events
        Event::listen('Illuminate\Http\Client\Events\RequestSending', function ($event) use ($verbose, $logToFile) {
            $timestamp = now()->format('H:i:s.v');
            
            if ($verbose && (app()->runningInConsole() || env('API_CLIENT_DEBUG_WEB', false))) {
                echo "\n🌐 [{$timestamp}] → {$event->request->method()} {$event->request->url()}\n";
                
                // Show request data
                $body = $event->request->body();
                if (!empty($body)) {
                    $decoded = json_decode($body, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        echo "   📤 JSON: " . json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
                    } else {
                        echo "   📤 Body: " . (strlen($body) > 200 ? substr($body, 0, 200) . '...' : $body) . "\n";
                    }
                }
                
                // Show safe headers
                $headers = $event->request->headers();
                $safeHeaders = $this->filterSensitiveHeaders($headers);
                if (!empty($safeHeaders)) {
                    echo "   📋 Headers: " . json_encode($safeHeaders, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
                }
            }
            
            // Log to file if enabled
            if ($logToFile) {
                Log::channel(env('API_CLIENT_DEBUG_LOG_CHANNEL', 'single'))->debug('HTTP Request Sending', [
                    'method' => $event->request->method(),
                    'url' => $event->request->url(),
                    'body' => $event->request->body(),
                    'headers' => $this->filterSensitiveHeaders($event->request->headers()),
                    'timestamp' => $timestamp,
                ]);
            }
        });

        // Listen for HTTP response events
        Event::listen('Illuminate\Http\Client\Events\ResponseReceived', function ($event) use ($verbose, $logToFile) {
            $timestamp = now()->format('H:i:s.v');
            $status = $event->response->status();
            $statusEmoji = $status >= 200 && $status < 300 ? '✅' : ($status >= 400 ? '❌' : '⚠️');
            
            // Get raw response data
            $rawBody = $event->response->body();
            $headers = $event->response->headers();
            $json = $event->response->json();
            $responseTime = $event->response->transferStats ? 
                round($event->response->transferStats->getTransferTime() * 1000, 2) : null;
            
            if ($verbose && (app()->runningInConsole() || env('API_CLIENT_DEBUG_WEB', false))) {
                echo "📡 [{$timestamp}] ← {$statusEmoji} {$status}";
                if ($responseTime) {
                    echo " ({$responseTime}ms)";
                }
                echo "\n";
                
                // Show response headers if enabled
                if (env('API_CLIENT_DEBUG_SHOW_HEADERS', false)) {
                    echo "   📋 Response Headers:\n";
                    foreach ($this->filterSensitiveHeaders($headers) as $key => $values) {
                        $value = is_array($values) ? implode(', ', $values) : $values;
                        echo "      {$key}: {$value}\n";
                    }
                }
                
                // Show response data
                if ($json) {
                    $preview = is_array($json) ? (count($json) . ' items') : 'object';
                    echo "   📥 Response: {$preview}\n";
                    
                    $maxResponseSize = env('API_CLIENT_DEBUG_MAX_RESPONSE_SIZE', 1000);
                    if (strlen(json_encode($json)) <= $maxResponseSize) {
                        echo "   📄 JSON Data: " . json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
                    } else {
                        echo "   📄 JSON Data: [Large response - " . strlen($rawBody) . " chars]\n";
                    }
                } else {
                    $maxBodySize = env('API_CLIENT_DEBUG_MAX_BODY_SIZE', 200);
                    if (strlen($rawBody) <= $maxBodySize) {
                        echo "   📄 Raw Body: {$rawBody}\n";
                    } else {
                        echo "   📄 Raw Body: " . substr($rawBody, 0, $maxBodySize) . "... (" . strlen($rawBody) . " chars)\n";
                    }
                }
                
                // Show raw response if specifically enabled
                if (env('API_CLIENT_DEBUG_SHOW_RAW', false) && $rawBody) {
                    $maxRawSize = env('API_CLIENT_DEBUG_MAX_RAW_SIZE', 2000);
                    echo "   🔍 Raw Response:\n";
                    if (strlen($rawBody) <= $maxRawSize) {
                        echo "   " . str_replace("\n", "\n   ", $rawBody) . "\n";
                    } else {
                        echo "   " . str_replace("\n", "\n   ", substr($rawBody, 0, $maxRawSize)) . "\n   ... [truncated - total " . strlen($rawBody) . " chars]\n";
                    }
                }
                
                echo "   ────────────────────────────────────────\n";
            }
            
            // Enhanced file logging with raw response data
            if ($logToFile) {
                $logData = [
                    'status' => $status,
                    'status_text' => $event->response->reason(),
                    'headers' => $this->filterSensitiveHeaders($headers),
                    'body_size' => strlen($rawBody),
                    'timestamp' => $timestamp,
                ];
                
                // Add response time if available
                if ($responseTime) {
                    $logData['response_time_ms'] = $responseTime;
                }
                
                // Add JSON data if parseable
                if ($json) {
                    $logData['json_data'] = $json;
                }
                
                // Always log raw body if enabled or if it's small enough
                $maxLogSize = env('API_CLIENT_DEBUG_MAX_LOG_SIZE', 5000);
                if (env('API_CLIENT_DEBUG_LOG_RAW_RESPONSE', true) && strlen($rawBody) <= $maxLogSize) {
                    $logData['raw_body'] = $rawBody;
                } elseif (strlen($rawBody) > $maxLogSize) {
                    $logData['raw_body_preview'] = substr($rawBody, 0, 500) . '... [truncated]';
                    $logData['raw_body_full_size'] = strlen($rawBody);
                }
                
                Log::channel(env('API_CLIENT_DEBUG_LOG_CHANNEL', 'single'))->debug('HTTP Response Received', $logData);
            }
        });
    }

    /**
     * Enable database query debugging if requested
     */
    protected function enableQueryDebug(): void
    {
        if (env('API_CLIENT_DEBUG_QUERIES', false)) {
            DB::enableQueryLog();
            
            // Listen for query events
            Event::listen('Illuminate\Database\Events\QueryExecuted', function ($event) {
                if (app()->runningInConsole() || env('API_CLIENT_DEBUG_WEB', false)) {
                    echo "🗄️  [{$event->time}ms] {$event->sql}\n";
                    if (!empty($event->bindings)) {
                        echo "   Bindings: " . json_encode($event->bindings) . "\n";
                    }
                }
                
                Log::channel(env('API_CLIENT_DEBUG_LOG_CHANNEL', 'single'))->debug('Database Query', [
                    'sql' => $event->sql,
                    'bindings' => $event->bindings,
                    'time' => $event->time,
                ]);
            });
        }
    }

    /**
     * Setup debug configuration based on environment variables
     */
    protected function setupDebugConfiguration(): void
    {
        // Set API Client debug configuration
        config([
            'api-client.development.debug_mode' => true,
            'api-client.development.profiling' => env('API_CLIENT_PROFILING', true),
            'logging.channels.single.level' => env('API_CLIENT_LOG_LEVEL', 'debug'),
        ]);

        // Output debug status if in console
        if (app()->runningInConsole() && env('API_CLIENT_DEBUG_SHOW_STATUS', true)) {
            echo "🎯 API Debug Mode Auto-Enabled!\n";
            echo "📝 Environment Variables Detected:\n";
            echo "   - API_CLIENT_AUTO_DEBUG: " . (env('API_CLIENT_AUTO_DEBUG') ? '✅' : '❌') . "\n";
            echo "   - HTTP_CLIENT_DEBUG: " . (env('HTTP_CLIENT_DEBUG') ? '✅' : '❌') . "\n";
            echo "   - API_CLIENT_DEBUG_MODE: " . (env('API_CLIENT_DEBUG_MODE') ? '✅' : '❌') . "\n";
            echo "   - APP_DEBUG: " . (env('APP_DEBUG') ? '✅' : '❌') . "\n";
            echo "📊 Logs: " . storage_path('logs/laravel.log') . "\n\n";
        }
    }

    /**
     * Filter sensitive headers from debug output
     */
    protected function filterSensitiveHeaders(array $headers): array
    {
        $sensitiveKeys = [
            'authorization', 'cookie', 'x-api-key', 'api-key', 'x-auth-token', 
            'bearer', 'token', 'password', 'secret', 'key'
        ];

        return array_filter($headers, function($key) use ($sensitiveKeys) {
            return !in_array(strtolower($key), $sensitiveKeys);
        }, ARRAY_FILTER_USE_KEY);
    }
}
