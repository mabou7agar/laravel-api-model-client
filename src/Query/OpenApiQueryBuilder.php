<?php

namespace MTechStack\LaravelApiModelClient\Query;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use MTechStack\LaravelApiModelClient\Models\ApiModel;
use MTechStack\LaravelApiModelClient\OpenApi\OpenApiParameterSerializer;

/**
 * OpenAPI-enhanced query builder for ApiModel
 */
class OpenApiQueryBuilder extends Builder
{
    /**
     * The ApiModel instance
     */
    protected ApiModel $apiModel;

    /**
     * Query parameters for API requests
     */
    protected array $apiParameters = [];

    /**
     * OpenAPI parameter definitions cache
     */
    protected ?array $parameterDefinitions = null;

    /**
     * Dynamic scopes cache
     */
    protected array $dynamicScopes = [];

    /**
     * Pagination parameters
     */
    protected array $paginationParams = [];

    /**
     * Sorting parameters
     */
    protected array $sortingParams = [];

    /**
     * Filtering parameters
     */
    protected array $filteringParams = [];

    /**
     * Create a new OpenAPI query builder instance
     */
    public function __construct($query, $model = null)
    {
        parent::__construct($query);
        if ($model instanceof ApiModel) {
            $this->setModel($model);
        }
        // Do not access $this->getModel() here; Eloquent will call setModel() after construction.
    }

    public function setModel($model)
    {
        parent::setModel($model);
        if ($model instanceof ApiModel) {
            $this->apiModel = $model;
        }
        return $this;
    }

    /**
     * Add OpenAPI-validated where clause
     */
    public function whereOpenApi(string $attribute, $operator = null, $value = null, string $boolean = 'and'): self
    {
        if (!$this->apiModel->hasOpenApiSchema()) {
            return $this->where($attribute, $operator, $value, $boolean);
        }

        // Validate attribute exists in OpenAPI schema
        $paramDefinition = $this->apiModel->getOpenApiParameterDefinition($attribute);
        if (!$paramDefinition) {
            // Fallback: proceed without OpenAPI validation
            return $this->where($attribute, $operator, $value, $boolean);
        }

        // Validate value against OpenAPI schema
        $this->validateParameterValue($attribute, $value, $paramDefinition);

        // Store for API request
        $this->apiParameters[$attribute] = $value;

        return $this->where($attribute, $operator, $value, $boolean);
    }

    /**
     * Add OpenAPI-validated OR where clause
     */
    public function orWhereOpenApi(string $attribute, $operator = null, $value = null): self
    {
        return $this->whereOpenApi($attribute, $operator, $value, 'or');
    }

    /**
     * Add multiple OpenAPI-validated where clauses
     */
    public function whereOpenApiMultiple(array $parameters): self
    {
        foreach ($parameters as $attribute => $value) {
            $this->whereOpenApi($attribute, '=', $value);
        }

        return $this;
    }

    /**
     * Apply OpenAPI filters from request parameters
     */
    public function applyOpenApiFilters(array $filters): self
    {
        if (!$this->apiModel->hasOpenApiSchema()) {
            return $this;
        }

        $validatedFilters = $this->validateFilters($filters);
        
        foreach ($validatedFilters as $attribute => $value) {
            $this->whereOpenApi($attribute, '=', $value);
        }

        return $this;
    }

    /**
     * Order by OpenAPI-defined attribute
     */
    public function orderByOpenApi(string $attribute, string $direction = 'asc'): self
    {
        if (!$this->apiModel->hasOpenApiSchema()) {
            return $this->orderBy($attribute, $direction);
        }

        // Validate attribute exists in OpenAPI schema
        $paramDefinition = $this->apiModel->getOpenApiParameterDefinition($attribute);
        if (!$paramDefinition) {
            // Fallback to plain ordering
            return $this->orderBy($attribute, $direction);
        }

        // Store for API request
        $this->apiParameters['sort'] = $attribute;
        $this->apiParameters['order'] = $direction;

        return $this->orderBy($attribute, $direction);
    }

    /**
     * Limit results with OpenAPI validation
     */
    public function limitOpenApi(int $limit): self
    {
        if (!$this->apiModel->hasOpenApiSchema()) {
            return $this->limit($limit);
        }

        // Check if limit parameter is supported in OpenAPI schema
        $operations = $this->apiModel->getOpenApiOperations();
        $indexOperation = collect($operations)->firstWhere('type', 'index');
        
        if ($indexOperation && isset($indexOperation['endpoint_data']['parameters'])) {
            $limitParam = collect($indexOperation['endpoint_data']['parameters'])
                ->firstWhere('name', 'limit');
            
            if ($limitParam) {
                $schema = $limitParam['schema'];
                $maxLimit = $schema['maximum'] ?? null;
                
                if ($maxLimit && $limit > $maxLimit) {
                    throw new \InvalidArgumentException("Limit {$limit} exceeds maximum allowed limit of {$maxLimit}");
                }
            }
        }

        $this->apiParameters['limit'] = $limit;
        return $this->limit($limit);
    }

    /**
     * Offset results with OpenAPI validation
     */
    public function offsetOpenApi(int $offset): self
    {
        if (!$this->apiModel->hasOpenApiSchema()) {
            return $this->offset($offset);
        }

        if ($offset < 0) {
            throw new \InvalidArgumentException("Offset cannot be negative");
        }

        $this->apiParameters['offset'] = $offset;
        return $this->offset($offset);
    }

    /**
     * Paginate with OpenAPI-aware parameters
     */
    public function paginateOpenApi(int $perPage = null, array $columns = ['*'], string $pageName = 'page', int $page = null)
    {
        $perPage = $perPage ?: $this->getDefaultPerPage();
        
        // Validate pagination parameters against OpenAPI schema
        if ($this->apiModel->hasOpenApiSchema()) {
            $this->validatePaginationParameters($perPage, $page);
        }

        return $this->paginate($perPage, $columns, $pageName, $page);
    }

    /**
     * Get API parameters for the current query
     */
    public function getApiParameters(): array
    {
        return $this->apiParameters;
    }

    /**
     * Execute the query with OpenAPI-enhanced logic
     */
    public function get($columns = ['*'])
    {
        // If model has OpenAPI schema, enhance the query execution
        if ($this->apiModel->hasOpenApiSchema()) {
            return $this->getWithOpenApiEnhancements($columns);
        }

        return parent::get($columns);
    }

    /**
     * Execute query with OpenAPI enhancements
     */
    protected function getWithOpenApiEnhancements($columns = ['*'])
    {
        // Validate all accumulated parameters
        if (!empty($this->apiParameters)) {
            $validator = $this->apiModel->validateParameters($this->apiParameters, 'index');
            
            if ($validator->fails()) {
                throw new \InvalidArgumentException(
                    'Invalid query parameters: ' . implode(', ', $validator->errors()->all())
                );
            }
        }

        // Execute the query with enhanced error handling
        try {
            return parent::get($columns);
        } catch (\Exception $e) {
            // Log OpenAPI-specific error context
            \Log::error('OpenAPI query execution failed', [
                'model' => get_class($this->apiModel),
                'parameters' => $this->apiParameters,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    /**
     * Validate parameter value against OpenAPI schema
     */
    protected function validateParameterValue(string $attribute, $value, array $paramDefinition): void
    {
        $rules = $this->buildValidationRulesFromDefinition($paramDefinition);
        
        if (!empty($rules)) {
            $validator = Validator::make([$attribute => $value], [$attribute => $rules]);
            
            if ($validator->fails()) {
                throw new \InvalidArgumentException(
                    "Invalid value for '{$attribute}': " . implode(', ', $validator->errors()->get($attribute))
                );
            }
        }
    }

    /**
     * Build Laravel validation rules from OpenAPI parameter definition
     */
    protected function buildValidationRulesFromDefinition(array $definition): array
    {
        $rules = [];
        $type = $definition['type'] ?? 'string';
        $format = $definition['format'] ?? null;

        // Add type validation
        switch ($type) {
            case 'integer':
                $rules[] = 'integer';
                break;
            case 'number':
                $rules[] = 'numeric';
                break;
            case 'boolean':
                $rules[] = 'boolean';
                break;
            case 'array':
                $rules[] = 'array';
                break;
            case 'string':
                $rules[] = 'string';
                break;
        }

        // Add format validation
        if ($format) {
            switch ($format) {
                case 'email':
                    $rules[] = 'email';
                    break;
                case 'url':
                    $rules[] = 'url';
                    break;
                case 'date':
                    $rules[] = 'date';
                    break;
                case 'date-time':
                    $rules[] = 'date';
                    break;
                case 'uuid':
                    $rules[] = 'uuid';
                    break;
            }
        }

        // Add constraint validations
        if (isset($definition['minimum'])) {
            $rules[] = 'min:' . $definition['minimum'];
        }
        if (isset($definition['maximum'])) {
            $rules[] = 'max:' . $definition['maximum'];
        }
        if (isset($definition['min_length'])) {
            $rules[] = 'min:' . $definition['min_length'];
        }
        if (isset($definition['max_length'])) {
            $rules[] = 'max:' . $definition['max_length'];
        }
        if (isset($definition['enum'])) {
            $rules[] = 'in:' . implode(',', $definition['enum']);
        }
        if (isset($definition['pattern'])) {
            $rules[] = 'regex:/' . str_replace('/', '\/', $definition['pattern']) . '/';
        }

        return $rules;
    }

    /**
     * Validate filters against OpenAPI schema
     */
    protected function validateFilters(array $filters): array
    {
        $validatedFilters = [];
        $attributes = collect($this->apiModel->getOpenApiAttributes())->keyBy('name');

        foreach ($filters as $attribute => $value) {
            if (!$attributes->has($attribute)) {
                continue; // Skip unknown attributes
            }

            $paramDefinition = $attributes->get($attribute);
            
            try {
                $this->validateParameterValue($attribute, $value, $paramDefinition);
                $validatedFilters[$attribute] = $value;
            } catch (\InvalidArgumentException $e) {
                // Log validation error but continue with other filters
                \Log::warning("OpenAPI filter validation failed for '{$attribute}'", [
                    'value' => $value,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $validatedFilters;
    }

    /**
     * Validate pagination parameters
     */
    protected function validatePaginationParameters(int $perPage, ?int $page): void
    {
        $operations = $this->apiModel->getOpenApiOperations();
        $indexOperation = collect($operations)->firstWhere('type', 'index');
        
        if (!$indexOperation || !isset($indexOperation['endpoint_data']['parameters'])) {
            return;
        }

        $parameters = collect($indexOperation['endpoint_data']['parameters']);
        
        // Validate per page limit
        $limitParam = $parameters->firstWhere('name', 'limit');
        if ($limitParam && isset($limitParam['schema']['maximum'])) {
            $maxLimit = $limitParam['schema']['maximum'];
            if ($perPage > $maxLimit) {
                throw new \InvalidArgumentException("Per page limit {$perPage} exceeds maximum of {$maxLimit}");
            }
        }

        // Validate page number
        if ($page !== null && $page < 1) {
            throw new \InvalidArgumentException("Page number must be greater than 0");
        }
    }

    /**
     * Get default per page value from OpenAPI schema or fallback
     */
    protected function getDefaultPerPage(): int
    {
        if (!$this->apiModel->hasOpenApiSchema()) {
            return 15; // Laravel default
        }

        $operations = $this->apiModel->getOpenApiOperations();
        $indexOperation = collect($operations)->firstWhere('type', 'index');
        
        if ($indexOperation && isset($indexOperation['endpoint_data']['parameters'])) {
            $limitParam = collect($indexOperation['endpoint_data']['parameters'])
                ->firstWhere('name', 'limit');
            
            if ($limitParam && isset($limitParam['schema']['default'])) {
                return $limitParam['schema']['default'];
            }
        }

        return 15; // Fallback default
    }

    /**
     * Apply OpenAPI parameters automatically with validation and conversion
     */
    public function withOpenApiParams(array $parameters): self
    {
        if (!$this->apiModel->hasOpenApiSchema()) {
            return $this;
        }

        $definitions = $this->getParameterDefinitions();
        $processedParams = [];

        foreach ($parameters as $name => $value) {
            if (!isset($definitions[$name])) {
                continue; // Skip unknown parameters
            }

            $definition = $definitions[$name];
            
            try {
                // Validate parameter
                $errors = OpenApiParameterSerializer::validateParameter($value, $definition);
                if (!empty($errors)) {
                    throw new \InvalidArgumentException(
                        "Parameter '{$name}' validation failed: " . implode(', ', $errors)
                    );
                }

                // Convert and serialize parameter
                $convertedValue = OpenApiParameterSerializer::serializeParameter($value, $definition);
                $processedParams[$name] = $convertedValue;

                // Apply parameter based on its location and purpose
                $this->applyParameterByType($name, $convertedValue, $definition);

            } catch (\Exception $e) {
                \Log::warning("Failed to process OpenAPI parameter '{$name}'", [
                    'value' => $value,
                    'error' => $e->getMessage()
                ]);
                continue;
            }
        }

        $this->apiParameters = array_merge($this->apiParameters, $processedParams);
        return $this;
    }

    /**
     * Apply parameter based on its type and purpose
     */
    protected function applyParameterByType(string $name, $value, array $definition): void
    {
        $location = $definition['in'] ?? 'query';
        $purpose = $this->determineParameterPurpose($name, $definition);

        switch ($purpose) {
            case 'filter':
                $this->filteringParams[$name] = $value;
                $this->where($name, '=', $value);
                break;

            case 'sort':
                $this->sortingParams[$name] = $value;
                if ($name === 'sort' || $name === 'order_by') {
                    $direction = $this->apiParameters['order'] ?? $this->apiParameters['sort_direction'] ?? 'asc';
                    $this->orderBy($value, $direction);
                } elseif (str_contains($name, '_sort') || str_contains($name, '_order')) {
                    $this->orderBy(str_replace(['_sort', '_order'], '', $name), $value);
                }
                break;

            case 'pagination':
                $this->paginationParams[$name] = $value;
                if (in_array($name, ['limit', 'per_page', 'page_size'])) {
                    $this->limit($value);
                } elseif (in_array($name, ['offset', 'skip'])) {
                    $this->offset($value);
                } elseif ($name === 'page') {
                    $perPage = $this->paginationParams['limit'] ?? 
                              $this->paginationParams['per_page'] ?? 
                              $this->getDefaultPerPage();
                    $this->offset(($value - 1) * $perPage);
                }
                break;

            case 'search':
                $this->filteringParams[$name] = $value;
                // Apply search logic based on schema
                $this->applySearchParameter($name, $value, $definition);
                break;

            default:
                // Generic parameter handling
                $this->filteringParams[$name] = $value;
                break;
        }
    }

    /**
     * Determine the purpose of a parameter based on its name and definition
     */
    protected function determineParameterPurpose(string $name, array $definition): string
    {
        // Check explicit purpose in extensions
        if (isset($definition['x-purpose'])) {
            return $definition['x-purpose'];
        }

        // Determine by name patterns
        $lowerName = strtolower($name);

        if (in_array($lowerName, ['limit', 'per_page', 'page_size', 'offset', 'skip', 'page'])) {
            return 'pagination';
        }

        if (in_array($lowerName, ['sort', 'order_by', 'sort_by']) || 
            str_contains($lowerName, '_sort') || 
            str_contains($lowerName, '_order')) {
            return 'sort';
        }

        if (in_array($lowerName, ['search', 'query', 'q', 'filter']) || 
            str_contains($lowerName, 'search') || 
            str_contains($lowerName, 'query')) {
            return 'search';
        }

        return 'filter';
    }

    /**
     * Apply search parameter with intelligent matching
     */
    protected function applySearchParameter(string $name, $value, array $definition): void
    {
        if (in_array($name, ['search', 'query', 'q'])) {
            // Global search across searchable fields
            $searchableFields = $this->getSearchableFields();
            if (!empty($searchableFields)) {
                $this->where(function ($query) use ($searchableFields, $value) {
                    foreach ($searchableFields as $field) {
                        $query->orWhere($field, 'LIKE', "%{$value}%");
                    }
                });
            }
        } else {
            // Field-specific search
            $this->where($name, 'LIKE', "%{$value}%");
        }
    }

    /**
     * Get searchable fields from OpenAPI schema
     */
    protected function getSearchableFields(): array
    {
        if (!$this->apiModel->hasOpenApiSchema()) {
            return [];
        }

        $attributes = $this->apiModel->getOpenApiAttributes();
        $searchableFields = [];

        foreach ($attributes as $attribute) {
            // Consider string fields as searchable by default
            if (($attribute['type'] ?? 'string') === 'string') {
                $searchableFields[] = $attribute['name'];
            }

            // Check for explicit searchable marking
            if (isset($attribute['x-searchable']) && $attribute['x-searchable']) {
                $searchableFields[] = $attribute['name'];
            }
        }

        return array_unique($searchableFields);
    }

    /**
     * Create dynamic scope methods for OpenAPI parameters
     */
    public function createDynamicScopes(): array
    {
        if (!$this->apiModel->hasOpenApiSchema()) {
            return [];
        }

        if (!empty($this->dynamicScopes)) {
            return $this->dynamicScopes;
        }

        $definitions = $this->getParameterDefinitions();
        $scopes = [];

        foreach ($definitions as $name => $definition) {
            $scopeName = 'scope' . Str::studly($name);
            $scopes[$scopeName] = $this->createDynamicScope($name, $definition);
        }

        $this->dynamicScopes = $scopes;
        return $scopes;
    }

    /**
     * Create a dynamic scope for a parameter
     */
    protected function createDynamicScope(string $paramName, array $definition): \Closure
    {
        return function ($query, $value) use ($paramName, $definition) {
            if ($value === null || $value === '') {
                return $query;
            }

            // Validate and convert the value
            $errors = OpenApiParameterSerializer::validateParameter($value, $definition);
            if (!empty($errors)) {
                throw new \InvalidArgumentException(
                    "Invalid value for parameter '{$paramName}': " . implode(', ', $errors)
                );
            }

            $convertedValue = OpenApiParameterSerializer::serializeParameter($value, $definition);
            
            // Apply the parameter
            $purpose = $this->determineParameterPurpose($paramName, $definition);
            
            switch ($purpose) {
                case 'filter':
                    return $query->where($paramName, '=', $convertedValue);
                    
                case 'search':
                    if (($definition['type'] ?? 'string') === 'string') {
                        return $query->where($paramName, 'LIKE', "%{$convertedValue}%");
                    }
                    return $query->where($paramName, '=', $convertedValue);
                    
                case 'sort':
                    $direction = is_string($convertedValue) && 
                                in_array(strtolower($convertedValue), ['desc', 'descending']) ? 'desc' : 'asc';
                    return $query->orderBy($paramName, $direction);
                    
                default:
                    return $query->where($paramName, '=', $convertedValue);
            }
        };
    }

    /**
     * Apply OpenAPI-defined sorting
     */
    public function withOpenApiSorting($sortParams, $direction = 'asc'): self
    {
        if (!$this->apiModel->hasOpenApiSchema()) {
            return $this;
        }

        // Handle both array and string parameters
        if (is_string($sortParams)) {
            $sortParams = [$sortParams => $direction];
        }

        foreach ($sortParams as $field => $direction) {
            if (is_numeric($field)) {
                // Handle array format: ['name', 'created_at:desc']
                $sortString = $direction;
                if (str_contains($sortString, ':')) {
                    [$field, $direction] = explode(':', $sortString, 2);
                } else {
                    $field = $sortString;
                    $direction = 'asc';
                }
            }

            $this->orderByOpenApi($field, $direction);
        }

        return $this;
    }

    /**
     * Apply OpenAPI-defined filtering with advanced operators
     */
    public function withOpenApiFiltering(array $filters): self
    {
        if (!$this->apiModel->hasOpenApiSchema()) {
            return $this;
        }

        $definitions = $this->getParameterDefinitions();

        foreach ($filters as $field => $value) {
            if (!isset($definitions[$field])) {
                continue;
            }

            $definition = $definitions[$field];
            
            // Handle different filter formats
            if (is_array($value)) {
                $this->applyArrayFilter($field, $value, $definition);
            } else {
                $this->applyScalarFilter($field, $value, $definition);
            }
        }

        return $this;
    }

    /**
     * Apply array-based filters (e.g., range, in, not_in)
     */
    protected function applyArrayFilter(string $field, array $value, array $definition): void
    {
        // Store in API parameters
        $this->apiParameters[$field] = $value;
        
        // Handle range filters: ['min' => 10, 'max' => 100]
        if (isset($value['min']) || isset($value['max'])) {
            if (isset($value['min'])) {
                $this->where($field, '>=', $value['min']);
            }
            if (isset($value['max'])) {
                $this->where($field, '<=', $value['max']);
            }
            return;
        }

        // Handle operator-based filters: ['operator' => 'gt', 'value' => 10]
        if (isset($value['operator']) && isset($value['value'])) {
            $operator = $this->mapOperator($value['operator']);
            $this->where($field, $operator, $value['value']);
            return;
        }

        // Handle IN filters: ['in' => [1, 2, 3]]
        if (isset($value['in'])) {
            $this->whereIn($field, $value['in']);
            return;
        }

        // Handle NOT IN filters: ['not_in' => [1, 2, 3]]
        if (isset($value['not_in'])) {
            $this->whereNotIn($field, $value['not_in']);
            return;
        }

        // Default: treat as IN filter
        $this->whereIn($field, $value);
    }

    /**
     * Apply scalar filters
     */
    protected function applyScalarFilter(string $field, $value, array $definition): void
    {
        // Store in API parameters
        $this->apiParameters[$field] = $value;
        
        $type = $definition['type'] ?? 'string';
        
        // Handle different types appropriately
        switch ($type) {
            case 'string':
                if (isset($definition['enum'])) {
                    $this->where($field, '=', $value);
                } else {
                    // Use LIKE for string searches unless exact match is specified
                    $useExactMatch = $definition['x-exact-match'] ?? false;
                    if ($useExactMatch) {
                        $this->where($field, '=', $value);
                    } else {
                        $this->where($field, 'LIKE', "%{$value}%");
                    }
                }
                break;
                
            case 'integer':
            case 'number':
            case 'boolean':
                $this->where($field, '=', $value);
                break;
                
            default:
                $this->where($field, '=', $value);
                break;
        }
    }

    /**
     * Map OpenAPI filter operators to SQL operators
     */
    protected function mapOperator(string $operator): string
    {
        $mapping = [
            'eq' => '=',
            'ne' => '!=',
            'gt' => '>',
            'gte' => '>=',
            'lt' => '<',
            'lte' => '<=',
            'like' => 'LIKE',
            'not_like' => 'NOT LIKE',
            'in' => 'IN',
            'not_in' => 'NOT IN',
        ];

        return $mapping[$operator] ?? '=';
    }

    /**
     * Apply OpenAPI-defined pagination with smart parameter detection
     */
    public function withOpenApiPagination($pageOrParams, $perPage = null): self
    {
        if (!$this->apiModel->hasOpenApiSchema()) {
            return $this;
        }

        // Handle both array and separate parameter formats
        if (is_array($pageOrParams)) {
            $paginationParams = $pageOrParams;
        } else {
            $paginationParams = [
                'page' => $pageOrParams,
                'per_page' => $perPage
            ];
        }

        // Extract pagination parameters
        $limit = $paginationParams['limit'] ?? 
                 $paginationParams['per_page'] ?? 
                 $paginationParams['page_size'] ?? null;

        $offset = $paginationParams['offset'] ?? 
                  $paginationParams['skip'] ?? null;

        $page = $paginationParams['page'] ?? null;

        // Store pagination parameters in API parameters
        if ($page !== null) {
            $this->apiParameters['page'] = (int) $page;
        }
        if ($limit !== null) {
            $this->apiParameters['per_page'] = (int) $limit;
        }
        if ($offset !== null) {
            $this->apiParameters['offset'] = (int) $offset;
        }

        // Apply limit
        if ($limit !== null) {
            $this->limitOpenApi((int) $limit);
        }

        // Apply offset or page
        if ($offset !== null) {
            $this->offsetOpenApi((int) $offset);
        } elseif ($page !== null && $limit !== null) {
            $this->offsetOpenApi(((int) $page - 1) * (int) $limit);
        }

        return $this;
    }

    /**
     * Get parameter definitions from OpenAPI schema
     */
    protected function getParameterDefinitions(): array
    {
        if ($this->parameterDefinitions !== null) {
            return $this->parameterDefinitions;
        }

        if (!$this->apiModel->hasOpenApiSchema()) {
            return [];
        }

        $operations = $this->apiModel->getOpenApiOperations();
        $definitions = [];

        foreach ($operations as $operation) {
            if (isset($operation['endpoint_data']['parameters'])) {
                foreach ($operation['endpoint_data']['parameters'] as $param) {
                    $name = $param['name'] ?? '';
                    if ($name) {
                        $definitions[$name] = array_merge($param['schema'] ?? [], [
                            'in' => $param['in'] ?? 'query',
                            'required' => $param['required'] ?? false,
                            'description' => $param['description'] ?? null,
                            'style' => $param['style'] ?? 'simple',
                            'explode' => $param['explode'] ?? false,
                        ]);
                    }
                }
            }
        }

        $this->parameterDefinitions = $definitions;
        return $definitions;
    }

    /**
     * Serialize all API parameters for the request
     */
    public function serializeApiParameters(): array
    {
        if (empty($this->apiParameters)) {
            return [];
        }

        $definitions = $this->getParameterDefinitions();
        return OpenApiParameterSerializer::serialize($this->apiParameters, $definitions);
    }

    /**
     * Create dynamic scope methods based on OpenAPI attributes
     */
    public function __call($method, $parameters)
    {
        // Handle dynamic scopes first
        if ($this->apiModel->hasOpenApiSchema()) {
            $scopes = $this->createDynamicScopes();
            if (isset($scopes[$method])) {
                $scope = $scopes[$method];
                return $scope($this, ...$parameters);
            }
        }

        // Handle whereBy* methods for OpenAPI attributes
        if ($this->apiModel->hasOpenApiSchema() && str_starts_with($method, 'whereBy')) {
            $attribute = Str::snake(substr($method, 7));
            $definitions = $this->getParameterDefinitions();
            
            if (isset($definitions[$attribute])) {
                return $this->whereOpenApi($attribute, '=', $parameters[0] ?? null);
            }
        }

        // Handle orderBy* methods for OpenAPI attributes
        if ($this->apiModel->hasOpenApiSchema() && str_starts_with($method, 'orderBy')) {
            $attribute = Str::snake(substr($method, 7));
            $definitions = $this->getParameterDefinitions();
            
            if (isset($definitions[$attribute])) {
                $direction = $parameters[0] ?? 'asc';
                return $this->orderByOpenApi($attribute, $direction);
            }
        }

        // Handle scope* methods for OpenAPI parameters
        if ($this->apiModel->hasOpenApiSchema() && str_starts_with($method, 'scope')) {
            $paramName = Str::snake(substr($method, 5));
            $definitions = $this->getParameterDefinitions();
            
            if (isset($definitions[$paramName])) {
                $value = $parameters[0] ?? null;
                return $this->withOpenApiParams([$paramName => $value]);
            }
        }

        return parent::__call($method, $parameters);
    }

    /**
     * Add OpenAPI search parameter
     */
    public function withOpenApiSearch(string $searchTerm): self
    {
        if (!$this->apiModel->hasOpenApiSchema()) {
            return $this;
        }

        $this->apiParameters['search'] = $searchTerm;
        return $this;
    }

    /**
     * Serialize API parameters according to OpenAPI parameter styles
     */
    public function serializeParameters(): array
    {
        $serialized = [];
        $definitions = $this->getParameterDefinitions();

        foreach ($this->apiParameters as $name => $value) {
            $definition = $definitions[$name] ?? [];
            $style = $definition['style'] ?? 'simple';
            $explode = $definition['explode'] ?? false;

            if (is_array($value)) {
                switch ($style) {
                    case 'simple':
                        $serialized[$name] = implode(',', $value);
                        break;
                    case 'spaceDelimited':
                        $serialized[$name] = implode(' ', $value);
                        break;
                    case 'pipeDelimited':
                        $serialized[$name] = implode('|', $value);
                        break;
                    default:
                        $serialized[$name] = $explode ? $value : implode(',', $value);
                }
            } else {
                $serialized[$name] = $value;
            }
        }

        return $serialized;
    }

    /**
     * Detect the purpose of a parameter (filter, sort, pagination, search)
     */
    public function detectParameterPurpose(string $parameterName): string
    {
        // Common pagination parameters
        if (in_array($parameterName, ['page', 'per_page', 'limit', 'offset'])) {
            return 'pagination';
        }

        // Common sort parameters
        if (in_array($parameterName, ['sort', 'order', 'order_by'])) {
            return 'sort';
        }

        // Common search parameters
        if (in_array($parameterName, ['search', 'query', 'q'])) {
            return 'search';
        }

        // Default to filter for other parameters
        return 'filter';
    }

    /**
     * Clear all API parameters
     */
    public function clearApiParameters(): self
    {
        $this->apiParameters = [];
        return $this;
    }

    /**
     * Override whereIn to store API parameters
     */
    public function whereIn($column, $values, $boolean = 'and', $not = false)
    {
        // Store in API parameters if it's a valid OpenAPI parameter
        if ($this->apiModel->hasOpenApiSchema()) {
            $definitions = $this->getParameterDefinitions();
            if (isset($definitions[$column])) {
                $this->apiParameters[$column] = $values;
            }
        }

        return parent::whereIn($column, $values, $boolean, $not);
    }

    /**
     * Override whereNotIn to store API parameters
     */
    public function whereNotIn($column, $values, $boolean = 'and')
    {
        // Store in API parameters if it's a valid OpenAPI parameter
        if ($this->apiModel->hasOpenApiSchema()) {
            $definitions = $this->getParameterDefinitions();
            if (isset($definitions[$column])) {
                $this->apiParameters[$column] = ['not_in' => $values];
            }
        }

        return parent::whereNotIn($column, $values, $boolean);
    }

    /**
     * Handle operator-based where clauses
     */
    public function whereOperator(string $column, string $operator, $value): self
    {
        // Store in API parameters if it's a valid OpenAPI parameter
        if ($this->apiModel->hasOpenApiSchema()) {
            $definitions = $this->getParameterDefinitions();
            if (isset($definitions[$column])) {
                $this->apiParameters[$column] = ['operator' => $operator, 'value' => $value];
            }
        }

        // Map operator to SQL equivalent
        $sqlOperator = $this->mapOperator($operator);
        return $this->where($column, $sqlOperator, $value);
    }

    /**
     * Dynamic scope method for status filtering
     */
    public function scopeWithStatus($status): self
    {
        if ($this->apiModel->hasOpenApiSchema()) {
            $this->apiParameters['status'] = $status;
        }
        return $this->where('status', '=', $status);
    }

    /**
     * Dynamic scope method for limit filtering
     */
    public function scopeWithLimit($limit): self
    {
        if ($this->apiModel->hasOpenApiSchema()) {
            $this->apiParameters['limit'] = $limit;
        }
        return $this->limit($limit);
    }

    /**
     * Get parameter definition for a specific parameter
     */
    public function getParameterDefinition(string $parameterName): ?array
    {
        $definitions = $this->getParameterDefinitions();
        return $definitions[$parameterName] ?? null;
    }
}
