<?php

namespace MTechStack\LaravelApiModelClient\Query;

use MTechStack\LaravelApiModelClient\Contracts\ApiClientInterface;
use MTechStack\LaravelApiModelClient\Services\ApiClient;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Traits\Macroable;

class ApiQueryBuilder
{
    use Macroable;
    /**
     * The API model instance.
     *
     * @var \MTechStack\LaravelApiModelClient\Models\ApiModel
     */
    protected $model;

    /**
     * The where constraints for the query.
     *
     * @var array
     */
    protected $wheres = [];

    /**
     * The orderings for the query.
     *
     * @var array
     */
    protected $orders = [];

    /**
     * The maximum number of records to return.
     *
     * @var int|null
     */
    protected $limit;

    /**
     * The number of records to skip.
     *
     * @var int|null
     */
    protected $offset;

    /**
     * The columns to select.
     *
     * @var array
     */
    protected $columns = ['*'];

    /**
     * The relationships that should be eager loaded.
     *
     * @var array
     */
    protected $eagerLoad = [];

    /**
     * Create a new API query builder instance.
     *
     * @param \MTechStack\LaravelApiModelClient\Models\ApiModel $model
     * @return void
     */
    public function __construct($model)
    {
        $this->model = $model;
    }

    /**
     * Add a basic where clause to the query.
     *
     * @param string $column
     * @param mixed $operator
     * @param mixed $value
     * @param string $boolean
     * @return $this
     */
    public function where($column, $operator = null, $value = null, $boolean = 'and')
    {
        // If only two arguments are provided, assume the operator is '='
        if ($value === null && $operator !== null) {
            $value = $operator;
            $operator = '=';
        }

        $this->wheres[] = [
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => $boolean,
        ];

        return $this;
    }

    /**
     * Add an "or where" clause to the query.
     *
     * @param string $column
     * @param mixed $operator
     * @param mixed $value
     * @return $this
     */
    public function orWhere($column, $operator = null, $value = null)
    {
        return $this->where($column, $operator, $value, 'or');
    }

    /**
     * Add a "where in" clause to the query.
     *
     * @param string $column
     * @param array $values
     * @param string $boolean
     * @return $this
     */
    public function whereIn($column, array $values, $boolean = 'and')
    {
        $this->wheres[] = [
            'column' => $column,
            'operator' => 'in',
            'value' => $values,
            'boolean' => $boolean,
        ];

        return $this;
    }

    /**
     * Add an "or where in" clause to the query.
     *
     * @param string $column
     * @param array $values
     * @return $this
     */
    public function orWhereIn($column, array $values)
    {
        return $this->whereIn($column, $values, 'or');
    }

    /**
     * Add an "order by" clause to the query.
     *
     * @param string $column
     * @param string $direction
     * @return $this
     */
    public function orderBy($column, $direction = 'asc')
    {
        $this->orders[] = [
            'column' => $column,
            'direction' => strtolower($direction) === 'asc' ? 'asc' : 'desc',
        ];

        return $this;
    }

    /**
     * Add a descending "order by" clause to the query.
     *
     * @param string $column
     * @return $this
     */
    public function orderByDesc($column)
    {
        return $this->orderBy($column, 'desc');
    }

    /**
     * Set the "limit" value of the query.
     *
     * @param int $limit
     * @return $this
     */
    public function limit($limit)
    {
        $this->limit = $limit;

        return $this;
    }

    /**
     * Alias for the "limit" method.
     *
     * @param int $value
     * @return $this
     */
    public function take($value)
    {
        return $this->limit($value);
    }

    /**
     * Set the "offset" value of the query.
     *
     * @param int $offset
     * @return $this
     */
    public function offset($offset)
    {
        $this->offset = $offset;

        return $this;
    }

    /**
     * Alias for the "offset" method.
     *
     * @param int $offset
     * @return $this
     */
    public function skip($offset)
    {
        return $this->offset($offset);
    }

    /**
     * Set the "limit" and "offset" for a given page.
     *
     * @param int $page
     * @param int $perPage
     * @return $this
     */
    public function forPage($page, $perPage = 15)
    {
        return $this->skip(($page - 1) * $perPage)->limit($perPage);
    }

    /**
     * Set the columns to be selected.
     *
     * @param array|mixed $columns
     * @return $this
     */
    public function select($columns = ['*'])
    {
        $this->columns = is_array($columns) ? $columns : func_get_args();

        return $this;
    }

    /**
     * Set the relationships that should be eager loaded.
     *
     * @param array|string $relations
     * @return $this
     */
    public function with($relations)
    {
        $eagerLoad = $this->parseWithRelations(is_string($relations) ? func_get_args() : $relations);

        $this->eagerLoad = array_merge($this->eagerLoad, $eagerLoad);

        return $this;
    }

    /**
     * Parse a list of relations into individuals.
     *
     * @param array $relations
     * @return array
     */
    protected function parseWithRelations(array $relations)
    {
        $results = [];

        foreach ($relations as $name => $constraints) {
            // If numeric key, then it's just a relation name
            if (is_numeric($name)) {
                $results[$constraints] = function () {
                    //
                };
            } else {
                $results[$name] = $constraints;
            }
        }

        return $results;
    }

    /**
     * Add a relationship count / exists condition to the query.
     *
     * @param string $relation
     * @param string $operator
     * @param int $count
     * @param string $boolean
     * @param \Closure|null $callback
     * @return $this
     */
    public function has($relation, $operator = '>=', $count = 1, $boolean = 'and', $callback = null)
    {
        // For API models, we'll implement this as a simple filter
        // This is a placeholder implementation - you may need to customize based on your API
        return $this->where($relation . '_count', $operator, $count);
    }

    /**
     * Add a relationship count / exists condition to the query with where clauses.
     *
     * @param string $relation
     * @param \Closure|null $callback
     * @param string $operator
     * @param int $count
     * @return $this
     */
    public function whereHas($relation, $callback = null, $operator = '>=', $count = 1)
    {
        return $this->has($relation, $operator, $count, 'and', $callback);
    }

    /**
     * Add a relationship count / exists condition to the query with an "or".
     *
     * @param string $relation
     * @param \Closure|null $callback
     * @param string $operator
     * @param int $count
     * @return $this
     */
    public function orWhereHas($relation, $callback = null, $operator = '>=', $count = 1)
    {
        return $this->has($relation, $operator, $count, 'or', $callback);
    }

    /**
     * Add a relationship count / exists condition to the query.
     *
     * @param string $relation
     * @param string $boolean
     * @param \Closure|null $callback
     * @return $this
     */
    public function doesntHave($relation, $boolean = 'and', $callback = null)
    {
        return $this->has($relation, '<', 1, $boolean, $callback);
    }

    /**
     * Add a relationship count / exists condition to the query with where clauses.
     *
     * @param string $relation
     * @param \Closure|null $callback
     * @return $this
     */
    public function whereDoesntHave($relation, $callback = null)
    {
        return $this->doesntHave($relation, 'and', $callback);
    }

    /**
     * Add a relationship count / exists condition to the query with an "or".
     *
     * @param string $relation
     * @param \Closure|null $callback
     * @return $this
     */
    public function orWhereDoesntHave($relation, $callback = null)
    {
        return $this->doesntHave($relation, 'or', $callback);
    }

    /**
     * Execute the query and get the first result.
     *
     * @return \MTechStack\LaravelApiModelClient\Models\ApiModel|null
     */
    public function first()
    {
        return $this->limit(1)->get()->first();
    }

    /**
     * Execute the query and get all results with pagination support.
     *
     * @return \Illuminate\Support\Collection
     */
    public function get()
    {
        // Check if model uses HasApiCache trait for polymorphic caching
        if (in_array('MTechStack\LaravelApiModelClient\Traits\HasApiCache', class_uses($this->model))) {
            return $this->getWithPolymorphicCache();
        }

        // Fall back to original caching system
        return $this->getWithOriginalCache();
    }

    /**
     * Get results using polymorphic cache system with pagination support.
     *
     * @return \Illuminate\Support\Collection
     */
    protected function getWithPolymorphicCache()
    {
        $queryParams = $this->buildQueryParams();
        $instance = new (get_class($this->model))();
        $strategy = $instance->getCacheStrategy();

        switch ($strategy) {
            case 'cache_only':
                return $this->getFromCacheWithPagination($queryParams);

            case 'api_only':
                return $this->getFromApiWithPagination($queryParams);

            case 'hybrid':
            default:
                // For paginated requests, prefer API to ensure accurate pagination
                if (!empty($queryParams['limit']) || !empty($queryParams['offset'])) {
                    return $this->getFromApiWithPagination($queryParams);
                }

                // For non-paginated requests, try cache first
                $cached = $this->getFromCacheWithPagination($queryParams);
                return $cached->isNotEmpty() ? $cached : $this->getFromApiWithPagination($queryParams);
        }
    }

    /**
     * Get results from cache with pagination applied.
     *
     * @param array $queryParams
     * @return \Illuminate\Support\Collection
     */
    protected function getFromCacheWithPagination($queryParams)
    {
        $instance = new (get_class($this->model))();
        $apiCacheClass = app()->bound('ApiCache') ? app('ApiCache') : '\MTechStack\LaravelApiModelClient\Models\ApiCache';
        $cacheQuery = $apiCacheClass::forType($instance->getCacheableType())
                                ->fresh($instance->getCacheTtl());

        // Apply pagination to cache query
        if (isset($queryParams['limit']) && $queryParams['limit'] > 0) {
            $cacheQuery->limit($queryParams['limit']);
        }
        if (isset($queryParams['offset']) && $queryParams['offset'] > 0) {
            $cacheQuery->offset($queryParams['offset']);
        }

        $cacheEntries = $cacheQuery->get();

        return $cacheEntries->map(function ($cache) use ($instance) {
            return $instance->newFromApiResponse($cache->api_data);
        });
    }

    /**
     * Get results from API with pagination and cache them.
     *
     * @param array $queryParams
     * @return \Illuminate\Support\Collection
     */
    protected function getFromApiWithPagination($queryParams)
    {
        try {
            // Make API request with pagination parameters
            $endpoint = $this->model->getApiEndpoint();
            $response = $this->getApiClient()->get($endpoint, $queryParams);

            // Process API response
            $items = $this->processApiResponse($response);

            // Cache the individual items for future use
            foreach ($items as $item) {
                if (isset($item->id) && is_array($item->getAttributes())) {
                    $item->cacheApiData($item->getAttributes());
                }
            }

            return $items;
        } catch (\Exception $e) {
            // Log the error if configured to do so
            if (config('api-model-relations.error_handling.log_errors', true)) {
                \Illuminate\Support\Facades\Log::error('Error executing paginated API query', [
                    'endpoint' => $this->model->getApiEndpoint(),
                    'query_params' => $queryParams,
                    'exception' => $e->getMessage(),
                ]);
            }

            return new EloquentCollection();
        }
    }

    /**
     * Get results using original cache system (fallback).
     *
     * @return \Illuminate\Support\Collection
     */
    protected function getWithOriginalCache()
    {
        $cacheKey = $this->getCacheKey();
        $cacheTtl = $this->model->getCacheTtl();

        // Check if we have a cached response
        if (config('api-model-relations.cache.enabled', true) && $cacheTtl > 0) {
            $cachedData = Cache::get($cacheKey);
            if ($cachedData !== null) {
                return $this->processApiResponse($cachedData);
            }
        }

        try {
            // Build the query parameters
            $queryParams = $this->buildQueryParams();

            // Make API request
            $endpoint = $this->model->getApiEndpoint();
            $response = $this->getApiClient()->get($endpoint, $queryParams);

            // Cache the response if caching is enabled
            if (config('api-model-relations.cache.enabled', true) && $cacheTtl > 0) {
                Cache::put($cacheKey, $response, $cacheTtl);
            }

            return $this->processApiResponse($response);
        } catch (\Exception $e) {
            // Log the error if configured to do so
            if (config('api-model-relations.error_handling.log_errors', true)) {
                \Illuminate\Support\Facades\Log::error('Error executing API query', [
                    'endpoint' => $this->model->getApiEndpoint(),
                    'query_params' => $this->buildQueryParams(),
                    'exception' => $e->getMessage(),
                ]);
            }

            // Return empty collection
            return new EloquentCollection();
        }
    }

    /**
     * Alias for the get() method.
     *
     * @return \Illuminate\Support\Collection
     */
    public function getFromApi()
    {
        return $this->get();
    }

    /**
     * Process the API response into a collection of models.
     *
     * @param array $response
     * @return \Illuminate\Support\Collection
     */
    protected function processApiResponse($response)
    {
        // Extract items from the response
        $items = $this->extractItemsFromResponse($response);

        // Create a collection of models using reflection to bypass __call interference
        $models = [];

        foreach ($items as $item) {
            if (empty($item)) {
                continue;
            }

            try {
                // Create a fresh instance of the model class
                $modelClass = get_class($this->model);
                $newModel = new $modelClass();

                // Use reflection to call newFromApiResponse directly, bypassing all __call interference
                $reflection = new \ReflectionClass($newModel);
                if ($reflection->hasMethod('newFromApiResponse')) {
                    $method = $reflection->getMethod('newFromApiResponse');
                    $model = $method->invoke($newModel, $item);
                    if ($model !== null) {
                        $models[] = $model;
                    }
                }

            } catch (\Exception $e) {
                // Log error but continue processing
                error_log("Failed to create model from API response: " . $e->getMessage());
            }
        }

        $collection = new EloquentCollection($models);

        // Load eager relationships if any are specified
        if (!empty($this->eagerLoad)) {
            $collection = $this->eagerLoadRelations($collection);
        }

        return $collection;
    }

    /**
     * Eager load the relationships for the models.
     *
     * @param \Illuminate\Support\Collection $models
     * @return \Illuminate\Support\Collection
     */
    protected function eagerLoadRelations($models)
    {
        foreach ($this->eagerLoad as $name => $constraints) {
            // Load the relationship for each model
            $models = $this->eagerLoadRelation($models, $name, $constraints);
        }

        return $models;
    }

    /**
     * Eagerly load the relationship on a set of models.
     *
     * @param \Illuminate\Support\Collection $models
     * @param string $name
     * @param \Closure $constraints
     * @return \Illuminate\Support\Collection
     */
    protected function eagerLoadRelation($models, $name, $constraints)
    {
        // For API models, we'll try to load relationships if the model supports it
        foreach ($models as $model) {
            if (method_exists($model, 'load')) {
                try {
                    $model->load([$name => $constraints]);
                } catch (\Exception $e) {
                    // Log error but continue processing
                    error_log("Failed to eager load relationship '{$name}': " . $e->getMessage());
                }
            }
        }

        return $models;
    }

    /**
     * Create models from API response items.
     * Alias for processApiResponse for backward compatibility.
     *
     * @param array $items
     * @return \Illuminate\Support\Collection
     */
    public function createModelsFromItems($items)
    {
        // Create a collection of models using reflection to bypass __call interference
        $models = [];

        foreach ($items as $item) {
            if (empty($item)) {
                continue;
            }

            try {
                // Create a fresh instance of the model class
                $modelClass = get_class($this->model);
                $newModel = new $modelClass();

                // Use reflection to call newFromApiResponse directly, bypassing all __call interference
                $reflection = new \ReflectionClass($newModel);
                if ($reflection->hasMethod('newFromApiResponse')) {
                    $method = $reflection->getMethod('newFromApiResponse');
                    $model = $method->invoke($newModel, $item);
                    if ($model !== null) {
                        $models[] = $model;
                    }
                }

            } catch (\Exception $e) {
                // Log error but continue processing
                error_log("Failed to create model from API response: " . $e->getMessage());
            }
        }

        return new EloquentCollection($models);
    }

    /**
     * Extract items from an API response, handling different response formats.
     *
     * @param array $response
     * @return array
     */
    protected function extractItemsFromResponse($response)
    {
        // If response is already an array of items, return it
        if (isset($response[0])) {
            return $response;
        }

        // Check for common wrapper keys
        $possibleKeys = ['data', 'items', 'results', 'records', 'content'];

        foreach ($possibleKeys as $key) {
            if (isset($response[$key]) && is_array($response[$key])) {
                if (isset($response[$key]['id'])) { return $response; }
                return $response[$key];
            }
        }

        // If we can't find a collection, return an empty array
        return [];
    }

    /**
     * Build the query parameters for the API request.
     *
     * @return array
     */
    protected function buildQueryParams()
    {
        $params = [];

        // Add where constraints
        foreach ($this->wheres as $where) {
            $this->addWhereToParams($params, $where);
        }

        // Add ordering
        if (!empty($this->orders)) {
            $this->addOrderingToParams($params);
        }

        // Add pagination
        if ($this->limit !== null) {
            $params['limit'] = $this->limit;
        }

        if ($this->offset !== null) {
            $params['offset'] = $this->offset;
        }

        // Add columns to select
        if ($this->columns !== ['*']) {
            $params['fields'] = implode(',', $this->columns);
        }

        // Add eager loading relationships
        if (!empty($this->eagerLoad)) {
            $params['include'] = implode(',', array_keys($this->eagerLoad));
        }

        return $params;
    }

    /**
     * Add a where constraint to the query parameters.
     *
     * @param array $params
     * @param array $where
     * @return void
     */
    protected function addWhereToParams(&$params, $where)
    {
        $column = $where['column'];
        $operator = $where['operator'];
        $value = $where['value'];

        // Handle different operators
        switch ($operator) {
            case '=':
                $params[$column] = $value;
                break;
            case 'in':
                $params[$column] = implode(',', $value);
                break;
            default:
                // For other operators, use a format like column[operator]=value
                $params[$column . '[' . $operator . ']'] = $value;
                break;
        }
    }

    /**
     * Add ordering to the query parameters.
     *
     * @param array $params
     * @return void
     */
    protected function addOrderingToParams(&$params)
    {
        $orders = [];

        foreach ($this->orders as $order) {
            $direction = $order['direction'] === 'asc' ? '' : '-';
            $orders[] = $direction . $order['column'];
        }

        $params['sort'] = implode(',', $orders);
    }

    /**
     * Get a cache key for this query.
     *
     * @return string
     */
    protected function getCacheKey()
    {
        $prefix = config('api-model-relations.cache.prefix', 'api_model_');
        $class = str_replace('\\', '_', get_class($this->model));
        $queryString = md5($this->model->getApiEndpoint().json_encode($this->buildQueryParams()));

        return $prefix . $class . '_query_' . $queryString;
    }

    /**
     * Get the API client instance with robust fallback mechanisms.
     *
     * @return \MTechStack\LaravelApiModelClient\Contracts\ApiClientInterface
     * @throws \RuntimeException
     */
    protected function getApiClient()
    {
        // Strategy 1: Try to get the API client from the service container
        try {
            if (App::bound('api-client')) {
                return App::make('api-client');
            }
        } catch (\Exception $e) {
            // Continue to next strategy
        }

        // Strategy 2: Try the interface binding
        try {
            if (App::bound(ApiClientInterface::class)) {
                return App::make(ApiClientInterface::class);
            }
        } catch (\Exception $e) {
            // Continue to next strategy
        }

        // Strategy 3: Check if the model has its own API client method
        if (method_exists($this->model, 'getApiClient')) {
            try {
                $client = $this->model->getApiClient();
                if ($client instanceof ApiClientInterface) {
                    return $client;
                }
            } catch (\Exception $e) {
                // Continue to next strategy
            }
        }

        // Strategy 4: Create a default API client instance
        try {
            if (class_exists(ApiClient::class)) {
                $config = $this->getApiClientConfig();
                return new ApiClient($config);
            }
        } catch (\Exception $e) {
            // Continue to error handling
        }

        // If all strategies fail, provide helpful error message
        throw new \RuntimeException(
            "Unable to resolve API client. This usually means:\n" .
            "1. Laravel package auto-discovery is disabled\n" .
            "2. The package was not properly installed via Composer\n" .
            "3. Laravel application context is not available\n\n" .
            "To fix this:\n" .
            "- Ensure the package was installed via: composer require m-tech-stack/laravel-api-model-client\n" .
            "- Check that Laravel package auto-discovery is enabled (default)\n" .
            "- If auto-discovery is disabled, manually add the provider to config/app.php:\n" .
            "  MTechStack\\LaravelApiModelClient\\ApiModelRelationsServiceProvider::class\n" .
            "- Run: php artisan vendor:publish --provider=\"MTechStack\\LaravelApiModelClient\\ApiModelRelationsServiceProvider\"\n" .
            "- Ensure your .env has proper API configuration\n\n" .
            "For more help, see: https://github.com/mabou7agar/laravel-api-model-client#installation"
        );
    }

    /**
     * Get API client configuration with sensible defaults.
     *
     * @return array
     */
    protected function getApiClientConfig(): array
    {
        // Try to get configuration from Laravel config
        if (function_exists('config')) {
            $config = config('api-model-client', []);
            if (!empty($config)) {
                return $config;
            }
        }

        // Fallback to basic configuration
        return [
            'client' => [
                'base_url' => env('API_CLIENT_BASE_URL', ''),
                'timeout' => env('API_CLIENT_TIMEOUT', 30),
                'retry_attempts' => env('API_CLIENT_RETRY_ATTEMPTS', 3),
            ],
            'auth' => [
                'strategy' => env('API_CLIENT_AUTH_STRATEGY', null),
                'credentials' => [
                    'token' => env('API_CLIENT_TOKEN', null),
                    'api_key' => env('API_CLIENT_API_KEY', null),
                    'username' => env('API_CLIENT_USERNAME', null),
                    'password' => env('API_CLIENT_PASSWORD', null),
                ],
            ],
            'cache' => [
                'enabled' => env('API_CLIENT_CACHE_ENABLED', true),
                'ttl' => env('API_CLIENT_CACHE_TTL', 3600),
            ],
            'error_handling' => [
                'log_errors' => env('API_CLIENT_LOG_ERRORS', true),
                'throw_exceptions' => env('API_CLIENT_THROW_EXCEPTIONS', true),
            ],
        ];
    }

    /**
     * Get a new instance of the query builder.
     *
     * @return static
     */
    public function newQuery()
    {
        return new static($this->model);
    }

    /**
     * Get the model instance being queried.
     *
     * @return \MTechStack\LaravelApiModelClient\Models\ApiModel
     */
    public function getModel()
    {
        return $this->model;
    }

    /**
     * Paginate the given query.
     *
     * @param int $perPage
     * @param array $columns
     * @param string $pageName
     * @param int|null $page
     * @return \MTechStack\LaravelApiModelClient\Query\ApiPaginator
     */
    public function paginate($perPage = 15, $columns = ['*'], $pageName = 'page', $page = null)
    {
        $page = $page ?: request()->input($pageName, 1);

        // Set the columns to be selected
        $this->select($columns);

        // Set the pagination parameters
        $this->forPage($page, $perPage);

        $cacheKey = $this->getCacheKey();
        $cacheTtl = $this->model->getCacheTtl();

        // Check if we have a cached response
        if (config('api-model-relations.cache.enabled', true) && $cacheTtl > 0) {
            $cachedData = Cache::get($cacheKey);
            if ($cachedData !== null) {
                return ApiPaginator::fromApiResponse(
                    $this->model,
                    $cachedData,
                    $perPage,
                    $page,
                    [
                        'path' => request()->url(),
                        'pageName' => $pageName,
                    ]
                );
            }
        }

        try {
            // Build the query parameters
            $queryParams = $this->buildQueryParams();

            // Make API request
            $endpoint = $this->model->getApiEndpoint();
            $response = $this->getApiClient()->get($endpoint, $queryParams);

            // Cache the response if caching is enabled
            if (config('api-model-relations.cache.enabled', true) && $cacheTtl > 0) {
                Cache::put($cacheKey, $response, $cacheTtl);
            }

            return ApiPaginator::fromApiResponse(
                $this->model,
                $response,
                $perPage,
                $page,
                [
                    'path' => request()->url(),
                    'pageName' => $pageName,
                ]
            );
        } catch (\Exception $e) {
            // Log the error if configured to do so
            if (config('api-model-relations.error_handling.log_errors', true)) {
                \Illuminate\Support\Facades\Log::error('Error executing API query for pagination', [
                    'endpoint' => $this->model->getApiEndpoint(),
                    'query_params' => $this->buildQueryParams(),
                    'exception' => $e->getMessage(),
                ]);
            }

            // Return empty paginator
            return new ApiPaginator(
                new EloquentCollection(),
                0,
                $perPage,
                $page,
                [
                    'path' => request()->url(),
                    'pageName' => $pageName,
                ],
                []
            );
        }
    }

    /**
     * Paginate the given query into a simple paginator.
     *
     * @param int $perPage
     * @param array $columns
     * @param string $pageName
     * @param int|null $page
     * @return \MTechStack\LaravelApiModelClient\Query\ApiPaginator
     */
    public function simplePaginate($perPage = 15, $columns = ['*'], $pageName = 'page', $page = null)
    {
        // For simplicity, we'll just use the regular paginate method
        return $this->paginate($perPage, $columns, $pageName, $page);
    }

    /**
     * Handle dynamic method calls into the method.
     *
     * @param string $method
     * @param array $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        // Check if this is a scope method call
        if ($this->hasScopeMethod($method)) {
            return $this->callScope($method, $parameters);
        }

        // Check for macros
        if (static::hasMacro($method)) {
            return $this->macroCall($method, $parameters);
        }

        // If method doesn't exist, throw an exception
        throw new \BadMethodCallException(sprintf(
            'Call to undefined method %s::%s()',
            static::class,
            $method
        ));
    }

    /**
     * Check if the model has a scope method for the given method name.
     *
     * @param string $method
     * @return bool
     */
    protected function hasScopeMethod($method)
    {
        $scopeMethod = 'scope' . ucfirst($method);
        return method_exists($this->model, $scopeMethod);
    }

    /**
     * Call a scope method on the model.
     *
     * @param string $method
     * @param array $parameters
     * @return $this
     */
    protected function callScope($method, $parameters)
    {
        $scopeMethod = 'scope' . ucfirst($method);

        // Create a fresh instance of the model to call the scope method
        $modelInstance = new (get_class($this->model))();

        // Call the scope method with this query builder as the first parameter
        array_unshift($parameters, $this);

        $result = call_user_func_array([$modelInstance, $scopeMethod], $parameters);

        // Scope methods should return the query builder instance
        return $result instanceof static ? $result : $this;
    }

    /**
     * Find a model by its primary key.
     *
     * @param  mixed  $id
     * @param  array  $columns
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function find($id, $columns = ['*'])
    {
        $this->model->setApiEndpoint($this->model->getApiEndpoint().'/'.$id);
        // Get the first result
        return $this->first();
    }

    /**
     * Find a model by its primary key or throw an exception.
     *
     * @param  mixed  $id
     * @param  array  $columns
     * @return \Illuminate\Database\Eloquent\Model
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function findOrFail($id, $columns = ['*'])
    {
        $result = $this->model->find($id, $columns);

        if (is_null($result)) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException(
                'No query results for model [' . get_class($this->model) . '] ' . $id
            );
        }

        return $result;
    }

    /**
     * Find multiple models by their primary keys.
     *
     * @param  \Illuminate\Contracts\Support\Arrayable|array  $ids
     * @param  array  $columns
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function findMany($ids, $columns = ['*'])
    {
        if ($ids instanceof \Illuminate\Contracts\Support\Arrayable) {
            $ids = $ids->toArray();
        }

        $results = new \Illuminate\Database\Eloquent\Collection();

        foreach ($ids as $id) {
            $model = $this->model->find($id, $columns);
            if ($model) {
                $results->push($model);
            }
        }

        return $results;
    }

    /**
     * Execute the query and get the first result or throw an exception.
     *
     * @param  array  $columns
     * @return \Illuminate\Database\Eloquent\Model
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function firstOrFail($columns = ['*'])
    {
        $result = $this->first();

        if (is_null($result)) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException(
                'No query results for model [' . get_class($this->model) . ']'
            );
        }

        return $result;
    }

    /**
     * Execute the query and get the first result or call a callback.
     *
     * @param  \Closure|array  $columns
     * @param  \Closure|null  $callback
     * @return \Illuminate\Database\Eloquent\Model|mixed
     */
    public function firstOr($columns = ['*'], $callback = null)
    {
        if ($columns instanceof \Closure) {
            $callback = $columns;
            $columns = ['*'];
        }

        $result = $this->first();

        if (is_null($result)) {
            return $callback ? $callback() : null;
        }

        return $result;
    }

    /**
     * Get a single column's value from the first result of a query.
     *
     * @param  string  $column
     * @return mixed
     */
    public function value($column)
    {
        $result = $this->first();
        return $result ? $result->{$column} : null;
    }

    /**
     * Get an array with the values of a given column.
     *
     * @param  string  $column
     * @param  string|null  $key
     * @return \Illuminate\Support\Collection
     */
    public function pluck($column, $key = null)
    {
        $results = $this->get();

        if ($key) {
            return $results->pluck($column, $key);
        }

        return $results->pluck($column);
    }

    /**
     * Retrieve the "count" result of the query.
     *
     * @param  string  $columns
     * @return int
     */
    public function count($columns = '*')
    {
        // For API queries, we'll get all results and count them
        // In a real implementation, you might want to use a dedicated count endpoint
        return $this->get()->count();
    }

    /**
     * Retrieve the minimum value of a given column.
     *
     * @param  string  $column
     * @return mixed
     */
    public function min($column)
    {
        $results = $this->get();
        return $results->min($column);
    }

    /**
     * Retrieve the maximum value of a given column.
     *
     * @param  string  $column
     * @return mixed
     */
    public function max($column)
    {
        $results = $this->get();
        return $results->max($column);
    }

    /**
     * Retrieve the sum of the values of a given column.
     *
     * @param  string  $column
     * @return mixed
     */
    public function sum($column)
    {
        $results = $this->get();
        return $results->sum($column);
    }

    /**
     * Retrieve the average of the values of a given column.
     *
     * @param  string  $column
     * @return mixed
     */
    public function avg($column)
    {
        $results = $this->get();
        return $results->avg($column);
    }

    /**
     * Alias for the "avg" method.
     *
     * @param  string  $column
     * @return mixed
     */
    public function average($column)
    {
        return $this->avg($column);
    }

    /**
     * Determine if any rows exist for the current query.
     *
     * @return bool
     */
    public function exists()
    {
        return $this->count() > 0;
    }

    /**
     * Determine if no rows exist for the current query.
     *
     * @return bool
     */
    public function doesntExist()
    {
        return !$this->exists();
    }

    /**
     * Create or update a record matching the attributes, and fill it with values.
     *
     * @param  array  $attributes
     * @param  array  $values
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function updateOrCreate(array $attributes, array $values = [])
    {
        $instance = $this->firstOrNew($attributes);
        $instance->fill($values);
        $instance->save();

        return $instance;
    }

    /**
     * Get the first record matching the attributes or instantiate it.
     *
     * @param  array  $attributes
     * @param  array  $values
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function firstOrNew(array $attributes = [], array $values = [])
    {
        // Apply where clauses for attributes
        $query = clone $this;
        foreach ($attributes as $key => $value) {
            $query->where($key, $value);
        }

        $instance = $query->first();

        if (is_null($instance)) {
            $instance = $this->model->newInstance(array_merge($attributes, $values));
        }

        return $instance;
    }

    /**
     * Get the first record matching the attributes or create it.
     *
     * @param  array  $attributes
     * @param  array  $values
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function firstOrCreate(array $attributes, array $values = [])
    {
        $instance = $this->firstOrNew($attributes, $values);

        if (!$instance->exists) {
            $instance->save();
        }

        return $instance;
    }
}
