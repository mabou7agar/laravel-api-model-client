# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.11] - 2025-08-24

### Fixed
- **CRITICAL**: Fixed Laravel trait initialization warnings that occurred when using `Product::all()` and other Eloquent methods
- **MAJOR**: Resolved "Undefined array key" and "foreach() argument must be of type array|object" warnings in Laravel Model.php
- **IMPROVEMENT**: Added proper trait initialization handling in `ApiModel::initializeTraits()` method
- **ENHANCEMENT**: Implemented safe boot method that initializes trait initializers array correctly
- **POLISH**: Eliminated all warnings while maintaining full API functionality and seamless Eloquent-like experience

### Added
- Safe trait initialization with proper array existence checks
- Method existence validation before trait method invocation
- Graceful handling of missing trait initializers for API models

### Technical Details
- Override `initializeTraits()` method to handle missing trait initializers gracefully
- Initialize `static::$traitInitializers[static::class]` array in boot method
- Maintain full backward compatibility with existing API functionality
- Ensure clean, professional output without Laravel framework warnings

## [1.0.10] - 2025-08-24

### Fixed
- **CRITICAL**: Fixed `__call` method in `LazyLoadsApiRelationships` trait that was incorrectly forwarding `newFromApiResponse` calls to QueryBuilder
- **MAJOR**: Implemented reflection-based approach in `ApiQueryBuilder` to create fresh model instances for API response processing
- **BREAKTHROUGH**: Resolved data parsing issues where API responses were not being converted to Laravel model instances correctly

### Added
- Reflection-based model creation in `processApiResponse` and `createModelsFromItems` methods
- Direct method invocation bypassing trait `__call` interference
- Enhanced debugging capabilities for API response processing

### Technical Details
- Use PHP Reflection API to call `newFromApiResponse` directly on fresh model instances
- Bypass trait method forwarding that was causing `BadMethodCallException`
- Maintain full API functionality while ensuring proper Laravel model instantiation

## [1.0.9] - 2025-08-23

### Added
- Initial implementation of Laravel API Model Client
- Support for Eloquent-like models that interact with external APIs
- API query builder with chainable methods
- Lazy loading of API relationships
- Comprehensive trait system for API model functionality

### Features
- `allFromApi()`, `findFromApi()`, and `getFromApi()` methods
- Support for API endpoints with Bearer token authentication
- Query builder with `take()`, `limit()`, `where()` methods
- Seamless integration with Laravel framework
- Compatible with Laravel 8.x, 9.x, 10.x, 11.x, and 12.x
