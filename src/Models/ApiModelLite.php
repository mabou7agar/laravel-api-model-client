<?php

namespace MTechStack\LaravelApiModelClient\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Lightweight API Model — extends Eloquent Model with simple REST API support.
 *
 * Provides: ::find(), ::where()->get(), ::all() via HTTP.
 * No HybridDataSource, no caching traits, no OpenAPI, no sync.
 * Memory-safe: boots in <1MB vs ~50MB+ for the full ApiModel.
 */
abstract class ApiModelLite extends Model
{
    /**
     * The REST API endpoint (e.g. 'products', 'categories').
     * Must be set by subclasses.
     */
    protected $apiEndpoint;

    /**
     * Base URL for API requests. Falls back to config.
     */
    protected $apiBaseUrl;

    /**
     * Disable DB table requirement — API models don't have tables.
     */
    protected $table = 'api_model_stub';

    /**
     * Store raw API response data.
     */
    protected $apiResponseData = null;

    /**
     * The model is not persisted to DB.
     */
    public $incrementing = false;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        if (!isset($this->table) || $this->table === 'api_model_stub') {
            $this->setTable(Str::snake(Str::pluralStudly(class_basename($this))));
        }
    }

    // ─── Query Methods ───────────────────────────────────────────

    /**
     * Find a model by ID from the API.
     */
    public static function find($id, $columns = ['*'])
    {
        $instance = new static;

        try {
            $response = $instance->apiGet($instance->getApiEndpoint() . '/' . $id);

            if (empty($response)) {
                return null;
            }

            return $instance->newFromApiResponse($response);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Find or fail.
     */
    public static function findOrFail($id, $columns = ['*'])
    {
        $model = static::find($id, $columns);

        if (!$model) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException(
                'No query results for model [' . static::class . '] ' . $id
            );
        }

        return $model;
    }

    /**
     * Get all models from the API.
     */
    public static function all($columns = ['*'])
    {
        $instance = new static;

        try {
            $response = $instance->apiGet($instance->getApiEndpoint());
            $items = $instance->extractItems($response);

            return collect($items)->map(fn($item) => (new static)->newFromApiResponse($item))->filter();
        } catch (\Throwable $e) {
            return collect();
        }
    }

    /**
     * Simple where builder — returns a pending query.
     */
    public static function where($column, $operator = null, $value = null)
    {
        return new ApiLiteQueryBuilder(new static, $column, $operator, $value);
    }

    // ─── API Client ──────────────────────────────────────────────

    /**
     * Make a GET request to the API.
     */
    public function apiGet(string $endpoint, array $params = []): ?array
    {
        $url = rtrim($this->getBaseUrl(), '/') . '/' . ltrim($endpoint, '/');

        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        $response = Http::timeout(15)->get($url);

        if ($response->failed()) {
            return null;
        }

        return $response->json();
    }

    /**
     * Get the base URL for API requests.
     */
    protected function getBaseUrl(): string
    {
        if ($this->apiBaseUrl) {
            return $this->apiBaseUrl;
        }

        return config('api-model-client.client.base_url')
            ?? config('api-model-client.base_url')
            ?? config('bagisto.api_url', '');
    }

    /**
     * Get the API endpoint.
     */
    public function getApiEndpoint(): string
    {
        return $this->apiEndpoint ?? Str::snake(Str::pluralStudly(class_basename($this)));
    }

    // ─── Response Mapping ────────────────────────────────────────

    /**
     * Create a model instance from an API response.
     */
    public function newFromApiResponse(array $response): ?static
    {
        $data = $response;

        // Unwrap nested data
        if (isset($response['data']) && is_array($response['data'])) {
            $nested = $response['data'];
            if (!isset($nested[0])) {
                $data = $nested;
            }
        }

        if (empty($data) || !is_array($data)) {
            return null;
        }

        $model = new static;
        $model->exists = true;
        $model->apiResponseData = $response;

        // Set all response fields as attributes
        foreach ($data as $key => $value) {
            if (is_string($key)) {
                $model->setAttribute($key, $value);
            }
        }

        return $model;
    }

    /**
     * Extract items array from API response.
     */
    public function extractItems(?array $response): array
    {
        if (!$response) {
            return [];
        }

        if (isset($response['data']) && is_array($response['data'])) {
            return isset($response['data'][0]) ? $response['data'] : [$response['data']];
        }

        if (isset($response[0])) {
            return $response;
        }

        return [$response];
    }

    // ─── Helpers ─────────────────────────────────────────────────

    /**
     * Check if this is an API model.
     */
    public function isApiModel(): bool
    {
        return true;
    }

    /**
     * Get raw API response data.
     */
    public function getApiResponseData(): ?array
    {
        return $this->apiResponseData;
    }

    /**
     * Override newQuery to return standard Eloquent Builder.
     * This ensures Laravel 11 morphTo/belongsTo type hints work.
     */
    public function newQuery()
    {
        return parent::newQuery();
    }
}

/**
 * Minimal query builder for ApiModelLite — collects where clauses
 * and executes them as API query parameters.
 */
class ApiLiteQueryBuilder
{
    protected ApiModelLite $model;
    protected array $wheres = [];
    protected ?int $limitValue = null;

    public function __construct(ApiModelLite $model, $column = null, $operator = null, $value = null)
    {
        $this->model = $model;

        if ($column !== null) {
            $this->addWhere($column, $operator, $value);
        }
    }

    public function where($column, $operator = null, $value = null): static
    {
        $this->addWhere($column, $operator, $value);
        return $this;
    }

    public function take(int $value): static
    {
        $this->limitValue = $value;
        return $this;
    }

    public function limit(int $value): static
    {
        return $this->take($value);
    }

    public function get(): \Illuminate\Support\Collection
    {
        $params = $this->wheres;

        if ($this->limitValue) {
            $params['limit'] = $this->limitValue;
        }

        try {
            $response = $this->model->apiGet($this->model->getApiEndpoint(), $params);
            $items = $this->model->extractItems($response);

            return collect($items)
                ->map(fn($item) => (new ($this->model::class))->newFromApiResponse($item))
                ->filter();
        } catch (\Throwable $e) {
            return collect();
        }
    }

    public function getFromApi(): \Illuminate\Support\Collection
    {
        return $this->get();
    }

    public function first()
    {
        return $this->take(1)->get()->first();
    }

    protected function addWhere($column, $operator, $value): void
    {
        if ($value === null && $operator !== null) {
            $value = $operator;
        }
        $this->wheres[$column] = $value;
    }

    // Make apiGet and extractItems accessible from query builder
    public function __call($method, $parameters)
    {
        if (method_exists($this->model, $method)) {
            return $this->model->$method(...$parameters);
        }

        throw new \BadMethodCallException("Method {$method} does not exist on " . get_class($this));
    }
}
