<?php

namespace MTechStack\LaravelApiModelClient\Query;

use MTechStack\LaravelApiModelClient\Contracts\ApiModelInterface;
use MTechStack\LaravelApiModelClient\Models\ApiModel;
use MTechStack\LaravelApiModelClient\Query\ApiPaginator;
use Illuminate\Support\Collection;
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
     * Create a new API query builder instance.
     *
     * @param \MTechStack\LaravelApiModelClient\Models\ApiModel $model
     * @return void
     */
    public function __construct(ApiModel $model)
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
     * Execute the query and get the first result.
     *
     * @return \MTechStack\LaravelApiModelClient\Models\ApiModel|null
     */
    public function first()
    {
        return $this->limit(1)->get()->first();
    }

    /**
     * Execute the query and get all results.
     *
     * @return \Illuminate\Support\Collection
     */
    public function get()
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
            return new Collection();
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
        
        // Create a collection of models
        $models = [];
        
        foreach ($items as $item) {
            $model = $this->model->newFromApiResponse($item);
            if ($model !== null) {
                $models[] = $model;
            }
        }
        
        return new Collection($models);
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
        $models = [];
        
        foreach ($items as $item) {
            $model = $this->model->newFromApiResponse($item);
            if ($model !== null) {
                $models[] = $model;
            }
        }
        
        return new Collection($models);
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
        $queryString = md5(json_encode($this->buildQueryParams()));
        
        return $prefix . $class . '_query_' . $queryString;
    }

    /**
     * Get the API client instance.
     *
     * @return \MTechStack\LaravelApiModelClient\Contracts\ApiClientInterface
     */
    protected function getApiClient()
    {
        return App::make('api-client');
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
                new Collection(),
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
}
