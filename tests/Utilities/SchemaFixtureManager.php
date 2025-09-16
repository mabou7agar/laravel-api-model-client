<?php

namespace MTechStack\LaravelApiModelClient\Tests\Utilities;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Manages OpenAPI schema fixtures for testing
 */
class SchemaFixtureManager
{
    protected string $fixturesPath;
    protected array $loadedSchemas = [];

    public function __construct(string $fixturesPath)
    {
        $this->fixturesPath = $fixturesPath;
        $this->ensureFixturesDirectory();
        $this->createDefaultFixtures();
    }

    /**
     * Get a schema fixture by name
     */
    public function getSchema(string $name): array
    {
        if (isset($this->loadedSchemas[$name])) {
            return $this->loadedSchemas[$name];
        }

        $schemaPath = $this->getSchemaPath($name);
        if (!File::exists($schemaPath)) {
            throw new \InvalidArgumentException("Schema fixture '{$name}' not found at: {$schemaPath}");
        }

        $content = File::get($schemaPath);
        $schema = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException("Invalid JSON in schema fixture '{$name}': " . json_last_error_msg());
        }

        $this->loadedSchemas[$name] = $schema;
        return $schema;
    }

    /**
     * Save a schema fixture
     */
    public function saveSchema(string $name, array $schema): void
    {
        $schemaPath = $this->getSchemaPath($name);
        $content = json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        
        File::put($schemaPath, $content);
        $this->loadedSchemas[$name] = $schema;
    }

    /**
     * Get all available schema fixtures
     */
    public function getAvailableSchemas(): array
    {
        $schemasPath = $this->fixturesPath . '/schemas';
        if (!File::exists($schemasPath)) {
            return [];
        }

        $files = File::files($schemasPath);
        $schemas = [];

        foreach ($files as $file) {
            if ($file->getExtension() === 'json') {
                $name = $file->getFilenameWithoutExtension();
                $schemas[] = $name;
            }
        }

        return $schemas;
    }

    /**
     * Create a petstore schema for testing
     */
    public function createPetstoreSchema(string $version = '3.0.0'): array
    {
        return [
            'openapi' => $version,
            'info' => [
                'title' => 'Swagger Petstore',
                'description' => 'This is a sample server Petstore server.',
                'version' => '1.0.0',
                'license' => [
                    'name' => 'MIT'
                ]
            ],
            'servers' => [
                ['url' => 'http://petstore.swagger.io/v1']
            ],
            'paths' => [
                '/pets' => [
                    'get' => [
                        'summary' => 'List all pets',
                        'operationId' => 'listPets',
                        'tags' => ['pets'],
                        'parameters' => [
                            [
                                'name' => 'limit',
                                'in' => 'query',
                                'description' => 'How many items to return at one time (max 100)',
                                'required' => false,
                                'schema' => [
                                    'type' => 'integer',
                                    'maximum' => 100,
                                    'format' => 'int32'
                                ]
                            ]
                        ],
                        'responses' => [
                            '200' => [
                                'description' => 'A paged array of pets',
                                'headers' => [
                                    'x-next' => [
                                        'description' => 'A link to the next page of responses',
                                        'schema' => ['type' => 'string']
                                    ]
                                ],
                                'content' => [
                                    'application/json' => [
                                        'schema' => [
                                            'type' => 'array',
                                            'items' => ['$ref' => '#/components/schemas/Pet']
                                        ]
                                    ]
                                ]
                            ],
                            'default' => [
                                'description' => 'unexpected error',
                                'content' => [
                                    'application/json' => [
                                        'schema' => ['$ref' => '#/components/schemas/Error']
                                    ]
                                ]
                            ]
                        ]
                    ],
                    'post' => [
                        'summary' => 'Create a pet',
                        'operationId' => 'createPets',
                        'tags' => ['pets'],
                        'requestBody' => [
                            'required' => true,
                            'content' => [
                                'application/json' => [
                                    'schema' => ['$ref' => '#/components/schemas/Pet']
                                ]
                            ]
                        ],
                        'responses' => [
                            '201' => [
                                'description' => 'Null response'
                            ],
                            'default' => [
                                'description' => 'unexpected error',
                                'content' => [
                                    'application/json' => [
                                        'schema' => ['$ref' => '#/components/schemas/Error']
                                    ]
                                ]
                            ]
                        ]
                    ]
                ],
                '/pets/{petId}' => [
                    'get' => [
                        'summary' => 'Info for a specific pet',
                        'operationId' => 'showPetById',
                        'tags' => ['pets'],
                        'parameters' => [
                            [
                                'name' => 'petId',
                                'in' => 'path',
                                'required' => true,
                                'description' => 'The id of the pet to retrieve',
                                'schema' => [
                                    'type' => 'string'
                                ]
                            ]
                        ],
                        'responses' => [
                            '200' => [
                                'description' => 'Expected response to a valid request',
                                'content' => [
                                    'application/json' => [
                                        'schema' => ['$ref' => '#/components/schemas/Pet']
                                    ]
                                ]
                            ],
                            'default' => [
                                'description' => 'unexpected error',
                                'content' => [
                                    'application/json' => [
                                        'schema' => ['$ref' => '#/components/schemas/Error']
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            'components' => [
                'schemas' => [
                    'Pet' => [
                        'type' => 'object',
                        'required' => ['id', 'name'],
                        'properties' => [
                            'id' => [
                                'type' => 'integer',
                                'format' => 'int64'
                            ],
                            'name' => [
                                'type' => 'string'
                            ],
                            'tag' => [
                                'type' => 'string'
                            ]
                        ]
                    ],
                    'Error' => [
                        'type' => 'object',
                        'required' => ['code', 'message'],
                        'properties' => [
                            'code' => [
                                'type' => 'integer',
                                'format' => 'int32'
                            ],
                            'message' => [
                                'type' => 'string'
                            ]
                        ]
                    ]
                ]
            ]
        ];
    }

    /**
     * Create an e-commerce API schema for testing
     */
    public function createEcommerceSchema(): array
    {
        return [
            'openapi' => '3.0.0',
            'info' => [
                'title' => 'E-commerce API',
                'description' => 'A comprehensive e-commerce API for testing',
                'version' => '2.0.0'
            ],
            'servers' => [
                ['url' => 'https://api.ecommerce.com/v2']
            ],
            'paths' => [
                '/products' => [
                    'get' => [
                        'summary' => 'List products',
                        'operationId' => 'listProducts',
                        'parameters' => [
                            [
                                'name' => 'category',
                                'in' => 'query',
                                'schema' => ['type' => 'string']
                            ],
                            [
                                'name' => 'price_min',
                                'in' => 'query',
                                'schema' => ['type' => 'number', 'format' => 'float']
                            ],
                            [
                                'name' => 'price_max',
                                'in' => 'query',
                                'schema' => ['type' => 'number', 'format' => 'float']
                            ],
                            [
                                'name' => 'in_stock',
                                'in' => 'query',
                                'schema' => ['type' => 'boolean']
                            ]
                        ],
                        'responses' => [
                            '200' => [
                                'description' => 'List of products',
                                'content' => [
                                    'application/json' => [
                                        'schema' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'data' => [
                                                    'type' => 'array',
                                                    'items' => ['$ref' => '#/components/schemas/Product']
                                                ],
                                                'meta' => ['$ref' => '#/components/schemas/PaginationMeta']
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ],
                    'post' => [
                        'summary' => 'Create product',
                        'operationId' => 'createProduct',
                        'requestBody' => [
                            'required' => true,
                            'content' => [
                                'application/json' => [
                                    'schema' => ['$ref' => '#/components/schemas/ProductInput']
                                ]
                            ]
                        ],
                        'responses' => [
                            '201' => [
                                'description' => 'Product created',
                                'content' => [
                                    'application/json' => [
                                        'schema' => ['$ref' => '#/components/schemas/Product']
                                    ]
                                ]
                            ]
                        ]
                    ]
                ],
                '/orders' => [
                    'get' => [
                        'summary' => 'List orders',
                        'operationId' => 'listOrders',
                        'responses' => [
                            '200' => [
                                'description' => 'List of orders',
                                'content' => [
                                    'application/json' => [
                                        'schema' => [
                                            'type' => 'array',
                                            'items' => ['$ref' => '#/components/schemas/Order']
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            'components' => [
                'schemas' => [
                    'Product' => [
                        'type' => 'object',
                        'required' => ['id', 'name', 'price'],
                        'properties' => [
                            'id' => ['type' => 'integer'],
                            'name' => ['type' => 'string', 'maxLength' => 255],
                            'description' => ['type' => 'string'],
                            'price' => ['type' => 'number', 'format' => 'float', 'minimum' => 0],
                            'category' => ['$ref' => '#/components/schemas/Category'],
                            'tags' => [
                                'type' => 'array',
                                'items' => ['$ref' => '#/components/schemas/Tag']
                            ],
                            'in_stock' => ['type' => 'boolean'],
                            'stock_quantity' => ['type' => 'integer', 'minimum' => 0],
                            'created_at' => ['type' => 'string', 'format' => 'date-time'],
                            'updated_at' => ['type' => 'string', 'format' => 'date-time']
                        ]
                    ],
                    'ProductInput' => [
                        'type' => 'object',
                        'required' => ['name', 'price'],
                        'properties' => [
                            'name' => ['type' => 'string', 'maxLength' => 255],
                            'description' => ['type' => 'string'],
                            'price' => ['type' => 'number', 'format' => 'float', 'minimum' => 0],
                            'category_id' => ['type' => 'integer'],
                            'tag_ids' => [
                                'type' => 'array',
                                'items' => ['type' => 'integer']
                            ],
                            'stock_quantity' => ['type' => 'integer', 'minimum' => 0]
                        ]
                    ],
                    'Category' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'integer'],
                            'name' => ['type' => 'string'],
                            'slug' => ['type' => 'string']
                        ]
                    ],
                    'Tag' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'integer'],
                            'name' => ['type' => 'string']
                        ]
                    ],
                    'Order' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'integer'],
                            'customer_id' => ['type' => 'integer'],
                            'status' => [
                                'type' => 'string',
                                'enum' => ['pending', 'processing', 'shipped', 'delivered', 'cancelled']
                            ],
                            'total' => ['type' => 'number', 'format' => 'float'],
                            'items' => [
                                'type' => 'array',
                                'items' => ['$ref' => '#/components/schemas/OrderItem']
                            ],
                            'created_at' => ['type' => 'string', 'format' => 'date-time']
                        ]
                    ],
                    'OrderItem' => [
                        'type' => 'object',
                        'properties' => [
                            'product_id' => ['type' => 'integer'],
                            'quantity' => ['type' => 'integer', 'minimum' => 1],
                            'price' => ['type' => 'number', 'format' => 'float']
                        ]
                    ],
                    'PaginationMeta' => [
                        'type' => 'object',
                        'properties' => [
                            'current_page' => ['type' => 'integer'],
                            'per_page' => ['type' => 'integer'],
                            'total' => ['type' => 'integer'],
                            'last_page' => ['type' => 'integer']
                        ]
                    ]
                ],
                'securitySchemes' => [
                    'bearerAuth' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                        'bearerFormat' => 'JWT'
                    ]
                ]
            ],
            'security' => [
                ['bearerAuth' => []]
            ]
        ];
    }

    /**
     * Create a microservices API schema for testing
     */
    public function createMicroservicesSchema(): array
    {
        return [
            'openapi' => '3.1.0',
            'info' => [
                'title' => 'User Management Microservice',
                'description' => 'Microservice for user management with advanced features',
                'version' => '1.0.0'
            ],
            'servers' => [
                ['url' => 'https://users.microservice.com/api/v1']
            ],
            'paths' => [
                '/users' => [
                    'get' => [
                        'summary' => 'List users',
                        'operationId' => 'listUsers',
                        'parameters' => [
                            [
                                'name' => 'filter',
                                'in' => 'query',
                                'style' => 'deepObject',
                                'explode' => true,
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'email' => ['type' => 'string', 'format' => 'email'],
                                        'status' => ['type' => 'string', 'enum' => ['active', 'inactive']],
                                        'created_after' => ['type' => 'string', 'format' => 'date']
                                    ]
                                ]
                            ]
                        ],
                        'responses' => [
                            '200' => [
                                'description' => 'Users list',
                                'content' => [
                                    'application/json' => [
                                        'schema' => [
                                            'type' => 'array',
                                            'items' => ['$ref' => '#/components/schemas/User']
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            'components' => [
                'schemas' => [
                    'User' => [
                        'type' => 'object',
                        'required' => ['id', 'email'],
                        'properties' => [
                            'id' => ['type' => 'string', 'format' => 'uuid'],
                            'email' => ['type' => 'string', 'format' => 'email'],
                            'name' => ['type' => 'string'],
                            'status' => ['type' => 'string', 'enum' => ['active', 'inactive']],
                            'metadata' => [
                                'type' => 'object',
                                'additionalProperties' => true
                            ],
                            'created_at' => ['type' => 'string', 'format' => 'date-time'],
                            'updated_at' => ['type' => 'string', 'format' => 'date-time']
                        ]
                    ]
                ]
            ]
        ];
    }

    /**
     * Get schema path for a given name
     */
    protected function getSchemaPath(string $name): string
    {
        return $this->fixturesPath . '/schemas/' . $name . '.json';
    }

    /**
     * Ensure fixtures directory exists
     */
    protected function ensureFixturesDirectory(): void
    {
        $schemasPath = $this->fixturesPath . '/schemas';
        if (!File::exists($schemasPath)) {
            File::makeDirectory($schemasPath, 0755, true);
        }
    }

    /**
     * Create default fixture files
     */
    protected function createDefaultFixtures(): void
    {
        $defaultSchemas = [
            'petstore-3.0.0' => $this->createPetstoreSchema('3.0.0'),
            'petstore-3.0.1' => $this->createPetstoreSchema('3.0.1'),
            'petstore-3.0.2' => $this->createPetstoreSchema('3.0.2'),
            'petstore-3.0.3' => $this->createPetstoreSchema('3.0.3'),
            'petstore-3.1.0' => $this->createPetstoreSchema('3.1.0'),
            'ecommerce' => $this->createEcommerceSchema(),
            'microservices' => $this->createMicroservicesSchema(),
        ];

        foreach ($defaultSchemas as $name => $schema) {
            $schemaPath = $this->getSchemaPath($name);
            if (!File::exists($schemaPath)) {
                $this->saveSchema($name, $schema);
            }
        }
    }

    /**
     * Create invalid schema for testing error handling
     */
    public function createInvalidSchema(): array
    {
        return [
            'openapi' => '3.0.0',
            'info' => [
                'title' => 'Invalid Schema',
                // Missing required 'version' field
            ],
            'paths' => [
                '/invalid' => [
                    'get' => [
                        // Missing required 'responses' field
                        'summary' => 'Invalid endpoint'
                    ]
                ]
            ]
        ];
    }

    /**
     * Get fixture for testing edge cases
     */
    public function getEdgeCaseFixtures(): array
    {
        return [
            'empty_schema' => [],
            'minimal_schema' => [
                'openapi' => '3.0.0',
                'info' => ['title' => 'Minimal', 'version' => '1.0.0'],
                'paths' => []
            ],
            'large_schema' => $this->createLargeSchema(),
            'complex_nested' => $this->createComplexNestedSchema(),
        ];
    }

    /**
     * Create a large schema for performance testing
     */
    protected function createLargeSchema(): array
    {
        $schema = $this->createPetstoreSchema();
        
        // Add many paths and schemas
        for ($i = 1; $i <= 100; $i++) {
            $schema['paths']["/resource{$i}"] = [
                'get' => [
                    'summary' => "Get resource {$i}",
                    'responses' => [
                        '200' => [
                            'description' => 'Success',
                            'content' => [
                                'application/json' => [
                                    'schema' => ['$ref' => "#/components/schemas/Resource{$i}"]
                                ]
                            ]
                        ]
                    ]
                ]
            ];
            
            $schema['components']['schemas']["Resource{$i}"] = [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'name' => ['type' => 'string'],
                    'data' => ['type' => 'string']
                ]
            ];
        }
        
        return $schema;
    }

    /**
     * Create complex nested schema for testing
     */
    protected function createComplexNestedSchema(): array
    {
        return [
            'openapi' => '3.0.0',
            'info' => ['title' => 'Complex Nested', 'version' => '1.0.0'],
            'paths' => [
                '/complex' => [
                    'post' => [
                        'requestBody' => [
                            'content' => [
                                'application/json' => [
                                    'schema' => ['$ref' => '#/components/schemas/ComplexObject']
                                ]
                            ]
                        ],
                        'responses' => ['200' => ['description' => 'OK']]
                    ]
                ]
            ],
            'components' => [
                'schemas' => [
                    'ComplexObject' => [
                        'type' => 'object',
                        'properties' => [
                            'nested' => [
                                'type' => 'object',
                                'properties' => [
                                    'deep' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'deeper' => [
                                                'type' => 'array',
                                                'items' => [
                                                    'type' => 'object',
                                                    'properties' => [
                                                        'value' => ['type' => 'string']
                                                    ]
                                                ]
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];
    }
}
