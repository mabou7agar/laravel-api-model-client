# OpenAPI 3.0 Schema Parser

The Laravel API Model Client package now includes a comprehensive OpenAPI 3.0 schema parser that can automatically parse OpenAPI specifications and generate API models, validation rules, and endpoint mappings.

## Features

- ✅ **Parse OpenAPI JSON/YAML files** from local files or remote URLs
- ✅ **Extract endpoint definitions** with parameters, methods, and responses
- ✅ **Generate Laravel validation rules** from schema definitions
- ✅ **Create endpoint-to-model mappings** automatically
- ✅ **Support nested schemas and references** ($ref)
- ✅ **Schema caching** for improved performance
- ✅ **Comprehensive error handling** with detailed error reporting
- ✅ **Support for complex parameter types** (enums, arrays, objects)
- ✅ **Automatic model class generation**
- ✅ **Console command** for easy schema parsing

## Installation

The OpenAPI parser is included with the Laravel API Model Client package. Make sure you have the latest version:

```bash
composer require m-tech-stack/laravel-api-model-client
```

## Configuration

Publish the OpenAPI configuration file:

```bash
php artisan vendor:publish --tag=openapi-config
```

This will create `config/openapi.php` with the following options:

```php
return [
    'cache' => [
        'enabled' => true,
        'ttl' => 3600, // 1 hour
        'prefix' => 'openapi_schema_',
    ],
    'remote' => [
        'timeout' => 30,
        'max_file_size' => 10485760, // 10MB
    ],
    'supported_versions' => ['3.0.0', '3.0.1', '3.0.2', '3.0.3', '3.1.0'],
    'model_generation' => [
        'namespace' => 'App\\Models',
        'output_directory' => app_path('Models'),
        'overwrite_existing' => false,
    ],
    // ... more options
];
```

## Usage

### Basic Parsing

```php
use MTechStack\LaravelApiModelClient\OpenApi\OpenApiSchemaParser;

$parser = new OpenApiSchemaParser();

// Parse from local file
$result = $parser->parse('/path/to/openapi.json');

// Parse from remote URL
$result = $parser->parse('https://api.example.com/openapi.json');

// Parse without caching
$result = $parser->parse('/path/to/openapi.json', false);
```

### Using the Facade

```php
use MTechStack\LaravelApiModelClient\OpenApi\Facades\OpenApi;

$result = OpenApi::parse('/path/to/openapi.json');
```

### Console Command

Parse OpenAPI schema and optionally generate model classes:

```bash
# Basic parsing
php artisan api-model:parse-openapi /path/to/openapi.json

# Parse and generate models
php artisan api-model:parse-openapi /path/to/openapi.json --generate-models

# Dry run to see what would be generated
php artisan api-model:parse-openapi /path/to/openapi.json --generate-models --dry-run

# Custom output directory and namespace
php artisan api-model:parse-openapi /path/to/openapi.json \
    --generate-models \
    --output-dir=app/ApiModels \
    --namespace="App\\ApiModels"

# Overwrite existing files
php artisan api-model:parse-openapi /path/to/openapi.json \
    --generate-models \
    --overwrite
```

## Parsed Result Structure

The parser returns a comprehensive array with the following structure:

```php
[
    'info' => [
        'title' => 'Pet Store API',
        'version' => '1.0.0',
        'description' => 'A simple pet store API',
        // ... more info
    ],
    'endpoints' => [
        'get_pets' => [
            'operation_id' => 'get_pets',
            'path' => '/pets',
            'method' => 'GET',
            'summary' => 'List all pets',
            'parameters' => [...],
            'responses' => [...],
            // ... more endpoint details
        ],
        // ... more endpoints
    ],
    'schemas' => [
        'Pet' => [
            'type' => 'object',
            'properties' => [...],
            'required' => [...],
            // ... more schema details
        ],
        // ... more schemas
    ],
    'model_mappings' => [
        'Pet' => [
            'model_name' => 'Pet',
            'base_endpoint' => '/pets',
            'operations' => [...],
            'attributes' => [...],
            'relationships' => [...],
        ],
        // ... more model mappings
    ],
    'validation_rules' => [
        'schemas' => [...],
        'endpoints' => [...],
    ],
    'servers' => [...],
    'security' => [...],
]
```

## Working with Parsed Data

### Get Validation Rules

```php
$parser = new OpenApiSchemaParser();
$parser->parse('/path/to/openapi.json');

// Get validation rules for a specific endpoint
$rules = $parser->getValidationRulesForEndpoint('get_pets');
// Returns: ['limit' => ['integer', 'min:1'], 'offset' => ['integer', 'min:0']]

// Get validation rules for a specific schema
$rules = $parser->getValidationRulesForSchema('Pet');
// Returns: ['id' => ['required', 'integer'], 'name' => ['required', 'string']]
```

### Get Model Information

```php
// Get all model names
$modelNames = $parser->getModelNames();
// Returns: ['Pet', 'User', 'Order']

// Get model mapping
$petMapping = $parser->getModelMapping('Pet');

// Get model operations
$operations = $parser->getModelOperations('Pet');
// Returns operations like: index, store, show, update, destroy

// Get model attributes
$attributes = $parser->getModelAttributes('Pet');
// Returns attribute definitions with types, validation, etc.

// Get model relationships
$relationships = $parser->getModelRelationships('Pet');
// Returns detected relationships like belongsTo, hasMany, etc.
```

### Generate Model Classes

```php
// Generate model class code
$modelCode = $parser->generateModelClass('Pet');

// The generated code will look like:
/*
<?php

namespace App\Models;

use MTechStack\LaravelApiModelClient\Models\ApiModel;

class Pet extends ApiModel
{
    protected $baseEndpoint = '/pets';
    
    protected $fillable = [
        'name',
        'tag',
        'status',
    ];
    
    protected $casts = [
        'id' => 'integer',
    ];
    
    // Relationship methods...
}
*/
```

## Advanced Features

### Custom Configuration

```php
$parser = new OpenApiSchemaParser([
    'cache_enabled' => false,
    'remote_timeout' => 60,
    'max_file_size' => 20971520, // 20MB
    'supported_versions' => ['3.0.0', '3.1.0'],
]);
```

### Handling Complex Schemas

The parser supports:

- **References ($ref)**: Automatically resolves schema references
- **Composition keywords**: allOf, anyOf, oneOf
- **Nested objects**: Deep object structures
- **Arrays**: With typed items
- **Enums**: Converted to Laravel validation rules
- **Format validation**: email, url, date, uuid, etc.

### Error Handling

```php
use MTechStack\LaravelApiModelClient\OpenApi\Exceptions\OpenApiParsingException;
use MTechStack\LaravelApiModelClient\OpenApi\Exceptions\SchemaValidationException;

try {
    $result = $parser->parse('/path/to/openapi.json');
} catch (SchemaValidationException $e) {
    // Handle unsupported OpenAPI version or invalid schema
    echo "Schema validation error: " . $e->getMessage();
} catch (OpenApiParsingException $e) {
    // Handle parsing errors (file not found, network issues, etc.)
    echo "Parsing error: " . $e->getMessage();
}
```

## Integration with Existing Models

The generated models extend the existing `ApiModel` class and are fully compatible with all existing features:

```php
// Generated Pet model works with all existing features
$pets = Pet::all();
$pet = Pet::find(1);
$pet = Pet::create(['name' => 'Fluffy', 'status' => 'available']);

// Validation rules are automatically available
$request->validate(OpenApi::getValidationRulesForEndpoint('post_pets'));
```

## Examples

### Parsing Swagger Petstore

```php
$parser = new OpenApiSchemaParser();
$result = $parser->parse('https://petstore3.swagger.io/api/v3/openapi.json');

// Generate Pet model
$petCode = $parser->generateModelClass('Pet');
file_put_contents(app_path('Models/Pet.php'), $petCode);

// Use validation rules in controller
public function store(Request $request)
{
    $rules = OpenApi::getValidationRulesForEndpoint('addPet', 'application/json');
    $request->validate($rules);
    
    return Pet::create($request->all());
}
```

### Custom API Integration

```php
// Parse your custom API
$result = $parser->parse('/path/to/your-api-spec.json');

// Generate all models
foreach ($parser->getModelNames() as $modelName) {
    $modelCode = $parser->generateModelClass($modelName);
    $filename = app_path("Models/{$modelName}.php");
    file_put_contents($filename, $modelCode);
}
```

## Supported OpenAPI Features

### ✅ Fully Supported
- OpenAPI 3.0.x and 3.1.0
- JSON and YAML formats
- Local files and remote URLs
- Path parameters, query parameters, headers
- Request bodies (JSON, form data)
- Response schemas
- Component schemas with $ref
- Data types: string, integer, number, boolean, array, object
- String formats: email, url, date, date-time, uuid, etc.
- Validation constraints: min/max, length, pattern, enum
- Composition: allOf, anyOf, oneOf
- Security schemes

### ⚠️ Partially Supported
- Callbacks (basic support)
- Links (basic support)
- Discriminator (basic support)

### ❌ Not Supported
- OpenAPI 2.0 (Swagger 2.0)
- XML content types
- Complex authentication flows
- Custom string formats (without configuration)

## Performance

- **Caching**: Parsed schemas are cached for improved performance
- **Memory efficient**: Streams large files instead of loading entirely into memory
- **Lazy loading**: Components are parsed on-demand
- **Configurable limits**: File size and timeout limits prevent resource exhaustion

## Contributing

Found a bug or want to add a feature? Please check our [contribution guidelines](CONTRIBUTING.md) and submit a pull request!

## License

This package is open-sourced software licensed under the [MIT license](LICENSE).
