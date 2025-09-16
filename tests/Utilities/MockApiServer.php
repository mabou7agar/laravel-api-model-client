<?php

namespace MTechStack\LaravelApiModelClient\Tests\Utilities;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Mock API server for testing OpenAPI integration
 */
class MockApiServer
{
    protected array $config;
    protected array $routes = [];
    protected bool $running = false;
    protected ?int $processId = null;

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'host' => 'localhost',
            'port' => 8080,
            'routes_file' => null,
            'log_requests' => true,
            'response_delay' => 0, // ms
        ], $config);

        $this->loadRoutes();
    }

    /**
     * Start the mock server
     */
    public function start(): self
    {
        if ($this->running) {
            return $this;
        }

        // For testing, we'll use HTTP fake instead of actual server
        $this->setupHttpFakes();
        $this->running = true;

        return $this;
    }

    /**
     * Stop the mock server
     */
    public function stop(): self
    {
        if (!$this->running) {
            return $this;
        }

        Http::preventStrayRequests(false);
        $this->running = false;

        return $this;
    }

    /**
     * Add a route to the mock server
     */
    public function addRoute(string $method, string $path, array $response, int $statusCode = 200): self
    {
        $this->routes[] = [
            'method' => strtoupper($method),
            'path' => $path,
            'response' => $response,
            'status_code' => $statusCode,
            'headers' => ['Content-Type' => 'application/json']
        ];

        if ($this->running) {
            $this->setupHttpFakes();
        }

        return $this;
    }

    /**
     * Add multiple routes at once
     */
    public function addRoutes(array $routes): self
    {
        foreach ($routes as $route) {
            $this->addRoute(
                $route['method'],
                $route['path'],
                $route['response'],
                $route['status_code'] ?? 200
            );
        }

        return $this;
    }

    /**
     * Setup HTTP fakes for Laravel testing
     */
    protected function setupHttpFakes(): void
    {
        $fakes = [];

        foreach ($this->routes as $route) {
            $url = $this->buildUrl($route['path']);
            $fakes[$url] = Http::response(
                $route['response'],
                $route['status_code'],
                $route['headers']
            );
        }

        Http::fake($fakes);
    }

    /**
     * Build full URL from path
     */
    protected function buildUrl(string $path): string
    {
        $baseUrl = "http://{$this->config['host']}:{$this->config['port']}";
        return $baseUrl . (Str::startsWith($path, '/') ? $path : '/' . $path);
    }

    /**
     * Load routes from configuration file
     */
    protected function loadRoutes(): void
    {
        if (!$this->config['routes_file'] || !File::exists($this->config['routes_file'])) {
            $this->loadDefaultRoutes();
            return;
        }

        $routesData = json_decode(File::get($this->config['routes_file']), true);
        if (json_last_error() === JSON_ERROR_NONE && isset($routesData['routes'])) {
            $this->routes = $routesData['routes'];
        } else {
            $this->loadDefaultRoutes();
        }
    }

    /**
     * Load default routes for testing
     */
    protected function loadDefaultRoutes(): void
    {
        $this->routes = [
            // Pet Store API routes
            [
                'method' => 'GET',
                'path' => '/api/v1/pets',
                'response' => [
                    'data' => [
                        [
                            'id' => 1,
                            'name' => 'Fluffy',
                            'status' => 'available',
                            'category' => ['id' => 1, 'name' => 'Dogs'],
                            'tags' => [['id' => 1, 'name' => 'friendly']]
                        ],
                        [
                            'id' => 2,
                            'name' => 'Whiskers',
                            'status' => 'pending',
                            'category' => ['id' => 2, 'name' => 'Cats'],
                            'tags' => [['id' => 2, 'name' => 'playful']]
                        ]
                    ],
                    'meta' => [
                        'total' => 2,
                        'per_page' => 10,
                        'current_page' => 1
                    ]
                ],
                'status_code' => 200
            ],
            [
                'method' => 'GET',
                'path' => '/api/v1/pets/1',
                'response' => [
                    'data' => [
                        'id' => 1,
                        'name' => 'Fluffy',
                        'status' => 'available',
                        'category' => ['id' => 1, 'name' => 'Dogs'],
                        'tags' => [['id' => 1, 'name' => 'friendly']]
                    ]
                ],
                'status_code' => 200
            ],
            [
                'method' => 'POST',
                'path' => '/api/v1/pets',
                'response' => [
                    'data' => [
                        'id' => 3,
                        'name' => 'New Pet',
                        'status' => 'available',
                        'category' => ['id' => 1, 'name' => 'Dogs'],
                        'tags' => []
                    ]
                ],
                'status_code' => 201
            ],
            [
                'method' => 'PUT',
                'path' => '/api/v1/pets/1',
                'response' => [
                    'data' => [
                        'id' => 1,
                        'name' => 'Updated Pet',
                        'status' => 'sold',
                        'category' => ['id' => 1, 'name' => 'Dogs'],
                        'tags' => [['id' => 1, 'name' => 'friendly']]
                    ]
                ],
                'status_code' => 200
            ],
            [
                'method' => 'DELETE',
                'path' => '/api/v1/pets/1',
                'response' => ['message' => 'Pet deleted successfully'],
                'status_code' => 204
            ],
            // Error responses
            [
                'method' => 'GET',
                'path' => '/api/v1/pets/999',
                'response' => [
                    'error' => [
                        'code' => 'NOT_FOUND',
                        'message' => 'Pet not found'
                    ]
                ],
                'status_code' => 404
            ],
            [
                'method' => 'POST',
                'path' => '/api/v1/pets/invalid',
                'response' => [
                    'error' => [
                        'code' => 'VALIDATION_ERROR',
                        'message' => 'Validation failed',
                        'details' => [
                            'name' => ['The name field is required.']
                        ]
                    ]
                ],
                'status_code' => 422
            ],
            // Authentication test routes
            [
                'method' => 'GET',
                'path' => '/api/v1/protected',
                'response' => [
                    'error' => [
                        'code' => 'UNAUTHORIZED',
                        'message' => 'Authentication required'
                    ]
                ],
                'status_code' => 401
            ],
            // Rate limiting test route
            [
                'method' => 'GET',
                'path' => '/api/v1/rate-limited',
                'response' => [
                    'error' => [
                        'code' => 'RATE_LIMIT_EXCEEDED',
                        'message' => 'Too many requests'
                    ]
                ],
                'status_code' => 429
            ],
            // Server error test route
            [
                'method' => 'GET',
                'path' => '/api/v1/server-error',
                'response' => [
                    'error' => [
                        'code' => 'INTERNAL_SERVER_ERROR',
                        'message' => 'Something went wrong'
                    ]
                ],
                'status_code' => 500
            ]
        ];
    }

    /**
     * Create mock response for specific endpoint
     */
    public function createMockResponse(string $endpoint, array $data, int $statusCode = 200): array
    {
        return [
            'endpoint' => $endpoint,
            'data' => $data,
            'status_code' => $statusCode,
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Mock-Server' => 'true',
                'X-Request-Id' => Str::uuid()->toString()
            ],
            'timestamp' => now()->toISOString()
        ];
    }

    /**
     * Generate realistic mock data for schema
     */
    public function generateMockData(array $schema, int $count = 1): array
    {
        $generator = new MockDataGenerator();
        return $generator->generate($schema, $count);
    }

    /**
     * Add performance test routes with delays
     */
    public function addPerformanceTestRoutes(): self
    {
        // Fast response
        $this->addRoute('GET', '/api/v1/fast', ['message' => 'Fast response'], 200);
        
        // Slow response (simulated)
        $this->addRoute('GET', '/api/v1/slow', ['message' => 'Slow response'], 200);
        
        // Large payload
        $largeData = array_fill(0, 1000, [
            'id' => rand(1, 1000),
            'name' => 'Item ' . rand(1, 1000),
            'data' => str_repeat('x', 100)
        ]);
        $this->addRoute('GET', '/api/v1/large', ['data' => $largeData], 200);

        return $this;
    }

    /**
     * Add validation test routes
     */
    public function addValidationTestRoutes(): self
    {
        // Valid data
        $this->addRoute('POST', '/api/v1/validate/valid', [
            'message' => 'Validation passed',
            'data' => ['id' => 1, 'name' => 'Valid Item']
        ], 200);

        // Invalid data
        $this->addRoute('POST', '/api/v1/validate/invalid', [
            'error' => [
                'code' => 'VALIDATION_ERROR',
                'message' => 'Validation failed',
                'details' => [
                    'name' => ['The name field is required.'],
                    'email' => ['The email field must be a valid email address.']
                ]
            ]
        ], 422);

        return $this;
    }

    /**
     * Get server configuration
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Get all registered routes
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }

    /**
     * Check if server is running
     */
    public function isRunning(): bool
    {
        return $this->running;
    }

    /**
     * Get base URL
     */
    public function getBaseUrl(): string
    {
        return "http://{$this->config['host']}:{$this->config['port']}";
    }

    /**
     * Reset all routes
     */
    public function resetRoutes(): self
    {
        $this->routes = [];
        if ($this->running) {
            $this->setupHttpFakes();
        }
        return $this;
    }

    /**
     * Add OpenAPI specification endpoint
     */
    public function addOpenApiSpecRoute(array $spec): self
    {
        $this->addRoute('GET', '/openapi.json', $spec, 200);
        $this->addRoute('GET', '/swagger.json', $spec, 200);
        return $this;
    }
}
