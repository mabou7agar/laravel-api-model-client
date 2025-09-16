# Contributing Guidelines

Thank you for your interest in contributing to the Laravel API Model Client with OpenAPI integration! This document provides guidelines and information for contributors.

## Table of Contents

1. [Getting Started](#getting-started)
2. [Development Setup](#development-setup)
3. [Code Standards](#code-standards)
4. [Testing Guidelines](#testing-guidelines)
5. [Documentation Standards](#documentation-standards)
6. [Pull Request Process](#pull-request-process)
7. [Issue Reporting](#issue-reporting)
8. [Feature Requests](#feature-requests)
9. [OpenAPI Feature Development](#openapi-feature-development)
10. [Release Process](#release-process)

## Getting Started

### Prerequisites

- PHP 8.1 or higher
- Laravel 10.0 or higher
- Composer
- Git
- Basic understanding of OpenAPI/Swagger specifications

### Fork and Clone

1. Fork the repository on GitHub
2. Clone your fork locally:
```bash
git clone https://github.com/YOUR_USERNAME/laravel-api-model-client.git
cd laravel-api-model-client
```

3. Add the upstream repository:
```bash
git remote add upstream https://github.com/m-tech-stack/laravel-api-model-client.git
```

## Development Setup

### 1. Install Dependencies

```bash
# Install PHP dependencies
composer install

# Install development dependencies
composer install --dev

# Install OpenAPI dependency
composer require cebe/php-openapi
```

### 2. Environment Configuration

```bash
# Copy environment file
cp .env.example .env

# Configure test database and API endpoints
# Edit .env with your test configurations
```

### 3. Run Tests

```bash
# Run all tests
./vendor/bin/phpunit

# Run specific test suites
./vendor/bin/phpunit tests/Unit
./vendor/bin/phpunit tests/Integration
./vendor/bin/phpunit tests/Feature

# Run with coverage
./vendor/bin/phpunit --coverage-html coverage
```

### 4. Code Quality Tools

```bash
# Run PHP CS Fixer
./vendor/bin/php-cs-fixer fix

# Run PHPStan
./vendor/bin/phpstan analyse

# Run Psalm
./vendor/bin/psalm
```

## Code Standards

### PHP Standards

We follow PSR-12 coding standards with some additional rules:

```php
<?php

namespace MTechStack\LaravelApiModelClient\Example;

use Illuminate\Support\Collection;
use MTechStack\LaravelApiModelClient\Contracts\ApiModelInterface;

/**
 * Example class demonstrating coding standards.
 */
class ExampleClass implements ApiModelInterface
{
    /**
     * Class constants in UPPER_CASE.
     */
    private const DEFAULT_TIMEOUT = 30;

    /**
     * Properties with type declarations.
     */
    private string $apiEndpoint;
    private ?array $cachedData = null;

    /**
     * Constructor with promoted properties when appropriate.
     */
    public function __construct(
        private readonly string $baseUrl,
        private readonly array $config = []
    ) {
        $this->apiEndpoint = $baseUrl . '/api';
    }

    /**
     * Methods with clear return types and documentation.
     */
    public function fetchData(array $parameters = []): Collection
    {
        // Method implementation
        return collect($this->makeRequest($parameters));
    }

    /**
     * Private methods for internal logic.
     */
    private function makeRequest(array $parameters): array
    {
        // Implementation details
        return [];
    }
}
```

### Naming Conventions

- **Classes**: PascalCase (`OpenApiSchemaParser`)
- **Methods**: camelCase (`parseSchema`, `validateParameters`)
- **Properties**: camelCase (`$openApiSchema`, `$validationRules`)
- **Constants**: UPPER_SNAKE_CASE (`DEFAULT_CACHE_TTL`)
- **Files**: PascalCase for classes, kebab-case for configs (`api-client.php`)

### Documentation Standards

All public methods must have PHPDoc comments:

```php
/**
 * Parse OpenAPI schema and extract model information.
 *
 * @param string $source The schema source (file path or URL)
 * @param bool $useCache Whether to use cached results
 * @return array The parsed schema data
 * 
 * @throws OpenApiParsingException When schema parsing fails
 * @throws InvalidArgumentException When source is invalid
 */
public function parse(string $source, bool $useCache = true): array
{
    // Implementation
}
```

## Testing Guidelines

### Test Structure

Tests are organized into categories:

```
tests/
├── Unit/                 # Unit tests for individual classes
│   ├── OpenApi/         # OpenAPI-specific unit tests
│   ├── Models/          # Model unit tests
│   └── Validation/      # Validation unit tests
├── Integration/         # Integration tests
│   ├── SchemaLoading/   # Schema loading integration
│   └── ModelGeneration/ # Model generation integration
├── Feature/             # Feature tests
├── Performance/         # Performance benchmarks
├── Compatibility/       # Version compatibility tests
└── EdgeCases/          # Edge case and error handling
```

### Writing Tests

#### Unit Tests

```php
<?php

namespace Tests\Unit\OpenApi;

use PHPUnit\Framework\TestCase;
use MTechStack\LaravelApiModelClient\OpenApi\OpenApiSchemaParser;

class OpenApiSchemaParserTest extends TestCase
{
    private OpenApiSchemaParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new OpenApiSchemaParser();
    }

    /** @test */
    public function it_can_parse_valid_openapi_schema(): void
    {
        // Arrange
        $schemaPath = $this->getTestSchemaPath('valid-schema.json');

        // Act
        $result = $this->parser->parse($schemaPath);

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('info', $result);
        $this->assertArrayHasKey('endpoints', $result);
        $this->assertArrayHasKey('schemas', $result);
    }

    /** @test */
    public function it_throws_exception_for_invalid_schema(): void
    {
        // Arrange
        $invalidSchemaPath = $this->getTestSchemaPath('invalid-schema.json');

        // Assert
        $this->expectException(OpenApiParsingException::class);
        $this->expectExceptionMessage('Invalid OpenAPI schema');

        // Act
        $this->parser->parse($invalidSchemaPath);
    }

    private function getTestSchemaPath(string $filename): string
    {
        return __DIR__ . '/../../fixtures/schemas/' . $filename;
    }
}
```

#### Integration Tests

```php
<?php

namespace Tests\Integration\Models;

use Tests\TestCase;
use App\Models\Api\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ProductModelTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_can_create_product_with_openapi_validation(): void
    {
        // Arrange
        $this->mockApiResponse('POST', '/products', [
            'id' => 1,
            'name' => 'Test Product',
            'price' => 29.99,
        ]);

        $productData = [
            'name' => 'Test Product',
            'price' => 29.99,
            'status' => 'active',
        ];

        // Act
        $product = Product::create($productData);

        // Assert
        $this->assertInstanceOf(Product::class, $product);
        $this->assertEquals('Test Product', $product->name);
        $this->assertEquals(29.99, $product->price);
    }
}
```

#### Performance Tests

```php
<?php

namespace Tests\Performance;

use Tests\TestCase;
use MTechStack\LaravelApiModelClient\OpenApi\OpenApiSchemaParser;

class SchemaParsingPerformanceTest extends TestCase
{
    /** @test */
    public function schema_parsing_completes_within_time_limit(): void
    {
        $parser = new OpenApiSchemaParser();
        $largeSchemaPath = $this->getLargeTestSchema();

        $startTime = microtime(true);
        $result = $parser->parse($largeSchemaPath);
        $endTime = microtime(true);

        $executionTime = $endTime - $startTime;

        $this->assertLessThan(5.0, $executionTime, 'Schema parsing should complete within 5 seconds');
        $this->assertIsArray($result);
    }
}
```

### Test Data and Fixtures

Create reusable test fixtures:

```php
<?php

namespace Tests\Fixtures;

class SchemaFixtures
{
    public static function getMinimalValidSchema(): array
    {
        return [
            'openapi' => '3.0.0',
            'info' => [
                'title' => 'Test API',
                'version' => '1.0.0',
            ],
            'paths' => [
                '/products' => [
                    'get' => [
                        'summary' => 'List products',
                        'responses' => [
                            '200' => [
                                'description' => 'Success',
                                'content' => [
                                    'application/json' => [
                                        'schema' => [
                                            'type' => 'array',
                                            'items' => ['$ref' => '#/components/schemas/Product']
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
                        'required' => ['name', 'price'],
                        'properties' => [
                            'id' => ['type' => 'integer'],
                            'name' => ['type' => 'string'],
                            'price' => ['type' => 'number', 'minimum' => 0],
                            'status' => [
                                'type' => 'string',
                                'enum' => ['active', 'inactive']
                            ]
                        ]
                    ]
                ]
            ]
        ];
    }

    public static function getComplexEcommerceSchema(): array
    {
        // Return more complex schema for integration tests
        return [
            // Complex schema definition
        ];
    }
}
```

## Documentation Standards

### Code Documentation

- All public classes, methods, and properties must have PHPDoc comments
- Include `@param`, `@return`, and `@throws` tags
- Provide clear descriptions and examples where helpful
- Document complex algorithms and business logic

### User Documentation

When adding new features, update relevant documentation:

- **README.md**: Update if adding major features
- **OPENAPI-INTEGRATION-GUIDE.md**: Add new OpenAPI features
- **BEST-PRACTICES.md**: Include performance tips
- **TROUBLESHOOTING.md**: Add common issues and solutions
- **Examples**: Create practical examples in `docs/examples/`

### Example Documentation

```php
/**
 * Advanced query builder for OpenAPI-driven models.
 *
 * This class extends the base query builder to provide OpenAPI-aware
 * query methods that automatically validate parameters against the
 * OpenAPI schema and optimize API requests.
 *
 * @example
 * ```php
 * $products = Product::whereOpenApi('status', 'active')
 *     ->whereOpenApi('price', '>', 10.00)
 *     ->orderByOpenApi('created_at', 'desc')
 *     ->limitOpenApi(20)
 *     ->get();
 * ```
 */
class OpenApiQueryBuilder extends QueryBuilder
{
    // Implementation
}
```

## Pull Request Process

### Before Submitting

1. **Update your fork:**
```bash
git fetch upstream
git checkout main
git merge upstream/main
```

2. **Create a feature branch:**
```bash
git checkout -b feature/your-feature-name
```

3. **Make your changes:**
- Follow coding standards
- Add tests for new functionality
- Update documentation
- Ensure all tests pass

4. **Commit your changes:**
```bash
git add .
git commit -m "feat: add OpenAPI parameter validation

- Add parameter validation against OpenAPI schema
- Include support for nested object validation
- Add comprehensive test coverage
- Update documentation with examples

Closes #123"
```

### Commit Message Format

We use conventional commits:

```
<type>[optional scope]: <description>

[optional body]

[optional footer(s)]
```

Types:
- `feat`: New feature
- `fix`: Bug fix
- `docs`: Documentation changes
- `style`: Code style changes (formatting, etc.)
- `refactor`: Code refactoring
- `test`: Adding or updating tests
- `chore`: Maintenance tasks

Examples:
```
feat(openapi): add schema caching support
fix(validation): handle null values in enum validation
docs(examples): add e-commerce integration examples
test(parser): add edge case tests for schema parsing
```

### Pull Request Template

```markdown
## Description
Brief description of changes and motivation.

## Type of Change
- [ ] Bug fix (non-breaking change which fixes an issue)
- [ ] New feature (non-breaking change which adds functionality)
- [ ] Breaking change (fix or feature that would cause existing functionality to not work as expected)
- [ ] Documentation update

## Testing
- [ ] Unit tests added/updated
- [ ] Integration tests added/updated
- [ ] All tests pass locally
- [ ] Manual testing completed

## Documentation
- [ ] Code documentation updated
- [ ] User documentation updated
- [ ] Examples added/updated

## Checklist
- [ ] Code follows project style guidelines
- [ ] Self-review completed
- [ ] Changes are backward compatible (or breaking changes documented)
- [ ] Related issues linked

## Related Issues
Closes #123
Related to #456
```

## Issue Reporting

### Bug Reports

Use the bug report template:

```markdown
**Bug Description**
A clear description of the bug.

**To Reproduce**
Steps to reproduce the behavior:
1. Configure schema with '...'
2. Create model with '...'
3. Call method '...'
4. See error

**Expected Behavior**
What you expected to happen.

**Actual Behavior**
What actually happened.

**Environment**
- PHP Version: [e.g., 8.1.0]
- Laravel Version: [e.g., 10.0.0]
- Package Version: [e.g., 2.0.0]
- OpenAPI Schema Version: [e.g., 3.0.0]

**Additional Context**
- Configuration files
- Schema excerpts
- Error messages and stack traces
- Any other relevant information
```

### Security Issues

For security vulnerabilities:
1. **DO NOT** create a public issue
2. Email security@m-tech-stack.com
3. Include detailed reproduction steps
4. Allow reasonable time for response before disclosure

## Feature Requests

### Feature Request Template

```markdown
**Feature Description**
Clear description of the proposed feature.

**Use Case**
Describe the problem this feature would solve.

**Proposed Solution**
How you envision this feature working.

**Alternatives Considered**
Other approaches you've considered.

**Additional Context**
- Code examples
- Related OpenAPI specifications
- Links to relevant documentation
```

### Feature Development Process

1. **Discussion**: Create an issue to discuss the feature
2. **Design**: Collaborate on the design approach
3. **Implementation**: Develop the feature with tests
4. **Review**: Submit PR for code review
5. **Documentation**: Update relevant documentation
6. **Release**: Feature included in next release

## OpenAPI Feature Development

### Adding New OpenAPI Features

When adding OpenAPI-specific functionality:

1. **Study the OpenAPI Specification:**
   - Review the relevant OpenAPI 3.0+ specification
   - Understand edge cases and requirements
   - Check compatibility with different versions

2. **Design the API:**
   - Follow existing patterns in the codebase
   - Ensure backward compatibility
   - Consider performance implications

3. **Implementation Guidelines:**
```php
<?php

namespace MTechStack\LaravelApiModelClient\OpenApi;

/**
 * New OpenAPI feature implementation.
 */
class NewOpenApiFeature
{
    /**
     * Follow established patterns for error handling.
     */
    public function processFeature(array $schema): array
    {
        try {
            $this->validateSchema($schema);
            return $this->extractFeatureData($schema);
        } catch (InvalidSchemaException $e) {
            throw new OpenApiParsingException(
                "Failed to process OpenAPI feature: {$e->getMessage()}",
                0,
                $e
            );
        }
    }

    /**
     * Include comprehensive validation.
     */
    private function validateSchema(array $schema): void
    {
        if (!isset($schema['openapi'])) {
            throw new InvalidSchemaException('Missing OpenAPI version');
        }

        // Additional validation logic
    }
}
```

4. **Testing Requirements:**
   - Unit tests for the feature logic
   - Integration tests with real schemas
   - Edge case and error handling tests
   - Performance tests for large schemas

5. **Documentation:**
   - Update OpenAPI integration guide
   - Add practical examples
   - Include troubleshooting information

### OpenAPI Version Compatibility

When adding features, ensure compatibility:

```php
class OpenApiVersionCompatibility
{
    private const SUPPORTED_VERSIONS = ['3.0.0', '3.0.1', '3.0.2', '3.0.3', '3.1.0'];

    public function isVersionSupported(string $version): bool
    {
        return in_array($version, self::SUPPORTED_VERSIONS, true);
    }

    public function getVersionSpecificBehavior(string $version): array
    {
        return match ($version) {
            '3.1.0' => ['supports_json_schema_draft_2019_09' => true],
            default => ['supports_json_schema_draft_04' => true],
        };
    }
}
```

## Release Process

### Version Numbering

We follow Semantic Versioning (SemVer):
- **MAJOR**: Breaking changes
- **MINOR**: New features (backward compatible)
- **PATCH**: Bug fixes (backward compatible)

### Release Checklist

1. **Pre-release:**
   - [ ] All tests pass
   - [ ] Documentation updated
   - [ ] CHANGELOG.md updated
   - [ ] Version bumped in composer.json

2. **Release:**
   - [ ] Create release branch
   - [ ] Final testing
   - [ ] Tag release
   - [ ] Publish to Packagist

3. **Post-release:**
   - [ ] Update documentation site
   - [ ] Announce release
   - [ ] Monitor for issues

### Changelog Format

```markdown
# Changelog

## [2.1.0] - 2024-01-15

### Added
- OpenAPI 3.1.0 support
- Advanced parameter validation
- Schema caching improvements

### Changed
- Improved error messages
- Performance optimizations

### Fixed
- Enum validation edge cases
- Memory leak in large schemas

### Deprecated
- Legacy validation methods (will be removed in 3.0.0)

## [2.0.0] - 2023-12-01

### Added
- Complete OpenAPI integration
- Automatic model generation
- Advanced query building

### Breaking Changes
- Minimum PHP version now 8.1
- Configuration format changed
- Some method signatures updated
```

## Getting Help

### Development Questions

- **GitHub Discussions**: For general questions and discussions
- **Issues**: For specific bugs or feature requests
- **Discord/Slack**: Real-time chat with maintainers and community

### Code Review Process

1. **Automated Checks**: CI/CD runs tests and code quality checks
2. **Maintainer Review**: Core maintainers review code and design
3. **Community Feedback**: Community members may provide feedback
4. **Approval**: At least one maintainer approval required
5. **Merge**: Maintainers merge approved PRs

### Recognition

Contributors are recognized in:
- CONTRIBUTORS.md file
- Release notes
- Package documentation
- Annual contributor highlights

Thank you for contributing to the Laravel API Model Client! Your contributions help make this package better for the entire Laravel community.
