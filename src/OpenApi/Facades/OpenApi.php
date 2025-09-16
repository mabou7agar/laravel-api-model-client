<?php

namespace MTechStack\LaravelApiModelClient\OpenApi\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * OpenAPI Parser Facade
 * 
 * @method static array parse(string $source, bool $useCache = true)
 * @method static array getEndpoints()
 * @method static array getSchemas()
 * @method static array getModelMappings()
 * @method static array getValidationRules()
 * @method static array getValidationRulesForEndpoint(string $operationId, string $mediaType = 'application/json')
 * @method static array getValidationRulesForSchema(string $schemaName)
 * @method static array getModelMapping(string $modelName)
 * @method static array getModelNames()
 * @method static array getModelOperations(string $modelName)
 * @method static array getModelAttributes(string $modelName)
 * @method static array getModelRelationships(string $modelName)
 * @method static string generateModelClass(string $modelName)
 */
class OpenApi extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'openapi-parser';
    }
}
