# OpenAPI Integration Guide

## Overview

The Laravel API Model Client now includes comprehensive OpenAPI 3.0 integration, enabling automatic schema parsing, parameter validation, relationship detection, and dynamic query building based on OpenAPI specifications.

## Quick Start

### 1. Enable OpenAPI Support

Add the `HasOpenApiSchema` trait to your ApiModel:

```php
<?php

namespace App\Models;

use MTechStack\LaravelApiModelClient\Models\ApiModel;
use MTechStack\LaravelApiModelClient\Traits\HasOpenApiSchema;

class Pet extends ApiModel
{
    use HasOpenApiSchema;
    
    protected string $openApiSchemaSource = '/path/to/petstore-openapi.json';
    // or
    protected string $openApiSchemaSource = 'https://api.example.com/openapi.json';
}
```

### 2. Automatic Features

Once enabled, your model automatically gains:

```php
// Automatic parameter validation
$pet = new Pet();
$validator = $pet->validateParameters([
    'name' => 'Fluffy',
    'status' => 'available'
], 'create');

// Dynamic query scopes
$pets = Pet::withOpenApiFilters([
    'status' => 'available',
    'category' => 'dogs'
])->get();

// OpenAPI-aware query builder
$pets = Pet::whereOpenApi('status', 'available')
    ->orderByOpenApi('name')
    ->limitOpenApi(10)
    ->get();

// Automatic relationships
$pet = Pet::find(1);
$category = $pet->category(); // Automatically detected from schema
$tags = $pet->tags(); // Array relationships work too
```

## Configuration

### Environment Variables

```env
# OpenAPI Configuration
OPENAPI_CACHE_ENABLED=true
OPENAPI_CACHE_TTL=3600
OPENAPI_ALLOW_REMOTE=true
OPENAPI_VERIFY_SSL=true
OPENAPI_DEFAULT_SCHEMA=/path/to/default-schema.json
```

### Config File

Publish and customize the OpenAPI configuration:

```bash
php artisan vendor:publish --tag=openapi-config
```

```php
// config/openapi.php
return [
    'cache' => [
        'enabled' => env('OPENAPI_CACHE_ENABLED', true),
        'ttl' => env('OPENAPI_CACHE_TTL', 3600),
        'prefix' => 'openapi_',
    ],
    
    'model_schemas' => [
        'App\\Models\\Pet' => '/path/to/petstore-openapi.json',
        'App\\Models\\User' => 'https://api.example.com/openapi.json',
    ],
    
    'default_schema' => env('OPENAPI_DEFAULT_SCHEMA'),
];
```

## Advanced Usage

### Console Commands

Generate models from OpenAPI schemas:

```bash
# Parse schema and generate models
php artisan api-model:parse-openapi /path/to/schema.json --generate-models

# Specify output directory and namespace
php artisan api-model:parse-openapi schema.json \
    --generate-models \
    --output-dir=app/Models/Api \
    --namespace="App\\Models\\Api"

# Dry run to see what would be generated
php artisan api-model:parse-openapi schema.json --dry-run
```

### Manual Schema Operations

```php
use MTechStack\LaravelApiModelClient\OpenApi\OpenApiSchemaParser;

$parser = new OpenApiSchemaParser();
$result = $parser->parse('/path/to/openapi.json');

// Access parsed data
$endpoints = $result['endpoints'];
$schemas = $result['schemas'];
$validationRules = $result['validation_rules'];
$modelMappings = $result['model_mappings'];
```

### Custom Query Builder

```php
use MTechStack\LaravelApiModelClient\Query\OpenApiQueryBuilder;

class Pet extends ApiModel
{
    use HasOpenApiSchema;
    
    public function newEloquentBuilder($query)
    {
        return new OpenApiQueryBuilder($query);
    }
}

// Now use enhanced query methods
$pets = Pet::whereOpenApi('age', '>=', 1)
    ->whereOpenApiMultiple([
        'status' => 'available',
        'category' => 'dogs'
    ])
    ->applyOpenApiFilters($request->all())
    ->paginateOpenApi(20);
```

### Validation Examples

```php
// Validate against OpenAPI schema
$pet = new Pet();

// Create validation
$validator = $pet->validateParameters([
    'name' => 'Fluffy',
    'status' => 'available',
    'age' => 3
], 'create');

if ($validator->fails()) {
    return response()->json($validator->errors(), 422);
}

// Update validation
$validator = $pet->validateParameters([
    'status' => 'sold'
], 'update');
```

### Relationship Handling

```php
class Pet extends ApiModel
{
    use HasOpenApiSchema;
    
    // Relationships are automatically detected from OpenAPI schema
    // But you can still define them manually if needed
    
    public function category()
    {
        // This will be automatically resolved if defined in OpenAPI schema
        return $this->belongsTo(Category::class);
    }
    
    public function tags()
    {
        // Array relationships are also supported
        return $this->hasMany(Tag::class);
    }
}

// Usage
$pet = Pet::with(['category', 'tags'])->find(1);
```

## Schema Requirements

### Supported OpenAPI Versions

- OpenAPI 3.0.0, 3.0.1, 3.0.2, 3.0.3
- OpenAPI 3.1.0

### Required Schema Structure

```json
{
  "openapi": "3.0.0",
  "info": {
    "title": "Pet Store API",
    "version": "1.0.0"
  },
  "paths": {
    "/pets": {
      "get": {
        "operationId": "listPets",
        "parameters": [
          {
            "name": "status",
            "in": "query",
            "schema": {
              "type": "string",
              "enum": ["available", "pending", "sold"]
            }
          }
        ]
      },
      "post": {
        "operationId": "createPet",
        "requestBody": {
          "content": {
            "application/json": {
              "schema": {
                "$ref": "#/components/schemas/Pet"
              }
            }
          }
        }
      }
    },
    "/pets/{id}": {
      "get": {
        "operationId": "getPet"
      },
      "put": {
        "operationId": "updatePet"
      },
      "delete": {
        "operationId": "deletePet"
      }
    }
  },
  "components": {
    "schemas": {
      "Pet": {
        "type": "object",
        "required": ["name"],
        "properties": {
          "id": {
            "type": "integer",
            "format": "int64"
          },
          "name": {
            "type": "string",
            "maxLength": 100
          },
          "status": {
            "type": "string",
            "enum": ["available", "pending", "sold"]
          },
          "category": {
            "$ref": "#/components/schemas/Category"
          },
          "tags": {
            "type": "array",
            "items": {
              "$ref": "#/components/schemas/Tag"
            }
          }
        }
      }
    }
  }
}
```

## Performance Considerations

### Caching

- Parsed schemas are cached automatically
- Cache TTL is configurable
- Static caching within request lifecycle
- Laravel cache integration for persistence

### Memory Usage

- Lazy loading of schema components
- Configurable file size limits
- Efficient parsing with cebe/openapi

### Network Requests

- Configurable timeouts for remote schemas
- SSL verification options
- Retry mechanisms for failed requests

## Error Handling

### Custom Exceptions

```php
use MTechStack\LaravelApiModelClient\OpenApi\Exceptions\OpenApiParsingException;
use MTechStack\LaravelApiModelClient\OpenApi\Exceptions\SchemaValidationException;

try {
    $result = $parser->parse('/path/to/schema.json');
} catch (OpenApiParsingException $e) {
    Log::error('Failed to parse OpenAPI schema', [
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
} catch (SchemaValidationException $e) {
    Log::error('Schema validation failed', [
        'errors' => $e->getValidationErrors()
    ]);
}
```

### Graceful Degradation

Models without OpenAPI schemas continue to work normally:

```php
class LegacyModel extends ApiModel
{
    // No HasOpenApiSchema trait - works as before
    protected $fillable = ['name', 'email'];
}

$model = new LegacyModel();
$model->hasOpenApiSchema(); // Returns false
// All existing functionality continues to work
```

## Testing

### Unit Tests

```php
use MTechStack\LaravelApiModelClient\Tests\TestCase;

class MyApiModelTest extends TestCase
{
    public function test_openapi_validation()
    {
        $model = new Pet();
        $model->setSchemaSource('/path/to/test-schema.json');
        
        $validator = $model->validateParameters([
            'name' => 'Test Pet',
            'status' => 'available'
        ], 'create');
        
        $this->assertFalse($validator->fails());
    }
}
```

### Integration Tests

```php
public function test_openapi_query_builder()
{
    $pets = Pet::whereOpenApi('status', 'available')
        ->limitOpenApi(10)
        ->get();
        
    $this->assertInstanceOf(Collection::class, $pets);
    $this->assertLessThanOrEqual(10, $pets->count());
}
```

## Migration Guide

### From v1.0 to v1.1

1. **Update composer dependencies:**
   ```bash
   composer update m-tech-stack/laravel-api-model-client
   ```

2. **Publish new configuration:**
   ```bash
   php artisan vendor:publish --tag=openapi-config
   ```

3. **Add OpenAPI support to existing models:**
   ```php
   // Before
   class Pet extends ApiModel
   {
       protected $fillable = ['name', 'status'];
   }
   
   // After
   class Pet extends ApiModel
   {
       use HasOpenApiSchema;
       
       protected string $openApiSchemaSource = '/path/to/schema.json';
       // $fillable is now auto-generated from schema
   }
   ```

4. **Update tests if needed:**
   - Test files may need updates for new functionality
   - Existing tests continue to work unchanged

## Troubleshooting

### Common Issues

1. **Schema not loading:**
   - Check file path/URL accessibility
   - Verify SSL certificates for HTTPS URLs
   - Check cache permissions

2. **Validation failures:**
   - Ensure schema matches API responses
   - Check required vs optional fields
   - Verify enum values

3. **Relationship issues:**
   - Ensure proper $ref usage in schema
   - Check relationship naming conventions
   - Verify foreign key mappings

### Debug Mode

Enable detailed logging:

```php
// config/openapi.php
'logging' => [
    'enabled' => true,
    'level' => 'debug',
    'channel' => 'single'
]
```

## Support

For issues, feature requests, or contributions:

- GitHub Issues: [Package Repository]
- Documentation: [Package Wiki]
- Email: m.abou7agar@gmail.com

## License

This OpenAPI integration is part of the Laravel API Model Client package and is licensed under the MIT License.
